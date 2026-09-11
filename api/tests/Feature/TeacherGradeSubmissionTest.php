<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherGradeSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_submission_is_blocked_until_period_has_ended(): void
    {
        $context = $this->academicContext();
        $this->completeGrade($context['student_a'], $context);
        $this->completeGrade($context['student_b'], $context);
        Sanctum::actingAs($context['teacher']);

        Carbon::setTestNow('2026-09-04 12:00:00');
        $this->postJson('/api/docente/grades/submit', $this->submitPayload($context))
            ->assertStatus(423)
            ->assertJsonPath('message', 'El envío a revisión se habilita cuando finalice el período.');

        $this->assertDatabaseHas('period_grades', [
            'student_id' => $context['student_a'],
            'status' => 'draft',
        ]);

        Carbon::setTestNow('2026-09-05 12:00:00');
        $this->postJson('/api/docente/grades/submit', $this->submitPayload($context))
            ->assertOk()
            ->assertJsonPath('submission.submitted_count', 2)
            ->assertJsonPath('submission.ready', false);

        $this->assertDatabaseCount('period_grades', 2);
        $this->assertDatabaseHas('period_grades', [
            'student_id' => $context['student_a'],
            'status' => 'in_review',
        ]);
        $this->assertDatabaseHas('period_grades', [
            'student_id' => $context['student_b'],
            'status' => 'in_review',
        ]);
    }

    public function test_readiness_lists_pending_active_students_and_prevents_partial_submission(): void
    {
        $context = $this->academicContext();
        $this->completeGrade($context['student_a'], $context);
        $this->student($context['section_id'], $context['year_id'], 'INACTIVO', false);
        Sanctum::actingAs($context['teacher']);
        Carbon::setTestNow('2026-09-05 12:00:00');

        $this->getJson(sprintf(
            '/api/docente/grades/period/%d/%d?section_id=%d',
            $context['subject_id'],
            $context['period_id'],
            $context['section_id'],
        ))
            ->assertOk()
            ->assertJsonPath('submission.total_students', 2)
            ->assertJsonPath('submission.complete_students', 1)
            ->assertJsonPath('submission.pending_students', 1)
            ->assertJsonPath('submission.ready', false)
            ->assertJsonPath('submission.pending.0.student_name', 'B, Luis');

        $this->postJson('/api/docente/grades/submit', $this->submitPayload($context))
            ->assertStatus(422)
            ->assertJsonPath('submission.pending_students', 1);

        $this->assertDatabaseHas('period_grades', [
            'student_id' => $context['student_a'],
            'status' => 'draft',
        ]);
    }

    private function submitPayload(array $context): array
    {
        return [
            'subject_id' => $context['subject_id'],
            'section_id' => $context['section_id'],
            'period_id' => $context['period_id'],
        ];
    }

    private function completeGrade(int $studentId, array $context): void
    {
        DB::table('period_grades')->insert([
            'student_id' => $studentId,
            'section_id' => $context['section_id'],
            'subject_id' => $context['subject_id'],
            'period_id' => $context['period_id'],
            'c1_score' => 80,
            'c2_score' => 85,
            'c3_score' => 90,
            'period_score' => 85,
            'rp_score' => null,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function academicContext(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $periodId = DB::table('periods')->insertGetId([
            'academic_year_id' => $yearId,
            'number' => 1,
            'name' => 'Primer período',
            'months' => 'SEP-NOV',
            'start_date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = DB::table('grades')->insertGetId([
            'name' => '1RO SECUNDARIA',
            'level' => 'Secundaria',
            'sort_order' => 1,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId,
            'academic_year_id' => $yearId,
            'name' => 'A',
            'shift' => 'Matutina',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Lengua Española',
            'code' => 'LEN',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $offeringId = DB::table('course_offerings')->insertGetId([
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('teacher_assignments')->insert([
            'teacher_id' => $teacher->id,
            'course_offering_id' => $offeringId,
            'assigned_by' => null,
            'assigned_at' => now(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentA = $this->student($sectionId, $yearId, 'A', true, 'Ana');
        $studentB = $this->student($sectionId, $yearId, 'B', true, 'Luis');

        return compact('teacher', 'yearId', 'periodId', 'sectionId', 'subjectId', 'studentA', 'studentB') + [
            'year_id' => $yearId,
            'period_id' => $periodId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'student_a' => $studentA,
            'student_b' => $studentB,
        ];
    }

    private function student(int $sectionId, int $yearId, string $lastName, bool $active, string $name = 'Estudiante'): int
    {
        return DB::table('students')->insertGetId([
            'name' => $name,
            'last_name' => $lastName,
            'enrollment_no' => "2026-{$lastName}",
            'section_id' => $sectionId,
            'academic_year_id' => $yearId,
            'active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
