<?php

namespace Tests\Feature;

use App\Application\Grade\RegisterRecovery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class TeacherHighRiskGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_cannot_read_or_write_attendance_outside_assigned_section(): void
    {
        $context = $this->academicContext();
        $outsider = User::factory()->create(['role' => 'teacher', 'active' => true]);
        Sanctum::actingAs($outsider);

        $this->getJson("/api/docente/attendance/{$context['section_id']}/2026-09-01")
            ->assertForbidden();

        $this->postJson('/api/docente/attendance', [
            'student_id' => $context['student_id'],
            'date' => '2026-09-01',
            'status' => 'present',
        ])->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_attendance_date_must_belong_to_the_sections_academic_year(): void
    {
        $context = $this->academicContext();
        Sanctum::actingAs($context['teacher']);

        $this->postJson('/api/docente/attendance', [
            'student_id' => $context['student_id'],
            'date' => '2028-01-15',
            'status' => 'present',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_attendance_list_returns_students_without_existing_daily_records(): void
    {
        $context = $this->academicContext();
        Sanctum::actingAs($context['teacher']);

        $this->getJson("/api/docente/attendance/{$context['section_id']}/2026-09-01")
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.student_id', $context['student_id'])
            ->assertJsonPath('records.0.attendance_id', null)
            ->assertJsonPath('records.0.status', null);
    }

    public function test_future_open_period_is_upcoming_and_blocks_teacher_operations_until_start_date(): void
    {
        $context = $this->academicContext();
        DB::table('periods')->where('academic_year_id', $context['year_id'])->update([
            'start_date' => '2026-10-01', 'end_date' => '2026-11-30', 'status' => 'open',
        ]);
        Sanctum::actingAs($context['teacher']);

        $this->getJson('/api/docente/current-period')->assertOk()->assertJsonPath('period', null);
        $this->getJson('/api/docente/periods')->assertOk()->assertJsonPath('periods.0.status', 'upcoming');
        $this->postJson('/api/docente/attendance', [
            'student_id' => $context['student_id'], 'date' => '2026-10-01', 'status' => 'present',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_teacher_can_register_and_read_a_late_attendance_without_treating_it_as_absent(): void
    {
        $context = $this->academicContext();
        Sanctum::actingAs($context['teacher']);

        $this->postJson('/api/docente/attendance', [
            'student_id' => $context['student_id'],
            'date' => '2026-09-01',
            'status' => 'late',
        ])->assertCreated()->assertJsonPath('alerts.consecutive', false);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $context['student_id'], 'code' => 'T',
        ]);
        $this->getJson("/api/docente/attendance/{$context['section_id']}/2026-09-01")
            ->assertOk()->assertJsonPath('records.0.status', 'late');
    }

    public function test_teacher_cannot_replace_or_excuse_attendance_owned_by_another_teacher(): void
    {
        $context = $this->academicContext();
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        $this->assignTeacher($otherTeacher->id, $context['offering_id']);

        $attendanceId = DB::table('attendances')->insertGetId([
            'student_id' => $context['student_id'],
            'section_id' => $context['section_id'],
            'user_id' => $otherTeacher->id,
            'date' => '2026-09-01',
            'code' => 'A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($context['teacher']);

        $this->postJson('/api/docente/attendance', [
            'student_id' => $context['student_id'],
            'date' => '2026-09-01',
            'status' => 'present',
        ])->assertForbidden();
        $this->patchJson("/api/docente/attendance/{$attendanceId}/excuse")->assertForbidden();

        $this->assertDatabaseHas('attendances', ['id' => $attendanceId, 'code' => 'A', 'user_id' => $otherTeacher->id]);
    }

    public function test_rp_does_not_persist_when_four_periods_are_not_available(): void
    {
        $context = $this->academicContext(periodCount: 1);
        $periodId = $context['period_ids'][0];
        DB::table('period_grades')->insert([
            'student_id' => $context['student_id'],
            'section_id' => $context['section_id'],
            'subject_id' => $context['subject_id'],
            'period_id' => $periodId,
            'c1_score' => 60,
            'c2_score' => 60,
            'c3_score' => 60,
            'period_score' => 60,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(RegisterRecovery::class)->execute(
                $context['student_id'], $context['subject_id'], $periodId, $context['year_id'], 75
            );
            $this->fail('La recuperación debía fallar al no existir cuatro períodos.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('period_grades', [
                'student_id' => $context['student_id'],
                'period_id' => $periodId,
                'rp_score' => null,
            ]);
        }
    }

    public function test_rp_rejects_official_grades_and_final_recovery_requires_a_failed_final_grade(): void
    {
        $context = $this->academicContext();
        $periodId = $context['period_ids'][0];
        DB::table('period_grades')->insert([
            'student_id' => $context['student_id'], 'section_id' => $context['section_id'],
            'subject_id' => $context['subject_id'], 'period_id' => $periodId,
            'c1_score' => 60, 'c2_score' => 60, 'c3_score' => 60, 'period_score' => 60,
            'status' => 'official', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($context['teacher']);

        $this->postJson('/api/docente/grades/recovery', [
            'type' => 'rp', 'student_id' => $context['student_id'], 'subject_id' => $context['subject_id'],
            'academic_year_id' => $context['year_id'], 'period_id' => $periodId, 'score' => 75,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('period_grades', ['period_id' => $periodId, 'rp_score' => null]);

        DB::table('periods')->where('academic_year_id', $context['year_id'])->update(['status' => 'closed']);
        $this->postJson('/api/docente/grades/recovery', [
            'type' => 'final', 'student_id' => $context['student_id'], 'subject_id' => $context['subject_id'],
            'academic_year_id' => $context['year_id'], 'score' => 75,
        ])->assertUnprocessable();
    }

    public function test_teacher_cannot_edit_or_delete_another_teachers_observation(): void
    {
        $context = $this->academicContext();
        $author = User::factory()->create(['role' => 'teacher', 'active' => true]);
        $this->assignTeacher($author->id, $context['offering_id']);
        $observationId = DB::table('observations')->insertGetId([
            'student_id' => $context['student_id'], 'user_id' => $author->id,
            'section_id' => $context['section_id'], 'subject_id' => $context['subject_id'],
            'period_id' => $context['period_ids'][0], 'date' => '2026-09-01',
            'type' => 'academic', 'description' => 'Observación original.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($context['teacher']);

        $this->patchJson("/api/docente/observations/{$observationId}", [
            'date' => '2026-09-02', 'type' => 'academic', 'description' => 'Alterada.',
        ])->assertForbidden();
        $this->deleteJson("/api/docente/observations/{$observationId}")->assertForbidden();

        $this->assertDatabaseHas('observations', ['id' => $observationId, 'description' => 'Observación original.']);
    }

    public function test_subject_dashboard_rejects_unassigned_courses_and_limits_grades_to_selected_period(): void
    {
        $context = $this->academicContext();
        DB::table('period_grades')->insert([
            'student_id' => $context['student_id'], 'section_id' => $context['section_id'],
            'subject_id' => $context['subject_id'], 'period_id' => $context['period_ids'][0],
            'period_score' => 80, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('period_grades')->insert([
            'student_id' => $context['student_id'], 'section_id' => $context['section_id'],
            'subject_id' => $context['subject_id'], 'period_id' => $context['period_ids'][1],
            'period_score' => 20, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($context['teacher']);

        $this->getJson("/api/docente/dashboard?period_id={$context['period_ids'][0]}")
            ->assertOk()->assertJsonPath('avg_grade', 80);
        $this->getJson("/api/docente/dashboard/{$context['section_id']}/{$context['subject_id']}?period_id={$context['period_ids'][0]}")
            ->assertOk()->assertJsonPath('group_avg', 80);

        $outsider = User::factory()->create(['role' => 'teacher', 'active' => true]);
        Sanctum::actingAs($outsider);
        $this->getJson("/api/docente/dashboard/{$context['section_id']}/{$context['subject_id']}?period_id={$context['period_ids'][0]}")
            ->assertForbidden();
    }

    public function test_risk_rejects_a_period_from_another_year_and_ignores_historical_attendance(): void
    {
        $context = $this->academicContext();
        DB::table('periods')->where('id', $context['period_ids'][0])->update([
            'start_date' => '2026-09-01', 'end_date' => '2026-10-31',
        ]);
        DB::table('attendances')->insert([
            'student_id' => $context['student_id'], 'section_id' => $context['section_id'],
            'user_id' => $context['teacher']->id, 'date' => '2026-08-15', 'code' => 'A',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($context['teacher']);

        $this->getJson("/api/docente/risk/{$context['section_id']}/{$context['subject_id']}?period_id={$context['period_ids'][0]}")
            ->assertOk()->assertJsonCount(0, 'students');

        $otherYear = DB::table('academic_years')->insertGetId([
            'name' => '2027-2028', 'start_date' => '2027-08-01', 'end_date' => '2028-06-30',
            'active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherPeriod = DB::table('periods')->insertGetId([
            'academic_year_id' => $otherYear, 'number' => 1, 'name' => 'P1 2027', 'months' => '',
            'start_date' => '2027-08-01', 'end_date' => '2027-10-31', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/api/docente/risk/{$context['section_id']}/{$context['subject_id']}?period_id={$otherPeriod}")
            ->assertForbidden();
    }

    private function academicContext(int $periodCount = 4): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $periodIds = [];
        foreach (range(1, $periodCount) as $number) {
            $periodIds[] = DB::table('periods')->insertGetId([
                'academic_year_id' => $yearId, 'number' => $number, 'name' => "P{$number}", 'months' => '',
                'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'status' => 'open',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $gradeId = DB::table('grades')->insertGetId([
            'name' => '1ro Secundaria', 'level' => 'Secundaria', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Robótica', 'code' => 'ROB', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $offeringId = DB::table('course_offerings')->insertGetId([
            'section_id' => $sectionId, 'subject_id' => $subjectId, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assignTeacher($teacher->id, $offeringId);
        $studentId = DB::table('students')->insertGetId([
            'name' => 'Ana', 'last_name' => 'Pérez', 'enrollment_no' => '2026-0001',
            'section_id' => $sectionId, 'academic_year_id' => $yearId, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('teacher', 'yearId', 'periodIds', 'gradeId', 'sectionId', 'subjectId', 'offeringId', 'studentId') + [
            'year_id' => $yearId, 'period_ids' => $periodIds, 'section_id' => $sectionId,
            'subject_id' => $subjectId, 'offering_id' => $offeringId, 'student_id' => $studentId,
        ];
    }

    private function assignTeacher(int $teacherId, int $offeringId): void
    {
        DB::table('teacher_assignments')->insert([
            'teacher_id' => $teacherId, 'course_offering_id' => $offeringId,
            'assigned_at' => now(), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
