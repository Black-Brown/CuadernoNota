<?php

namespace Tests\Feature;

use App\Infrastructure\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStudentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspaces_are_isolated_by_academic_year_and_section_and_include_pending_students(): void
    {
        $this->admin();
        [$currentYearId, $sectionA, $sectionB] = $this->catalog('2026-2027', true);
        [$oldYearId, $oldSection] = $this->catalog('2025-2026', false, 2025);

        $this->student('CUR-A-1', ['section_id' => $sectionA, 'academic_year_id' => $currentYearId]);
        $this->student('CUR-A-2', ['section_id' => $sectionA, 'academic_year_id' => $currentYearId, 'active' => false]);
        $this->student('CUR-B-1', ['section_id' => $sectionB, 'academic_year_id' => $currentYearId]);
        $this->student('OLD-1', ['section_id' => $oldSection, 'academic_year_id' => $oldYearId]);
        $this->student('PENDING-1');

        $response = $this->getJson("/api/admin/students/workspaces?academic_year_id={$currentYearId}")
            ->assertOk()
            ->assertJsonPath('academic_year.id', $currentYearId)
            ->assertJsonPath('summary.workspaces', 2)
            ->assertJsonPath('summary.students', 4)
            ->assertJsonPath('summary.active_students', 3)
            ->assertJsonPath('summary.inactive_students', 1)
            ->assertJsonPath('pending.students_count', 1);

        $workspaces = collect($response->json('workspaces'))->keyBy('id');
        $this->assertSame(2, $workspaces[$sectionA]['students_count']);
        $this->assertSame(1, $workspaces[$sectionA]['active_students_count']);
        $this->assertSame(1, $workspaces[$sectionA]['inactive_students_count']);
        $this->assertSame(1, $workspaces[$sectionB]['students_count']);
        $this->assertFalse($workspaces->has($oldSection));

        $this->getJson("/api/admin/students?section_id={$sectionA}&academic_year_id={$currentYearId}&per_page=1000")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/students?pending=1&per_page=1000')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.enrollment_no', 'PENDING-1');
    }

    public function test_reactivation_preserves_history_and_returns_student_to_pending_placement(): void
    {
        $admin = $this->admin();
        [$yearId, $sectionId] = $this->catalog('2026-2027', true);
        $student = $this->student('REACT-1', [
            'section_id' => $sectionId,
            'academic_year_id' => $yearId,
            'active' => false,
            'deactivation_date' => '2026-10-01',
            'deactivation_reason' => 'Traslado temporal',
        ]);
        $enrollmentId = DB::table('student_enrollments')->insertGetId([
            'student_id' => $student->id,
            'section_id' => $sectionId,
            'status' => 'withdrawn',
            'enrolled_at' => '2026-09-01',
            'ended_at' => '2026-10-01',
            'end_reason' => 'Traslado temporal',
        ]);

        $this->postJson("/api/admin/students/{$student->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('student.active', true)
            ->assertJsonPath('student.section_id', null);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'active' => true,
            'section_id' => null,
            'academic_year_id' => null,
            'deactivation_date' => null,
            'deactivation_reason' => null,
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollmentId,
            'status' => 'withdrawn',
            'end_reason' => 'Traslado temporal',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'affected_table' => 'students',
            'record_id' => $student->id,
            'action' => 'post',
        ]);
    }

    public function test_bulk_replace_previews_and_updates_only_selected_students(): void
    {
        $admin = $this->admin();
        $first = $this->student('2025-1SA-0001');
        $second = $this->student('2025-1SA-0002');
        $untouched = $this->student('2025-1SA-0003');
        $payload = [
            'student_ids' => [$first->id, $second->id],
            'search' => '2025-1SA',
            'replace' => '2026-1SA',
        ];

        $this->postJson('/api/admin/students/bulk-replace/preview', $payload)
            ->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.changed', 2)
            ->assertJsonPath('summary.ready', 2)
            ->assertJsonPath('summary.invalid', 0)
            ->assertJsonPath('rows.0.current_enrollment_no', '2025-1SA-0001')
            ->assertJsonPath('rows.0.proposed_enrollment_no', '2026-1SA-0001');
        $this->assertDatabaseHas('students', ['id' => $first->id, 'enrollment_no' => '2025-1SA-0001']);

        $this->postJson('/api/admin/students/bulk-replace', $payload)
            ->assertOk()->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('students', ['id' => $first->id, 'enrollment_no' => '2026-1SA-0001']);
        $this->assertDatabaseHas('students', ['id' => $second->id, 'enrollment_no' => '2026-1SA-0002']);
        $this->assertDatabaseHas('students', ['id' => $untouched->id, 'enrollment_no' => '2025-1SA-0003']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'affected_table' => 'students',
            'record_id' => 0,
            'action' => 'post',
        ]);
    }

    public function test_bulk_replace_rejects_collisions_atomically(): void
    {
        $this->admin();
        $first = $this->student('ERR-001');
        $second = $this->student('ERR-002');
        $this->student('OK-001');
        $payload = [
            'student_ids' => [$first->id, $second->id],
            'search' => 'ERR',
            'replace' => 'OK',
        ];

        $this->postJson('/api/admin/students/bulk-replace/preview', $payload)
            ->assertOk()->assertJsonPath('summary.invalid', 1)
            ->assertJsonPath('rows.0.valid', false);
        $this->postJson('/api/admin/students/bulk-replace', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('students');

        $this->assertDatabaseHas('students', ['id' => $first->id, 'enrollment_no' => 'ERR-001']);
        $this->assertDatabaseHas('students', ['id' => $second->id, 'enrollment_no' => 'ERR-002']);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_bulk_replace_can_remove_the_searched_text(): void
    {
        $this->admin();
        $student = $this->student('TEMP-2025-001');
        $payload = [
            'student_ids' => [$student->id],
            'search' => '-2025',
            'replace' => '',
        ];

        $this->postJson('/api/admin/students/bulk-replace/preview', $payload)
            ->assertOk()->assertJsonPath('rows.0.proposed_enrollment_no', 'TEMP-001');
        $this->postJson('/api/admin/students/bulk-replace', $payload)
            ->assertOk()->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('students', ['id' => $student->id, 'enrollment_no' => 'TEMP-001']);
    }

    public function test_student_workspace_mutations_are_restricted_to_active_administrators(): void
    {
        $student = $this->student('SEC-001', ['active' => false]);
        $payload = ['student_ids' => [$student->id], 'search' => 'SEC', 'replace' => 'NEW'];

        $this->postJson("/api/admin/students/{$student->id}/reactivate")->assertUnauthorized();
        $this->postJson('/api/admin/students/bulk-replace', $payload)->assertUnauthorized();

        foreach ([['teacher', true], ['coordinator', true], ['admin', false]] as [$role, $active]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'active' => $active]));
            $this->postJson("/api/admin/students/{$student->id}/reactivate")->assertForbidden();
            $this->postJson('/api/admin/students/bulk-replace', $payload)->assertForbidden();
        }

        $this->assertDatabaseHas('students', ['id' => $student->id, 'active' => false, 'enrollment_no' => 'SEC-001']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function student(string $enrollmentNo, array $attributes = []): Student
    {
        return Student::create([...[
            'name' => 'Ana',
            'last_name' => $enrollmentNo,
            'enrollment_no' => $enrollmentNo,
            'active' => true,
        ], ...$attributes]);
    }

    private function catalog(string $name, bool $active, int $startYear = 2026): array
    {
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => $name,
            'start_date' => "{$startYear}-08-01",
            'end_date' => ($startYear + 1).'-06-30',
            'active' => $active,
        ]);
        $gradeId = DB::table('grades')->insertGetId([
            'name' => "1RO SECUNDARIA {$startYear}",
            'level' => 'Secundaria',
            'sort_order' => $startYear,
        ]);
        $sectionA = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId,
            'academic_year_id' => $yearId,
            'name' => 'A',
            'shift' => 'Matutina',
        ]);
        $sectionB = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId,
            'academic_year_id' => $yearId,
            'name' => 'B',
            'shift' => 'Matutina',
        ]);

        return [$yearId, $sectionA, $sectionB];
    }
}
