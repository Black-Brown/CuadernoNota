<?php

namespace Tests\Integration;

use App\Application\Admin\ResetDataSchema;
use App\Application\Admin\ResetSystemData;
use App\Infrastructure\Models\CourseOffering;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PDO;
use Tests\TestCase;

/** Opt-in integration test. NEVER migrates or resets the configured application database. */
class MySqlSystemResetTest extends TestCase
{
    private ?PDO $server = null;

    private ?string $temporaryDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MYSQL_RESET_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with RUN_MYSQL_RESET_TESTS=1; a disposable database will be created.');
        }
        $config = config('database.connections.mysql');
        $this->server = new PDO(
            'mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4',
            $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $name = 'cuaderno_reset_test_'.bin2hex(random_bytes(8));
        // CREATE (not IF NOT EXISTS): a pre-existing database is never adopted or deleted.
        $this->server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->temporaryDatabase = $name;
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null]);
        DB::purge('mysql');
        $this->assertSame($name, DB::connection()->getDatabaseName());
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->temporaryDatabase !== null && preg_match('/\Acuaderno_reset_test_[a-f0-9]{16}\z/', $this->temporaryDatabase)) {
                DB::disconnect('mysql');
                $this->server->exec('DROP DATABASE `'.$this->temporaryDatabase.'`');
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_mysql_commit_and_rollback_in_a_disposable_database(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        Sanctum::actingAs($admin);
        DB::table('academic_years')->insert(['id' => 1, 'name' => 'Test', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30']);
        DB::table('periods')->insert(['id' => 1, 'academic_year_id' => 1, 'number' => 1, 'name' => 'Test', 'months' => 'Ago-Oct', 'start_date' => '2026-08-01', 'end_date' => '2026-10-31']);
        DB::table('grades')->insert(['id' => 1, 'name' => 'Test', 'level' => 'Primaria', 'sort_order' => 1]);
        DB::table('subjects')->insert(['id' => 1, 'name' => 'Test', 'code' => 'TEST']);
        DB::table('sections')->insert(['id' => 1, 'grade_id' => 1, 'academic_year_id' => 1, 'name' => 'A', 'shift' => 'Matutina']);
        CourseOffering::create(['section_id' => 1, 'subject_id' => 1, 'active' => true]);
        DB::table('teacher_assignments')->insert(['teacher_id' => $teacher->id, 'course_offering_id' => 1]);
        $protected = DB::table('users')->get()->toJson();
        $service = app(ResetSystemData::class);
        $preview = $service->preview($admin);
        $lockName = 'reset:'.substr(hash('sha256', $this->temporaryDatabase), 0, 50);
        $statement = $this->server->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([$lockName]);
        $this->assertSame(1, (int) $statement->fetchColumn());
        try {
            $service->execute($admin, ResetSystemData::CONFIRMATION, $preview['preview_token'], null);
            $this->fail('Concurrent resets must be blocked');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('en curso', $exception->errors()['reset'][0]);
        } finally {
            $statement = $this->server->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$lockName]);
        }
        $injected = false;
        DB::listen(function (QueryExecuted $event) use (&$injected) {
            if (! $injected && str_starts_with(strtolower($event->sql), 'delete from `course_offerings`')) {
                $injected = true;
                throw new \RuntimeException('MySQL rollback test');
            }
        });
        try {
            $service->execute($admin, ResetSystemData::CONFIRMATION, $preview['preview_token'], null);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $exception) {
            $this->assertSame('MySQL rollback test', $exception->getMessage());
        }
        $this->assertDatabaseCount('course_activities', 6);
        $this->assertDatabaseCount('teacher_assignments', 1);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame($protected, DB::table('users')->get()->toJson());
        $this->assertSame(0, DB::transactionLevel());

        $fresh = $this->getJson('/api/admin/system/reset-data/preview')->assertOk()->json();
        $this->postJson('/api/admin/system/reset-data', [
            'confirmation' => ResetSystemData::CONFIRMATION, 'preview_token' => $fresh['preview_token'],
        ])->assertOk()->assertJsonPath('total_deleted', $fresh['total_to_delete']);
        foreach (ResetDataSchema::DELETE as $table => $label) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame($protected, DB::table('users')->get()->toJson());
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('activity_templates', 6);
        $this->assertDatabaseCount('periods', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(1, (int) DB::selectOne('SELECT @@SESSION.foreign_key_checks AS enabled')->enabled);
    }
}
