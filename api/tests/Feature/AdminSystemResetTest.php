<?php

namespace Tests\Feature;

use App\Application\Admin\ResetDataSchema;
use App\Application\Admin\ResetSystemData;
use App\Infrastructure\Models\CourseOffering;
use App\Models\User;
use Database\Seeders\CompetencySeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSystemResetTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/system/reset-data';

    public function test_reset_clears_all_academic_tables_preserves_complete_protected_rows_and_audits_once(): void
    {
        $admin = $this->fixture();
        $before = $this->snapshot();
        $preview = $this->getJson(self::URL.'/preview')->assertOk()->json();
        $this->assertSame($before, $this->snapshot(), 'Preview must be read-only.');
        foreach ($preview['delete'] as $row) {
            $this->assertSame(count($before[$row['table']]), $row['count']);
            $this->assertGreaterThan(0, $row['count'], $row['table'].' needs a fixture');
        }
        $schema = app(ResetDataSchema::class)->inspect();
        $positions = array_flip($preview['delete_order']);
        foreach ($schema['tables'] as $child => $table) {
            foreach ($table['foreign_keys'] as $fk) {
                if (isset($positions[$child], $positions[$fk['foreign_table']])) {
                    $this->assertLessThan($positions[$fk['foreign_table']], $positions[$child]);
                }
            }
        }

        $response = $this->postJson(self::URL, $this->confirmation($preview))->assertOk()
            ->assertJsonPath('total_deleted', $preview['total_to_delete'])
            ->assertJsonPath('users_preserved', 2)
            ->assertJsonPath('academic_years_preserved', 2)
            ->assertJsonPath('periods_preserved', 2);
        $after = $this->snapshot();
        foreach (ResetDataSchema::DELETE as $table => $label) {
            $this->assertSame([], $after[$table], $table);
        }
        foreach (ResetDataSchema::KEEP as $table => $label) {
            if ($table !== 'audit_logs') {
                $this->assertSame($before[$table], $after[$table], $table.' must be unchanged');
            }
        }
        $this->assertSame($schema['fingerprint'], app(ResetDataSchema::class)->inspect()['fingerprint']);
        $this->assertDatabaseCount('audit_logs', count($before['audit_logs']) + 1);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'SYSTEM_DATA_RESET']);
        $detail = json_decode(DB::table('audit_logs')->where('action', 'SYSTEM_DATA_RESET')->value('detail'), true);
        $this->assertSame('success', $detail['result']);
        $this->assertSame($response->json('total_deleted'), $detail['total_deleted']);
        $this->assertSame($before['audit_logs'][0], $after['audit_logs'][0]);
        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('teacher_sections', 0);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        // Replay cannot erase records newly configured after the first reset.
        $this->postJson(self::URL, $this->confirmation($preview))->assertUnprocessable();
        foreach (['/api/auth/me', '/api/admin/dashboard', '/api/admin/users', '/api/admin/academic-years',
            '/api/admin/grades', '/api/admin/sections', '/api/admin/subjects', '/api/admin/students',
            '/api/admin/teacher-assignments', '/api/admin/grade-reviews', '/api/admin/reports/academic',
            '/api/admin/reports/attendance', '/api/admin/audit-logs'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        Sanctum::actingAs(User::where('role', 'teacher')->firstOrFail());
        $this->getJson('/api/docente/dashboard')->assertOk();
        $this->getJson('/api/docente/courses')->assertOk();
    }

    public function test_new_course_after_reset_reuses_the_same_six_fixed_templates(): void
    {
        $this->fixture();
        $templatesBefore = DB::table('activity_templates')->orderBy('id')->get()->toJson();
        $preview = $this->getJson(self::URL.'/preview')->assertOk()->json();
        $this->postJson(self::URL, $this->confirmation($preview))->assertOk();
        $this->assertSame($templatesBefore, DB::table('activity_templates')->orderBy('id')->get()->toJson());
        $grade = $this->postJson('/api/admin/grades', ['name' => 'Nuevo grado', 'level' => 'Primaria', 'sort_order' => 1])->assertCreated()->json('id');
        $subject = $this->postJson('/api/admin/subjects', ['name' => 'Nueva materia', 'code' => 'NUEVA', 'grade_ids' => [$grade]])->assertCreated()->json('id');
        $section = $this->postJson('/api/admin/sections', [
            'grade_id' => $grade, 'academic_year_id' => 1, 'name' => 'A', 'shift' => 'Matutina',
        ])->assertCreated()->json('id');
        $offering = CourseOffering::where('section_id', $section)->where('subject_id', $subject)->firstOrFail();
        $this->assertDatabaseCount('activity_templates', 6);
        $this->assertSame($templatesBefore, DB::table('activity_templates')->orderBy('id')->get()->toJson());
        $this->assertSame(6, DB::table('course_activities')->where('course_offering_id', $offering->id)->count());
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('activity_scores', 0);
    }

    public function test_exact_confirmation_and_valid_fresh_actor_bound_preview_are_required(): void
    {
        $admin = $this->fixture();
        $preview = $this->getJson(self::URL.'/preview')->assertOk()->json();
        $before = $this->snapshot();
        foreach (['restablecer datos', ' RESTABLECER DATOS ', ''] as $confirmation) {
            $this->postJson(self::URL, ['confirmation' => $confirmation, 'preview_token' => $preview['preview_token']])->assertUnprocessable();
        }
        $this->postJson(self::URL, ['confirmation' => ResetSystemData::CONFIRMATION, 'preview_token' => 'forged'])->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $this->postJson(self::URL, $this->confirmation($preview))->assertUnprocessable();
        Sanctum::actingAs($admin);
        $this->travel(6)->minutes();
        $this->postJson(self::URL, $this->confirmation($preview))->assertUnprocessable();
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_same_count_but_changed_content_invalidates_preview(): void
    {
        $this->fixture();
        $preview = $this->getJson(self::URL.'/preview')->assertOk()->json();
        DB::table('grades')->where('id', 1)->update(['name' => 'Modificado']);
        $before = $this->snapshot();
        $this->postJson(self::URL, $this->confirmation($preview))->assertUnprocessable();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_error_after_several_deletes_rolls_back_every_row(): void
    {
        $admin = $this->fixture();
        $service = app(ResetSystemData::class);
        $preview = $service->preview($admin);
        $before = $this->snapshot();
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected) {
            if (! $injected && str_starts_with(strtolower($query->sql), 'delete from "course_offerings"')) {
                $injected = true;
                throw new \RuntimeException('Injected reset failure');
            }
        });
        try {
            $service->execute($admin, ResetSystemData::CONFIRMATION, $preview['preview_token'], '127.0.0.1');
            $this->fail('Expected injected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected reset failure', $exception->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_audit_failure_also_rolls_back_all_deletions(): void
    {
        $admin = $this->fixture();
        $service = app(ResetSystemData::class);
        $preview = $service->preview($admin);
        $before = $this->snapshot();
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected) {
            if (! $injected && str_starts_with(strtolower($query->sql), 'insert into "audit_logs"')) {
                $injected = true;
                throw new \RuntimeException('Injected audit failure');
            }
        });
        try {
            $service->execute($admin, ResetSystemData::CONFIRMATION, $preview['preview_token'], null);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected audit failure', $exception->getMessage());
        }
        $this->assertTrue($injected);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_guests_teachers_coordinators_and_inactive_admins_cannot_reset_or_preview(): void
    {
        $this->getJson(self::URL.'/preview')->assertUnauthorized();
        $this->postJson(self::URL)->assertUnauthorized();
        $this->fixture();
        foreach ([['teacher', true], ['coordinator', true], ['admin', false]] as [$role, $active]) {
            Sanctum::actingAs(User::factory()->create(compact('role', 'active')));
            $this->getJson(self::URL.'/preview')->assertForbidden();
            $this->postJson(self::URL, ['confirmation' => ResetSystemData::CONFIRMATION, 'preview_token' => 'anything'])->assertForbidden();
        }
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_unknown_tables_and_triggers_block_reset_before_any_delete(): void
    {
        $this->fixture();
        $preview = $this->getJson(self::URL.'/preview')->assertOk()->json();
        Schema::create('unreviewed_data', fn (Blueprint $table) => $table->id());
        $this->getJson(self::URL.'/preview')->assertUnprocessable();
        $this->postJson(self::URL, $this->confirmation($preview))->assertUnprocessable();
        Schema::drop('unreviewed_data');
        DB::unprepared('CREATE TRIGGER unsafe_reset AFTER DELETE ON grades BEGIN DELETE FROM users; END');
        $this->getJson(self::URL.'/preview')->assertUnprocessable();
        $this->postJson(self::URL, $this->confirmation($preview))->assertUnprocessable();
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_pending_jobs_block_reset_without_discarding_them(): void
    {
        $this->fixture();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
        $this->getJson(self::URL.'/preview')->assertUnprocessable();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_modified_protected_content_causes_rollback_even_if_counts_match(): void
    {
        $admin = $this->fixture();
        $service = app(ResetSystemData::class);
        $preview = $service->preview($admin);
        $before = $this->snapshot();
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected, $admin) {
            if (! $injected && str_starts_with(strtolower($query->sql), 'delete from "grades"')) {
                $injected = true;
                DB::table('users')->where('id', $admin->id)->update(['name' => 'Unexpected change']);
            }
        });
        try {
            $service->execute($admin, ResetSystemData::CONFIRMATION, $preview['preview_token'], null);
            $this->fail('Expected protected data validation');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('información protegida', $exception->errors()['reset'][0]);
        }
        $this->assertTrue($injected);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_foreign_key_cycles_are_rejected_not_bypassed(): void
    {
        $schema = app(ResetDataSchema::class);
        $tables = $schema->inspect()['tables'];
        $tables['course_offerings']['foreign_keys'][] = ['foreign_table' => 'course_activities'];
        $this->expectException(ValidationException::class);
        $schema->deletionOrder($tables);
    }

    private function confirmation(array $preview): array
    {
        return ['confirmation' => ResetSystemData::CONFIRMATION, 'preview_token' => $preview['preview_token']];
    }

    private function snapshot(): array
    {
        $rows = [];
        foreach (ResetDataSchema::KEEP + ResetDataSchema::DELETE as $table => $label) {
            $key = match ($table) {
                'cache', 'cache_locks' => 'key', 'password_reset_tokens' => 'email', default => 'id'
            };
            $rows[$table] = DB::table($table)->orderBy($key)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $rows;
    }

    private function fixture(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'active' => true, 'google_id' => 'preserve-google-identity']);
        Sanctum::actingAs($admin);
        $this->seed(CompetencySeeder::class);
        $teacher->createToken('preserved-token');
        DB::table('password_reset_tokens')->insert(['email' => $teacher->email, 'token' => 'preserved-reset-token']);
        DB::table('sessions')->insert(['id' => 'preserved-session', 'user_id' => $teacher->id, 'payload' => 'session-data', 'last_activity' => time()]);
        DB::table('cache')->insert(['key' => 'google-oauth-test', 'value' => 'oauth-data', 'expiration' => time() + 300]);
        DB::table('cache_locks')->insert(['key' => 'test-lock', 'owner' => 'owner', 'expiration' => time() + 300]);
        DB::table('failed_jobs')->insert(['uuid' => 'failed-test', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'historical failure']);
        DB::table('job_batches')->insert(['id' => 'completed-batch', 'name' => 'Technical history', 'total_jobs' => 0, 'pending_jobs' => 0, 'failed_jobs' => 0, 'failed_job_ids' => '[]', 'created_at' => time(), 'finished_at' => time()]);
        foreach ([1, 2] as $id) {
            DB::table('academic_years')->insert(['id' => $id, 'name' => 'Año '.$id, 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => $id === 1]);
            DB::table('periods')->insert(['id' => $id, 'academic_year_id' => $id, 'number' => 1, 'name' => 'Primer período', 'months' => 'Ago-Oct', 'start_date' => '2026-08-01', 'end_date' => '2026-10-31']);
        }
        DB::table('grades')->insert(['id' => 1, 'name' => 'Primero', 'level' => 'Primaria', 'sort_order' => 1]);
        DB::table('subjects')->insert(['id' => 1, 'name' => 'Robótica', 'code' => 'ROB']);
        DB::table('grade_subjects')->insert(['grade_id' => 1, 'subject_id' => 1]);
        foreach ([1, 2] as $id) {
            DB::table('sections')->insert(['id' => $id, 'grade_id' => 1, 'academic_year_id' => $id, 'name' => 'A', 'shift' => 'Matutina']);
            CourseOffering::create(['section_id' => $id, 'subject_id' => 1, 'active' => true]);
        }
        DB::table('teacher_assignments')->insert(['teacher_id' => $teacher->id, 'course_offering_id' => 1, 'assigned_by' => $admin->id]);
        DB::table('students')->insert(['id' => 1, 'name' => 'Ana', 'last_name' => 'Prueba', 'enrollment_no' => 'RESET-1', 'section_id' => 1, 'academic_year_id' => 1]);
        DB::table('student_enrollments')->insert(['id' => 1, 'student_id' => 1, 'section_id' => 1, 'enrolled_at' => '2026-08-01', 'created_by' => $admin->id]);
        DB::table('activity_scores')->insert(['activity_id' => DB::table('course_activities')->value('id'), 'student_id' => 1, 'competency_id' => 1, 'period_id' => 1, 'subject_id' => 1, 'score' => 85]);
        DB::table('period_grades')->insert(['student_id' => 1, 'subject_id' => 1, 'period_id' => 1, 'period_score' => 85, 'approved_by' => $admin->id]);
        DB::table('final_grades')->insert(['student_id' => 1, 'subject_id' => 1, 'academic_year_id' => 1, 'cf' => 85]);
        DB::table('attendances')->insert(['student_id' => 1, 'section_id' => 1, 'user_id' => $teacher->id, 'date' => '2026-08-01', 'code' => 'P']);
        DB::table('observations')->insert(['student_id' => 1, 'section_id' => 1, 'subject_id' => 1, 'period_id' => 1, 'user_id' => $teacher->id, 'date' => '2026-08-01', 'type' => 'academic', 'description' => 'Test']);
        DB::table('alerts')->insert(['student_id' => 1, 'type' => 'performance', 'message' => 'Test', 'resolved_by' => $admin->id]);
        DB::table('student_promotions')->insert(['student_id' => 1, 'academic_year_id' => 1]);
        DB::table('promotion_decisions')->insert(['student_enrollment_id' => 1, 'status' => 'promoted', 'destination_section_id' => 2, 'decided_by' => $admin->id]);
        DB::table('grade_review_actions')->insert(['period_id' => 1, 'subject_id' => 1, 'section_id' => 1, 'action' => 'approved', 'performed_by' => $admin->id]);
        DB::table('legacy_activities_unresolved')->insert(['legacy_activity_id' => 99, 'payload' => '{}', 'reason' => 'Unresolved historical data']);
        DB::table('audit_logs')->insert(['user_id' => $admin->id, 'action' => 'post', 'affected_table' => 'students', 'record_id' => 1, 'detail' => '{"historical":true}']);

        return $admin;
    }
}
