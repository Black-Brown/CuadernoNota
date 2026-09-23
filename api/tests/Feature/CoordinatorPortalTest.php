<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoordinatorPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_is_scoped_and_global_operations_remain_forbidden(): void
    {
        extract($this->context());
        DB::table('coordinator_sections')->insert(['user_id' => $coordinator->id, 'section_id' => $sections[0]]);
        $student = DB::table('students')->where('section_id', $sections[0])->value('id');
        $other = DB::table('students')->where('section_id', $sections[1])->value('id');
        DB::table('period_grades')->update(['status' => 'official']);
        DB::table('attendances')->insert(['student_id' => $student, 'section_id' => $sections[0], 'subject_id' => $subject, 'user_id' => $coordinator->id, 'date' => '2026-09-01', 'code' => 'T']);
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinador/catalog')->assertOk()->assertJsonCount(1, 'sections')->assertJsonCount(1, 'subjects');
        $this->getJson('/api/coordinador/students')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $student);
        $this->getJson('/api/coordinador/assignments')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/coordinador/student-placements')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/coordinador/promotions/{$sections[0]}")->assertOk();
        $this->getJson("/api/coordinador/promotions/{$sections[1]}")->assertForbidden();
        $this->getJson("/api/coordinador/reports/{$sections[0]}")->assertOk()->assertJsonCount(1, 'grades')->assertJsonPath('grades.0.enrollment_no', 'A')->assertJsonPath('attendance.0.code', 'T');
        $this->getJson("/api/coordinador/reports/{$sections[1]}")->assertForbidden();
        $this->getJson("/api/coordinador/reports/{$sections[0]}?period_id=99999")->assertUnprocessable();
        $payload = ['name' => 'Actualizado', 'last_name' => 'A', 'enrollment_no' => 'A', 'section_id' => $sections[1], 'active' => false];
        $this->patchJson("/api/coordinador/students/{$student}", $payload)->assertOk();
        $this->assertDatabaseHas('students', ['id' => $student, 'name' => 'Actualizado', 'section_id' => $sections[0], 'active' => true]);
        $this->patchJson("/api/coordinador/students/{$other}", $payload)->assertForbidden();
        $this->deleteJson("/api/admin/students/{$student}")->assertForbidden();
        $this->deleteJson("/api/coordinador/students/{$student}")->assertStatus(405);
        $this->postJson('/api/admin/backups')->assertForbidden();
        $this->postJson('/api/admin/system/reset-data')->assertForbidden();
        DB::table('coordinator_sections')->where('user_id', $coordinator->id)->delete();
        $this->getJson('/api/coordinador/students')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/coordinador/reports/{$sections[0]}")->assertForbidden();
    }

    public function test_student_workspace_is_scoped_to_assignment_student_and_period(): void
    {
        extract($this->context());
        DB::table('coordinator_sections')->insert(['user_id' => $coordinator->id, 'section_id' => $sections[0]]);
        $student = DB::table('students')->where('section_id', $sections[0])->value('id');
        $otherStudent = DB::table('students')->where('section_id', $sections[1])->value('id');
        $year = DB::table('sections')->where('id', $sections[0])->value('academic_year_id');
        $otherPeriod = DB::table('periods')->insertGetId(['academic_year_id' => $year, 'number' => 2, 'name' => 'Segundo', 'months' => 'NOV-ENE', 'start_date' => '2026-11-01', 'end_date' => '2027-01-31', 'status' => 'open']);
        foreach ([['2026-09-01', 'T'], ['2026-09-02', 'P'], ['2026-09-03', 'A'], ['2026-11-01', 'A']] as [$date, $code]) {
            DB::table('attendances')->insert(['section_id' => $sections[0], 'student_id' => $student, 'subject_id' => $subject, 'user_id' => $coordinator->id, 'date' => $date, 'code' => $code]);
        }
        foreach ([$period, $otherPeriod] as $p) {
            DB::table('observations')->insert(['section_id' => $sections[0], 'student_id' => $student, 'subject_id' => $subject, 'period_id' => $p, 'user_id' => $coordinator->id, 'date' => '2026-09-01', 'type' => 'academic', 'description' => 'Seguimiento']);
        }
        DB::table('period_grades')->where('student_id', $student)->update(['period_score' => 60]);
        Sanctum::actingAs($coordinator);
        $this->getJson("/api/coordinador/sections/{$sections[0]}/students?period_id={$period}")->assertOk()
            ->assertJsonCount(1, 'students')->assertJsonPath('students.0.at_risk', true)->assertJsonPath('students.0.average', 60);
        $this->getJson("/api/coordinador/sections/{$sections[0]}/students/{$student}?period_id={$period}")->assertOk()
            ->assertJsonCount(3, 'attendance')->assertJsonCount(1, 'observations')->assertJsonCount(1, 'grades')->assertJsonPath('summary.attendance_percentage', 66.7);
        $this->getJson("/api/coordinador/sections/{$sections[0]}/students/{$otherStudent}?period_id={$period}")->assertNotFound();
        $this->getJson("/api/coordinador/sections/{$sections[1]}/students")->assertForbidden();
        $this->getJson("/api/coordinador/sections/{$sections[0]}/students?period_id=99999")->assertUnprocessable();
        DB::table('students')->where('id', $student)->update(['active' => false]);
        $this->getJson("/api/coordinador/sections/{$sections[0]}/students/{$student}")->assertNotFound();
        $this->getJson("/api/coordinador/sections/{$sections[0]}/students")->assertOk()->assertJsonCount(0, 'students');
    }

    private function context(): array
    {
        $coordinator = User::factory()->create(['role' => 'coordinator', 'active' => true]);
        $year = DB::table('academic_years')->insertGetId(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true]);
        $period = DB::table('periods')->insertGetId(['academic_year_id' => $year, 'number' => 1, 'name' => 'Primer período', 'months' => 'AGO-OCT', 'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'status' => 'open']);
        $grade = DB::table('grades')->insertGetId(['name' => '1RO SECUNDARIA', 'level' => 'Secundaria', 'sort_order' => 1]);
        $subject = DB::table('subjects')->insertGetId(['name' => 'Lengua', 'code' => 'LEN', 'active' => true]);
        $sections = [];
        foreach (['A', 'B'] as $name) {
            $section = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => $name, 'shift' => 'Matutina']);
            $sections[] = $section;
            DB::table('course_offerings')->insert(['section_id' => $section, 'subject_id' => $subject, 'active' => true]);
            $student = DB::table('students')->insertGetId(['name' => 'Estudiante', 'last_name' => $name, 'enrollment_no' => $name, 'section_id' => $section, 'academic_year_id' => $year, 'active' => true]);
            DB::table('period_grades')->insert(['student_id' => $student, 'section_id' => $section, 'subject_id' => $subject, 'period_id' => $period, 'c1_score' => 80, 'c2_score' => 80, 'c3_score' => 80, 'period_score' => 80, 'status' => 'in_review']);
        }
        return compact('coordinator', 'sections', 'subject', 'period');
    }

    public function test_assignments_limit_dashboard_listing_detail_and_decisions(): void
    {
        extract($this->context());
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $this->putJson("/api/admin/users/{$coordinator->id}/coordinator-sections", ['section_ids' => [$sections[0]]])->assertOk();
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinador/dashboard')->assertOk()->assertJsonPath('counts.sections', 1)->assertJsonPath('counts.students', 1)->assertJsonPath('counts.pending_reviews', 1);
        $this->getJson('/api/coordinador/grade-reviews')->assertOk()->assertJsonCount(1)->assertJsonPath('0.section_id', $sections[0]);
        $this->getJson("/api/coordinador/grade-reviews/{$sections[1]}/{$subject}/{$period}")->assertForbidden();
        $payload = ['section_id' => $sections[1], 'subject_id' => $subject, 'period_id' => $period, 'action' => 'approved'];
        $this->postJson('/api/coordinador/grade-reviews/decision', $payload)->assertForbidden();
        $payload['section_id'] = $sections[0];
        $this->postJson('/api/coordinador/grade-reviews/decision', $payload)->assertOk();
        $this->assertDatabaseHas('period_grades', ['section_id' => $sections[0], 'status' => 'official', 'approved_by' => $coordinator->id]);
        $this->assertDatabaseHas('period_grades', ['section_id' => $sections[1], 'status' => 'in_review']);
        $this->postJson('/api/coordinador/grade-reviews/decision', [...$payload, 'action' => 'reopened', 'comment' => 'Abrir'])->assertUnprocessable();
        $this->getJson('/api/admin/students')->assertForbidden();
        $this->postJson('/api/docente/grades/activity-score', [])->assertForbidden();
    }

    public function test_no_assignment_and_revocation_grant_no_access(): void
    {
        extract($this->context());
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinador/grade-reviews')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/coordinador/dashboard')->assertOk()->assertJsonPath('counts.sections', 0);
        $this->getJson("/api/coordinador/grade-reviews/{$sections[0]}/{$subject}/{$period}")->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/users/{$coordinator->id}/coordinator-sections", ['section_ids' => [$sections[0]]])->assertOk();
        $this->putJson("/api/admin/users/{$coordinator->id}/coordinator-sections", ['section_ids' => []])->assertOk();
        Sanctum::actingAs($coordinator);
        $this->getJson('/api/coordinador/grade-reviews')->assertOk()->assertJsonCount(0);
        $coordinator->update(['active' => false]);
        $this->getJson('/api/coordinador/dashboard')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher', 'active' => true]));
        $this->getJson('/api/coordinador/dashboard')->assertForbidden();
    }

    public function test_rejection_requires_comment_and_records_reviewer(): void
    {
        extract($this->context());
        DB::table('coordinator_sections')->insert(['user_id' => $coordinator->id, 'section_id' => $sections[0]]);
        Sanctum::actingAs($coordinator);
        $payload = ['section_id' => $sections[0], 'subject_id' => $subject, 'period_id' => $period, 'action' => 'rejected'];
        $this->postJson('/api/coordinador/grade-reviews/decision', $payload)->assertUnprocessable();
        $this->postJson('/api/coordinador/grade-reviews/decision', [...$payload, 'comment' => 'Verificar competencias.'])->assertOk();
        $this->assertDatabaseHas('grade_review_actions', ['performed_by' => $coordinator->id, 'action' => 'rejected', 'comment' => 'Verificar competencias.']);
        $this->assertDatabaseHas('period_grades', ['section_id' => $sections[0], 'status' => 'draft']);
    }
}
