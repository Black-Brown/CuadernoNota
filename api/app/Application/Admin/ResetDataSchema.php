<?php

namespace App\Application\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Explicitly reviewed scope. New tables must be reviewed, never guessed by name. */
class ResetDataSchema
{
    public const DELETE = [
        'activity_scores' => 'Calificaciones por actividad',
        'alerts' => 'Alertas académicas',
        'attendances' => 'Asistencias',
        'course_activities' => 'Actividades de cursos',
        'course_offerings' => 'Cursos',
        'final_grades' => 'Calificaciones finales',
        'grade_review_actions' => 'Historial de revisión de notas',
        'grade_subjects' => 'Relaciones grado–materia',
        'grades' => 'Grados',
        'legacy_activities_unresolved' => 'Actividades históricas sin resolver',
        'observations' => 'Observaciones',
        'period_grades' => 'Calificaciones por período',
        'promotion_decisions' => 'Decisiones de promoción',
        'sections' => 'Secciones',
        'student_enrollments' => 'Matrículas',
        'student_promotions' => 'Historial de promociones',
        'students' => 'Estudiantes',
        'subjects' => 'Materias',
        'teacher_assignments' => 'Asignaciones docentes',
    ];

    public const KEEP = [
        'activity_templates' => 'Catálogo de actividades base (incluye las seis fijas)',
        'academic_years' => 'Años escolares',
        'audit_logs' => 'Auditoría histórica (se añadirá el restablecimiento)',
        'cache' => 'Caché técnica y autenticación Google',
        'cache_locks' => 'Bloqueos técnicos',
        'competencies' => 'Configuración fija C1, C2 y C3',
        'failed_jobs' => 'Registro técnico de trabajos fallidos',
        'job_batches' => 'Registro técnico de lotes de trabajos',
        'jobs' => 'Cola técnica de trabajos',
        'migrations' => 'Migraciones',
        'password_reset_tokens' => 'Recuperación de contraseñas',
        'periods' => 'Períodos escolares',
        'personal_access_tokens' => 'Tokens de acceso',
        'sessions' => 'Sesiones',
        'users' => 'Usuarios, roles y credenciales (incluye profesores)',
    ];

    public function inspect(): array
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'pgsql', 'sqlite'], true) || DB::connection()->getTablePrefix() !== '') {
            $this->fail('Este motor o prefijo requiere una revisión específica antes de restablecer datos.');
        }
        $namespace = match ($driver) {
            'mysql' => DB::connection()->getDatabaseName(),
            'pgsql' => 'public',
            default => 'main',
        };
        $tables = [];
        foreach (Schema::getTables($namespace) as $table) {
            $name = $table['name'];
            if (! isset(self::KEEP[$name]) && ! isset(self::DELETE[$name])) {
                $this->fail("Tabla pendiente de revisión: {$name}. No se eliminó ningún dato.");
            }
            if ($driver === 'mysql' && strtolower($table['engine'] ?? '') !== 'innodb') {
                $this->fail("La tabla {$name} no garantiza transacciones InnoDB.");
            }
            $indexes = Schema::getIndexes($name);
            $primary = collect($indexes)->firstWhere('primary', true)['columns'] ?? [];
            if ($primary === []) {
                $this->fail("La tabla {$name} no tiene una clave primaria verificable.");
            }
            $tables[$name] = [
                'primary' => $primary,
                'columns' => Schema::getColumns($name),
                'indexes' => $indexes,
                'foreign_keys' => Schema::getForeignKeys($name),
            ];
        }
        ksort($tables);
        $missing = array_diff(array_keys(self::KEEP + self::DELETE), array_keys($tables));
        if ($missing !== []) {
            $this->fail('Esquema incompleto. Revisa las migraciones: '.implode(', ', $missing));
        }

        $views = Schema::getViews($namespace);
        $viewNames = array_column($views, 'name');
        sort($viewNames);
        if ($viewNames !== ['activities', 'teacher_sections']) {
            $this->fail('Las vistas del sistema cambiaron y requieren revisión.');
        }

        if ($driver === 'mysql') {
            if ((int) DB::selectOne('SELECT @@SESSION.foreign_key_checks AS enabled')->enabled !== 1) {
                $this->fail('Las claves foráneas deben estar habilitadas.');
            }
            $triggers = DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()');
            $external = DB::select('SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND TABLE_SCHEMA <> DATABASE()');
            if ($external !== []) {
                $this->fail('Existen dependencias desde otra base de datos; se requiere revisión.');
            }
            $checks = DB::select('SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS JOIN information_schema.TABLE_CONSTRAINTS USING (CONSTRAINT_SCHEMA, CONSTRAINT_NAME) WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME');
        } elseif ($driver === 'pgsql') {
            // session_replication_role = 'replica' is Postgres' equivalent of MySQL's foreign_key_checks=0:
            // it suspends FK and trigger enforcement for the session.
            if (DB::selectOne("SELECT current_setting('session_replication_role') AS role")->role !== 'origin') {
                $this->fail('Las claves foráneas deben estar habilitadas.');
            }
            $triggers = DB::select('SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = current_schema()');
            $external = DB::select(
                "SELECT tc.table_name FROM information_schema.table_constraints tc
                 JOIN information_schema.constraint_column_usage ccu
                   ON ccu.constraint_name = tc.constraint_name AND ccu.constraint_schema = tc.constraint_schema
                 WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = current_schema() AND ccu.table_schema <> current_schema()"
            );
            if ($external !== []) {
                $this->fail('Existen dependencias desde otra base de datos; se requiere revisión.');
            }
            $checks = DB::select(
                'SELECT tc.table_name, tc.constraint_name, cc.check_clause
                 FROM information_schema.check_constraints cc
                 JOIN information_schema.table_constraints tc USING (constraint_schema, constraint_name)
                 WHERE tc.constraint_schema = current_schema()
                 ORDER BY tc.table_name, tc.constraint_name'
            );
        } else {
            if ((int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys !== 1) {
                $this->fail('Las claves foráneas deben estar habilitadas.');
            }
            $triggers = DB::select("SELECT name FROM sqlite_master WHERE type = 'trigger' UNION ALL SELECT name FROM sqlite_temp_master WHERE type = 'trigger'");
            // SQLite stores CHECK constraints in the table definition.
            $checks = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        }
        if ($triggers !== []) {
            $this->fail('Existen triggers sin revisar. El restablecimiento está bloqueado por seguridad.');
        }

        foreach ($tables as $name => $table) {
            foreach ($table['foreign_keys'] as $fk) {
                $parent = $fk['foreign_table'];
                if (! isset($tables[$parent]) || ($fk['foreign_schema'] && $fk['foreign_schema'] !== $namespace)) {
                    $this->fail("Dependencia externa o desconocida en {$name}.");
                }
                if (isset(self::KEEP[$name], self::DELETE[$parent])) {
                    $this->fail("{$name} debe conservarse pero depende de {$parent}. Se requiere revisión.");
                }
            }
        }

        return [
            'tables' => $tables,
            'delete_order' => $this->deletionOrder($tables),
            'fingerprint' => hash('sha256', json_encode([$tables, $views, $checks], JSON_THROW_ON_ERROR)),
        ];
    }

    /** Kahn's algorithm on child -> parent edges, independent of table names/order. */
    public function deletionOrder(array $tables): array
    {
        $pending = array_fill_keys(array_keys(self::DELETE), true);
        $order = [];
        while ($pending !== []) {
            $parents = [];
            foreach (array_keys($pending) as $child) {
                foreach ($tables[$child]['foreign_keys'] as $fk) {
                    if (isset($pending[$fk['foreign_table']])) {
                        $parents[$fk['foreign_table']] = true;
                    }
                }
            }
            $leaves = array_diff_key($pending, $parents);
            if ($leaves === []) {
                $this->fail('Hay un ciclo de claves foráneas. No se pueden limpiar los datos de forma segura.');
            }
            foreach (array_keys($leaves) as $name) {
                $order[] = $name;
                unset($pending[$name]);
            }
        }

        return $order;
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['reset' => $message]);
    }
}
