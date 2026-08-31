<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStudentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enroll_approve_and_promote_a_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        $yearId = $this->year('2026-2027', '2026-08-01', '2027-06-30');
        $nextYearId = $this->year('2027-2028', '2027-08-01', '2028-06-30');
        $gradeId = DB::table('grades')->insertGetId(['name' => '4to Primaria', 'level' => 'Primaria', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()]);
        $nextGradeId = DB::table('grades')->insertGetId(['name' => '5to Primaria', 'level' => 'Primaria', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = $this->section($gradeId, $yearId);
        $destinationId = $this->section($nextGradeId, $nextYearId);
        $periodId = DB::table('periods')->insertGetId([
            'academic_year_id' => $yearId, 'number' => 1, 'name' => 'Primer período', 'months' => 'Ago-Oct',
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Robótica', 'code' => 'ROB', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('grade_subjects')->insert(['grade_id' => $gradeId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);

        $studentId = $this->postJson('/api/admin/students', [
            'name' => 'Ana', 'last_name' => 'Pérez', 'enrollment_no' => '2026-001',
            'section_id' => $sectionId, 'enrolled_at' => '2026-08-01',
        ])->assertCreated()->json('id');
        $enrollmentId = DB::table('student_enrollments')->where('student_id', $studentId)->value('id');

        DB::table('period_grades')->insert([
            'student_id' => $studentId, 'subject_id' => $subjectId, 'period_id' => $periodId,
            'c1_score' => 85, 'c2_score' => 90, 'c3_score' => 80, 'period_score' => 85,
            'status' => 'in_review', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/admin/grade-reviews/decision', [
            'section_id' => $sectionId, 'subject_id' => $subjectId,
            'period_id' => $periodId, 'action' => 'approved',
        ])->assertOk()->assertJsonPath('updated_grades', 1);
        $this->assertDatabaseHas('period_grades', ['student_id' => $studentId, 'status' => 'official', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('grade_review_actions', ['section_id' => $sectionId, 'action' => 'approved', 'performed_by' => $admin->id]);

        DB::table('final_grades')->insert([
            'student_id' => $studentId, 'subject_id' => $subjectId, 'academic_year_id' => $yearId,
            'cf' => 85, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/api/admin/promotions/candidates?academic_year_id={$yearId}")
            ->assertOk()->assertJsonFragment(['student_id' => $studentId, 'eligible' => true]);

        $this->postJson("/api/admin/promotions/{$enrollmentId}/decision", [
            'status' => 'promoted', 'destination_section_id' => $destinationId,
        ])->assertOk()->assertJsonPath('status', 'promoted');
        $this->assertDatabaseHas('students', ['id' => $studentId, 'section_id' => $destinationId, 'academic_year_id' => $nextYearId]);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $studentId, 'section_id' => $destinationId, 'status' => 'active']);
    }

    private function year(string $name, string $start, string $end): int
    {
        return DB::table('academic_years')->insertGetId(['name' => $name, 'start_date' => $start, 'end_date' => $end, 'active' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function section(int $gradeId, int $yearId): int
    {
        return DB::table('sections')->insertGetId(['grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina', 'created_at' => now(), 'updated_at' => now()]);
    }
}
