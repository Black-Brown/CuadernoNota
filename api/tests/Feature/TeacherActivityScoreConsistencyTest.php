<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherActivityScoreConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-05 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_clearing_the_last_score_of_a_competency_removes_the_stale_period_summary(): void
    {
        $context = $this->academicContext();
        Sanctum::actingAs($context['teacher']);

        foreach ([1 => 80, 2 => 90, 3 => 100] as $competencyId => $score) {
            $this->postJson('/api/docente/grades/activity-score', $this->scorePayload(
                $context,
                $competencyId,
                $score,
            ))->assertCreated();
        }

        $this->assertDatabaseHas('period_grades', [
            'student_id' => $context['student_id'],
            'subject_id' => $context['subject_id'],
            'period_id' => $context['period_id'],
            'c1_score' => 80,
            'c2_score' => 90,
            'c3_score' => 100,
            'period_score' => 90,
        ]);

        $this->postJson('/api/docente/grades/activity-score', $this->scorePayload(
            $context,
            1,
            null,
        ))
            ->assertCreated()
            ->assertJsonPath('period_grade', null);

        $this->assertDatabaseHas('activity_scores', [
            'activity_id' => $context['activity_id'],
            'student_id' => $context['student_id'],
            'competency_id' => 1,
            'score' => null,
        ]);
        $this->assertDatabaseMissing('period_grades', [
            'student_id' => $context['student_id'],
            'subject_id' => $context['subject_id'],
            'period_id' => $context['period_id'],
        ]);

        $this->getJson(
            "/api/docente/grades/period/{$context['subject_id']}/{$context['period_id']}?section_id={$context['section_id']}"
        )
            ->assertOk()
            ->assertJsonCount(0, 'grades');
    }

    public function test_restoring_the_missing_competency_recreates_the_period_summary(): void
    {
        $context = $this->academicContext();
        Sanctum::actingAs($context['teacher']);

        foreach ([1 => 80, 2 => 90, 3 => 100] as $competencyId => $score) {
            $this->postJson('/api/docente/grades/activity-score', $this->scorePayload(
                $context,
                $competencyId,
                $score,
            ))->assertCreated();
        }

        $this->postJson('/api/docente/grades/activity-score', $this->scorePayload($context, 1, null))
            ->assertCreated();
        $this->assertDatabaseMissing('period_grades', [
            'student_id' => $context['student_id'],
            'subject_id' => $context['subject_id'],
            'period_id' => $context['period_id'],
        ]);

        $this->postJson('/api/docente/grades/activity-score', $this->scorePayload($context, 1, 70))
            ->assertCreated()
            ->assertJsonPath('period_grade.c1_score', 70)
            ->assertJsonPath('period_grade.c2_score', 90)
            ->assertJsonPath('period_grade.c3_score', 100)
            ->assertJsonPath('period_grade.period_score', 86.67);

        $this->assertDatabaseHas('period_grades', [
            'student_id' => $context['student_id'],
            'subject_id' => $context['subject_id'],
            'period_id' => $context['period_id'],
            'period_score' => 86.67,
            'status' => 'draft',
        ]);
    }

    private function scorePayload(array $context, int $competencyId, ?int $score): array
    {
        return [
            'activity_id' => $context['activity_id'],
            'student_id' => $context['student_id'],
            'competency_id' => $competencyId,
            'period_id' => $context['period_id'],
            'subject_id' => $context['subject_id'],
            'score' => $score,
        ];
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
            'end_date' => '2026-11-30',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gradeId = DB::table('grades')->insertGetId([
            'name' => '1RO SECUNDARIA',
            'level' => 'Secundaria',
            'sort_order' => 1,
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
            'name' => 'Robótica y Programación',
            'code' => 'ROB',
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
            'assigned_at' => now(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = DB::table('students')->insertGetId([
            'name' => 'Ana',
            'last_name' => 'Pérez',
            'enrollment_no' => '2026-0001',
            'section_id' => $sectionId,
            'academic_year_id' => $yearId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activityId = DB::table('course_activities')->insertGetId([
            'course_offering_id' => $offeringId,
            'period_id' => $periodId,
            'created_by' => $teacher->id,
            'name' => 'Proyecto integrador',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact(
            'teacher',
            'yearId',
            'periodId',
            'gradeId',
            'sectionId',
            'subjectId',
            'offeringId',
            'studentId',
            'activityId',
        ) + [
            'year_id' => $yearId,
            'period_id' => $periodId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'offering_id' => $offeringId,
            'student_id' => $studentId,
            'activity_id' => $activityId,
        ];
    }
}
