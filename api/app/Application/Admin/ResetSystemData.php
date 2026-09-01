<?php

namespace App\Application\Admin;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ResetSystemData
{
    public const CONFIRMATION = 'RESTABLECER DATOS';

    public function __construct(private ResetDataSchema $schema) {}

    public function preview(User $actor): array
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor) {
            $schema = $this->schema->inspect();
            $this->assertReady();
            $this->assertIntegrity($schema);
            $deleted = $this->counts(ResetDataSchema::DELETE);
            $expires = now()->addMinutes(5);

            return [
                'delete' => $deleted,
                'preserve' => $this->counts(ResetDataSchema::KEEP),
                'total_to_delete' => array_sum(array_column($deleted, 'count')),
                'delete_order' => $schema['delete_order'],
                'expires_at' => $expires->toIso8601String(),
                'preview_token' => Crypt::encryptString(json_encode([
                    'actor_id' => $actor->id,
                    'expires' => $expires->timestamp,
                    'schema' => $schema['fingerprint'],
                    'data' => $this->fingerprints($schema, array_keys(ResetDataSchema::DELETE)),
                    'counts' => $deleted,
                    // Reject replay, including when the reset was performed on an empty system.
                    'last_reset' => $this->lastReset(),
                ], JSON_THROW_ON_ERROR)),
            ];
        });
    }

    public function execute(User $actor, string $confirmation, string $token, ?string $ip): array
    {
        $this->authorize($actor);
        if ($confirmation !== self::CONFIRMATION) {
            $this->schema->fail('Escribe exactamente RESTABLECER DATOS.');
        }
        $driver = DB::connection()->getDriverName();
        $locking = in_array($driver, ['mysql', 'pgsql'], true);
        // Named lock serializes resets; range locks below also block concurrent ordinary writes.
        $lockName = 'reset:'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 50);
        if ($locking) {
            $acquired = match ($driver) {
                'mysql' => (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired === 1,
                'pgsql' => (bool) DB::selectOne('SELECT pg_try_advisory_lock(hashtext(?)::bigint) AS acquired', [$lockName])->acquired,
            };
            if (! $acquired) {
                $this->schema->fail('Ya hay un restablecimiento en curso.');
            }
        }
        try {
            if ($locking) {
                if (DB::transactionLevel() !== 0) {
                    $this->schema->fail('El restablecimiento necesita su propia transacción.');
                }
                // Applies only to the next transaction, not to the session's default isolation.
                DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            }

            return DB::transaction(function () use ($actor, $token, $ip) {
                $schema = $this->schema->inspect();
                foreach ($schema['tables'] as $name => $table) {
                    // Full PK scan locks existing records AND empty ranges on MySQL/InnoDB.
                    DB::table($name)->select($table['primary'])->orderBy($table['primary'][0])->lockForUpdate()->get();
                }
                $this->authorize($actor); // Re-read after locking: no stale admin role/active flag.
                $this->assertReady();
                $this->assertIntegrity($schema);
                if ($schema['fingerprint'] !== $this->schema->inspect()['fingerprint']) {
                    $this->schema->fail('El esquema cambió durante la validación.');
                }
                $preview = $this->decodePreview($token);
                $currentCounts = $this->counts(ResetDataSchema::DELETE);
                if (($preview['actor_id'] ?? null) !== $actor->id
                    || ($preview['expires'] ?? 0) <= now()->timestamp
                    || ($preview['schema'] ?? null) !== $schema['fingerprint']
                    || ($preview['last_reset'] ?? null) !== $this->lastReset()
                    || ($preview['counts'] ?? null) !== $currentCounts
                    || ($preview['data'] ?? null) !== $this->fingerprints($schema, array_keys(ResetDataSchema::DELETE))) {
                    $this->schema->fail('La vista previa venció o los datos cambiaron. Revisa un resumen nuevo antes de confirmar.');
                }

                $preserved = $this->fingerprints($schema, array_keys(ResetDataSchema::KEEP));
                $removed = [];
                foreach ($schema['delete_order'] as $table) {
                    $removed[] = ['table' => $table, 'label' => ResetDataSchema::DELETE[$table], 'count' => DB::table($table)->delete()];
                }

                foreach (array_keys(ResetDataSchema::DELETE) as $table) {
                    if (DB::table($table)->exists()) {
                        $this->schema->fail("La limpieza de {$table} no fue completa. Se revertirá la operación.");
                    }
                }
                if ($preserved !== $this->fingerprints($schema, array_keys(ResetDataSchema::KEEP))) {
                    $this->schema->fail('Cambió información protegida. Se revertirá la operación.');
                }
                $this->assertIntegrity($schema);
                $result = [
                    'message' => 'Restablecimiento completado. Los usuarios y años/períodos escolares fueron conservados.',
                    'deleted' => $removed,
                    'total_deleted' => array_sum(array_column($removed, 'count')),
                    'users_preserved' => DB::table('users')->count(),
                    'academic_years_preserved' => DB::table('academic_years')->count(),
                    'periods_preserved' => DB::table('periods')->count(),
                    'completed_at' => now()->toIso8601String(),
                ];
                // Same transaction as the deletion: an audit insert failure rolls everything back.
                DB::table('audit_logs')->insert([
                    'user_id' => $actor->id, 'action' => 'SYSTEM_DATA_RESET',
                    'affected_table' => 'system', 'record_id' => 0,
                    'detail' => json_encode(['result' => 'success'] + $result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'ip' => $ip, 'created_at' => now(),
                ]);

                return $result;
            }, 1); // Never silently retry a destructive operation.
        } finally {
            if ($locking) {
                match ($driver) {
                    'mysql' => DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]),
                    'pgsql' => DB::selectOne('SELECT pg_advisory_unlock(hashtext(?)::bigint) AS released', [$lockName]),
                };
            }
        }
    }

    private function authorize(User $actor): void
    {
        abort_unless(DB::table('users')->where('id', $actor->id)->where('role', 'admin')->where('active', true)->exists(), 403,
            'Solo un administrador activo puede restablecer los datos.');
    }

    private function assertReady(): void
    {
        // Workers may contain references without FKs. Never discard or run pending jobs implicitly.
        if (DB::table('jobs')->exists() || DB::table('job_batches')->where('pending_jobs', '>', 0)->exists()) {
            $this->schema->fail('Hay trabajos pendientes. Detén los workers y revisa la cola antes de restablecer.');
        }
        $competencies = DB::table('competencies')->orderBy('id')->pluck('code', 'id')->all();
        if ($competencies !== [] && $competencies !== [1 => 'C1', 2 => 'C2', 3 => 'C3']) {
            $this->schema->fail('El catálogo de competencias no coincide con C1–C3 y necesita revisión.');
        }
    }

    private function decodePreview(string $token): array
    {
        try {
            $value = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($value)) {
                return $value;
            }
        } catch (DecryptException|\JsonException) {
            // Never return token contents, SQL or credentials to the client.
        }
        $this->schema->fail('La vista previa no es válida. Solicita un resumen nuevo.');
    }

    private function lastReset(): int
    {
        return (int) DB::table('audit_logs')->where('action', 'SYSTEM_DATA_RESET')->max('id');
    }

    private function counts(array $labels): array
    {
        $result = [];
        foreach ($labels as $table => $label) {
            $result[] = ['table' => $table, 'label' => $label, 'count' => DB::table($table)->count()];
        }

        return $result;
    }

    /** Compare complete rows, not just counts. Hashes/credentials never leave the server. */
    private function fingerprints(array $schema, array $names): array
    {
        $result = [];
        foreach ($names as $name) {
            $query = DB::table($name);
            foreach ($schema['tables'][$name]['primary'] as $column) {
                $query->orderBy($column);
            }
            $hash = hash_init('sha256');
            foreach ($query->lazy(500) as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
            }
            $result[$name] = hash_final($hash);
        }

        return $result;
    }

    private function assertIntegrity(array $schema): void
    {
        foreach ($schema['tables'] as $name => $table) {
            foreach ($table['foreign_keys'] as $fk) {
                $query = DB::table($name.' as child');
                foreach ($fk['columns'] as $column) {
                    $query->whereNotNull('child.'.$column);
                }
                $query->whereNotExists(function ($parent) use ($fk) {
                    $parent->selectRaw('1')->from($fk['foreign_table'].' as parent');
                    foreach ($fk['columns'] as $i => $column) {
                        $parent->whereColumn('child.'.$column, 'parent.'.$fk['foreign_columns'][$i]);
                    }
                });
                if ($query->exists()) {
                    $this->schema->fail("Hay referencias huérfanas en {$name}. Revisa la integridad antes de continuar.");
                }
            }
        }
    }
}
