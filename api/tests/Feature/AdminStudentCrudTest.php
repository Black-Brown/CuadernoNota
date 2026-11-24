<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStudentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_delete_students_without_history(): void
    {
        $this->admin();
        $first = $this->student();
        $second = $this->student(['enrollment_no' => 'DELETE-2', 'active' => false]);
        $payload = ['student_ids' => [$first->id, $second->id], 'confirmation' => 'ELIMINAR'];
        $this->postJson('/api/admin/students/bulk-delete', [...$payload, 'confirmation' => ''])->assertUnprocessable();
        $this->postJson('/api/admin/students/bulk-delete', $payload)->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $first->id]);
        $this->assertDatabaseMissing('students', ['id' => $second->id]);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'student_delete')->count());
    }

    public function test_bulk_delete_preserves_entire_batch_if_any_student_has_history(): void
    {
        $this->admin();
        [, $section] = $this->catalog();
        $first = $this->student();
        $second = $this->student(['enrollment_no' => 'HISTORY']);
        DB::table('student_enrollments')->insert(['student_id' => $second->id, 'section_id' => $section, 'enrolled_at' => '2026-01-01', 'status' => 'withdrawn']);
        $payload = ['student_ids' => [$first->id, $second->id], 'confirmation' => 'ELIMINAR'];
        $this->postJson('/api/admin/students/bulk-delete', $payload)->assertUnprocessable()->assertJsonValidationErrors('student_ids');
        $this->assertDatabaseHas('students', ['id' => $first->id]);
        $this->assertDatabaseHas('students', ['id' => $second->id]);
        $this->assertDatabaseCount('audit_logs', 0);
        foreach (['teacher', 'coordinator'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'active' => true]));
            $this->postJson('/api/admin/students/bulk-delete', $payload)->assertForbidden();
        }
    }

    public function test_admin_can_withdraw_multiple_students_and_preserve_their_status(): void
    {
        $this->admin();
        [$year, $section] = $this->catalog();
        $ids = [];
        foreach ([true, false] as $index => $active) {
            $student = $this->student(['enrollment_no' => 'BULK-'.$index, 'section_id' => $section, 'academic_year_id' => $year, 'active' => $active]);
            $ids[] = $student->id;
            DB::table('student_enrollments')->insert(['student_id' => $student->id, 'section_id' => $section, 'enrolled_at' => '2026-01-01', 'status' => 'active']);
        }
        $this->postJson('/api/admin/students/bulk-withdraw', ['student_ids' => $ids, 'section_id' => $section, 'reason' => 'Reorganización'])->assertOk();
        foreach ($ids as $index => $id) {
            $this->assertDatabaseHas('students', ['id' => $id, 'section_id' => null, 'active' => $index === 0]);
            $this->assertDatabaseHas('student_enrollments', ['student_id' => $id, 'status' => 'withdrawn']);
            $this->assertDatabaseHas('audit_logs', ['record_id' => $id, 'action' => 'student_withdraw']);
        }
    }

    public function test_bulk_withdraw_is_atomic_and_restricted_to_admin(): void
    {
        $this->admin();
        [$year, $section] = $this->catalog();
        $first = $this->student(['section_id' => $section, 'academic_year_id' => $year]);
        $second = $this->student(['enrollment_no' => 'OTHER']);
        DB::table('student_enrollments')->insert(['student_id' => $first->id, 'section_id' => $section, 'enrolled_at' => '2026-01-01', 'status' => 'active']);
        $payload = ['student_ids' => [$first->id, $second->id], 'section_id' => $section, 'reason' => 'Reorganización'];
        $this->postJson('/api/admin/students/bulk-withdraw', $payload)->assertUnprocessable();
        $this->assertDatabaseHas('students', ['id' => $first->id, 'section_id' => $section]);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $first->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
        foreach (['teacher', 'coordinator'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'active' => true]));
            $this->postJson('/api/admin/students/bulk-withdraw', $payload)->assertForbidden();
        }
    }

    public function test_withdraw_preserves_history_and_returns_active_student_to_placement(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 7));
        $this->admin();
        [$year, $section, $subject, $period] = $this->catalog();
        $student = $this->student(['section_id' => $section, 'academic_year_id' => $year]);
        $enrollment = DB::table('student_enrollments')->insertGetId(['student_id' => $student->id, 'section_id' => $section, 'enrolled_at' => '2026-09-01', 'status' => 'active']);
        DB::table('period_grades')->insert(['student_id' => $student->id, 'section_id' => $section, 'subject_id' => $subject, 'period_id' => $period, 'period_score' => 85, 'status' => 'official']);
        $url = "/api/admin/students/{$student->id}/withdraw";
        $this->postJson($url, ['section_id' => $section, 'reason' => 'Cambio de sección'])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'active' => true, 'section_id' => null, 'academic_year_id' => null]);
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollment, 'status' => 'withdrawn']);
        $this->assertSame('2026-11-07', substr((string) DB::table('student_enrollments')->where('id', $enrollment)->value('ended_at'), 0, 10));
        $this->assertDatabaseHas('period_grades', ['student_id' => $student->id, 'section_id' => $section, 'period_score' => 85, 'status' => 'official']);
        $this->getJson('/api/admin/student-placements/pending')->assertOk()->assertJsonFragment(['id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student_withdraw', 'record_id' => $student->id]);
        $this->postJson($url, ['section_id' => $section, 'reason' => 'Reintento'])->assertUnprocessable();
    }

    public function test_only_admin_can_withdraw_and_reason_is_required(): void
    {
        $this->admin();
        [$year, $section] = $this->catalog();
        $student = $this->student(['section_id' => $section, 'academic_year_id' => $year]);
        $url = "/api/admin/students/{$student->id}/withdraw";
        $this->postJson($url, ['section_id' => $section])->assertUnprocessable();
        foreach (['teacher', 'coordinator'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'active' => true]));
            $this->postJson($url, ['section_id' => $section, 'reason' => 'Retiro'])->assertForbidden();
        }
        $this->assertDatabaseHas('students', ['id' => $student->id, 'section_id' => $section]);
    }

    public function test_admin_can_edit_identity_without_changing_placement_status_or_history(): void
    {
        $admin = $this->admin();
        [$yearId, $sectionId] = $this->catalog();
        $student = $this->student(['section_id' => $sectionId, 'academic_year_id' => $yearId]);
        $enrollmentId = DB::table('student_enrollments')->insertGetId([
            'student_id' => $student->id, 'section_id' => $sectionId, 'status' => 'active', 'enrolled_at' => '2026-09-01',
        ]);

        $this->patchJson("/api/admin/students/{$student->id}", [
            'name' => 'Ana María', 'last_name' => 'Pérez Soto', 'enrollment_no' => 'CORREGIDA-001',
            'section_id' => null, 'academic_year_id' => null, 'active' => false, 'deactivation_reason' => 'No autorizado por edición',
        ])->assertOk()->assertJsonPath('name', 'Ana María')->assertJsonPath('active', true)
            ->assertJsonPath('section_id', $sectionId)->assertJsonPath('academic_year_id', $yearId);

        $this->assertDatabaseHas('students', ['id' => $student->id, 'last_name' => 'Pérez Soto', 'enrollment_no' => 'CORREGIDA-001', 'deactivation_reason' => null]);
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollmentId, 'status' => 'active', 'ended_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'affected_table' => 'students', 'record_id' => $student->id, 'action' => 'patch']);
    }

    public function test_edit_rejects_duplicate_blank_and_oversized_identity_fields(): void
    {
        $this->admin();
        $student = $this->student();
        $this->student(['enrollment_no' => 'OTRA-001']);
        $url = "/api/admin/students/{$student->id}";

        $this->patchJson($url, ['enrollment_no' => 'OTRA-001'])->assertUnprocessable()->assertJsonValidationErrors('enrollment_no');
        $this->patchJson($url, ['name' => ' ', 'last_name' => '', 'enrollment_no' => null])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'last_name', 'enrollment_no']);
        $this->patchJson($url, ['name' => str_repeat('a', 61), 'last_name' => str_repeat('a', 61), 'enrollment_no' => str_repeat('a', 21)])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'last_name', 'enrollment_no']);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Ana', 'last_name' => 'Pérez', 'enrollment_no' => 'CRUD-001']);

        $this->patchJson($url, ['enrollment_no' => 'CRUD-001'])->assertOk();
        $this->patchJson($url, ['name' => 'Ana María'])->assertOk()->assertJsonPath('last_name', 'Pérez');
    }

    public function test_admin_can_delete_an_active_or_inactive_student_without_history(): void
    {
        $admin = $this->admin();
        foreach ([true, false] as $active) {
            $student = $this->student(['active' => $active]);
            $this->deleteJson("/api/admin/students/{$student->id}", ['confirmation' => $student->enrollment_no])
                ->assertOk()->assertJsonPath('message', 'Estudiante eliminado definitivamente.');
            $this->assertDatabaseMissing('students', ['id' => $student->id]);
            $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'affected_table' => 'students', 'record_id' => $student->id, 'action' => 'delete']);
            $this->getJson("/api/admin/students/{$student->id}")->assertNotFound();
            $this->deleteJson("/api/admin/students/{$student->id}", ['confirmation' => $student->enrollment_no])->assertNotFound();
        }
    }

    public function test_deletion_requires_the_exact_enrollment_number_and_leaves_other_students_untouched(): void
    {
        $this->admin();
        $student = $this->student();
        $other = $this->student(['enrollment_no' => 'OTRA-001']);
        $url = "/api/admin/students/{$student->id}";

        foreach ([[], ['confirmation' => 'OTRA-001'], ['confirmation' => 'CRUD-001 ']] as $payload) {
            $this->deleteJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('confirmation');
            $this->assertDatabaseCount('students', 2);
        }
        $this->assertDatabaseCount('audit_logs', 0);

        $this->deleteJson($url, ['confirmation' => $student->enrollment_no])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $other->id, 'enrollment_no' => 'OTRA-001']);
    }

    #[DataProvider('historyTables')]
    public function test_academic_history_prevents_deletion_even_without_a_current_section(string $table): void
    {
        $admin = $this->admin();
        [$yearId, $sectionId, $subjectId, $periodId] = $this->catalog();
        $student = $this->student(['active' => false]);
        $fields = match ($table) {
            'student_enrollments' => ['section_id' => $sectionId, 'status' => 'withdrawn', 'enrolled_at' => '2026-09-01', 'ended_at' => '2026-09-02'],
            'period_grades' => ['section_id' => $sectionId, 'subject_id' => $subjectId, 'period_id' => $periodId, 'status' => 'official', 'period_score' => 85],
            'final_grades' => ['subject_id' => $subjectId, 'academic_year_id' => $yearId, 'cf' => 85],
            'attendances' => ['section_id' => $sectionId, 'user_id' => $admin->id, 'date' => '2026-09-01', 'code' => 'P'],
            'observations' => ['user_id' => $admin->id, 'date' => '2026-09-01', 'type' => 'academic', 'description' => 'Seguimiento'],
            'alerts' => ['type' => 'performance', 'message' => 'Seguimiento', 'resolved' => true],
            'promotion_decisions' => ['academic_year_id' => $yearId, 'status' => 'promoted', 'source' => 'legacy', 'placement_status' => null],
            'activity_scores' => $this->activityScore($sectionId, $subjectId, $periodId),
        };
        $recordId = DB::table($table)->insertGetId(['student_id' => $student->id, ...$fields]);

        $this->deleteJson("/api/admin/students/{$student->id}", ['confirmation' => $student->enrollment_no])
            ->assertUnprocessable()->assertJsonValidationErrors('student');
        $this->assertDatabaseHas('students', ['id' => $student->id, 'active' => false, 'section_id' => null]);
        $this->assertDatabaseHas($table, ['id' => $recordId, 'student_id' => $student->id, ...$fields]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function historyTables(): array
    {
        return array_combine($tables = [
            'student_enrollments', 'activity_scores', 'period_grades', 'final_grades',
            'attendances', 'observations', 'alerts', 'promotion_decisions',
        ], array_map(fn ($table) => [$table], $tables));
    }

    public function test_assigned_students_cannot_be_deleted_and_deactivation_preserves_enrollments(): void
    {
        $this->admin();
        [$yearId, $sectionId] = $this->catalog();
        $student = $this->student(['section_id' => $sectionId, 'academic_year_id' => $yearId]);
        $this->deleteJson("/api/admin/students/{$student->id}", ['confirmation' => $student->enrollment_no])
            ->assertUnprocessable()->assertJsonValidationErrors('student');
        $enrollmentId = DB::table('student_enrollments')->insertGetId([
            'student_id' => $student->id, 'section_id' => $sectionId, 'status' => 'active', 'enrolled_at' => '2026-09-01',
        ]);
        $url = "/api/admin/students/{$student->id}/deactivate";
        $this->postJson($url, ['reason' => ' '])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($url, ['reason' => 'Traslado a otro centro', 'date' => '2026-09-02'])->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'active' => false, 'name' => 'Ana']);
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollmentId, 'status' => 'withdrawn', 'end_reason' => 'Traslado a otro centro']);
    }

    public function test_only_active_administrators_can_edit_delete_or_deactivate_students(): void
    {
        $student = $this->student();
        $url = "/api/admin/students/{$student->id}";
        $this->patchJson($url, ['name' => 'Intruso'])->assertUnauthorized();
        $this->deleteJson($url, ['confirmation' => $student->enrollment_no])->assertUnauthorized();
        $this->postJson("{$url}/deactivate", ['reason' => 'Intruso'])->assertUnauthorized();
        foreach ([['teacher', true], ['coordinator', true], ['admin', false]] as [$role, $active]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'active' => $active]));
            $this->patchJson($url, ['name' => 'Intruso'])->assertForbidden();
            $this->deleteJson($url, ['confirmation' => $student->enrollment_no])->assertForbidden();
            $this->postJson("{$url}/deactivate", ['reason' => 'Intruso'])->assertForbidden();
        }
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Ana', 'active' => true]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    private function student(array $attributes = []): Student
    {
        return Student::create([...[
            'name' => 'Ana', 'last_name' => 'Pérez', 'enrollment_no' => 'CRUD-001', 'active' => true,
        ], ...$attributes]);
    }

    private function catalog(): array
    {
        $yearId = DB::table('academic_years')->insertGetId(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true]);
        $gradeId = DB::table('grades')->insertGetId(['name' => '1RO SECUNDARIA', 'level' => 'Secundaria', 'sort_order' => 1]);
        $sectionId = DB::table('sections')->insertGetId(['grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina']);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Lengua', 'code' => 'LEN']);
        $periodId = DB::table('periods')->insertGetId(['academic_year_id' => $yearId, 'number' => 1, 'name' => 'Primer período', 'months' => 'Sep-Nov', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30', 'status' => 'open']);
        return [$yearId, $sectionId, $subjectId, $periodId];
    }

    private function activityScore(int $sectionId, int $subjectId, int $periodId): array
    {
        $offeringId = DB::table('course_offerings')->insertGetId(['section_id' => $sectionId, 'subject_id' => $subjectId]);
        $activityId = DB::table('course_activities')->insertGetId(['course_offering_id' => $offeringId, 'period_id' => $periodId, 'name' => 'Tarea']);
        $competencyId = DB::table('competencies')->insertGetId(['code' => 'C1', 'name' => 'Comunicativa']);
        return ['activity_id' => $activityId, 'subject_id' => $subjectId, 'period_id' => $periodId, 'competency_id' => $competencyId, 'score' => 85];
    }
}
