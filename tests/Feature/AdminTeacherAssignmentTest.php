<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_register_and_assign_a_teacher_to_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        $teacherId = $this->postJson('/api/admin/teachers', [
            'name' => 'Profesora de Robótica',
            'email' => 'roboticaprimaria@happylearningschool.net',
        ])->assertCreated()
            ->assertJsonPath('role', 'teacher')
            ->json('id');

        $this->assertNotNull($teacherId);

        $yearId = DB::table('academic_years')->insertGetId([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-06-30',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (range(1, 4) as $number) {
            DB::table('periods')->insert([
                'academic_year_id' => $yearId,
                'number' => $number,
                'name' => "Período {$number}",
                'months' => '',
                'start_date' => '2026-08-01',
                'end_date' => '2027-06-30',
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $gradeId = DB::table('grades')->insertGetId([
            'name' => '4to Primaria',
            'level' => 'Primaria',
            'sort_order' => 4,
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
            'name' => 'Robótica',
            'code' => 'ROB',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('grade_subjects')->insert([
            'grade_id' => $gradeId,
            'subject_id' => $subjectId,
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

        $this->postJson('/api/admin/teacher-assignments', [
            'teacher_id' => $teacherId,
            'course_offering_id' => $offeringId,
        ])->assertCreated()
            ->assertJsonPath('teacher_id', $teacherId)
            ->assertJsonPath('assigned_by', $admin->id)
            ->assertJsonPath('active', true);

        $this->assertDatabaseHas('teacher_assignments', [
            'teacher_id' => $teacherId,
            'course_offering_id' => $offeringId,
            'assigned_by' => $admin->id,
            'active' => true,
        ]);

        $this->assertSame(24, DB::table('activities')
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('is_base', true)
            ->count());

        $this->assertSame(6, DB::table('activities')
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('period_id', DB::table('periods')->where('academic_year_id', $yearId)->value('id'))
            ->where('is_base', true)
            ->count());

        $this->assertDatabaseHas('teacher_sections', [
            'user_id' => $teacherId,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
        ]);
    }

    public function test_teacher_cannot_use_admin_assignment_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher', 'active' => true]));

        $this->getJson('/api/admin/teacher-assignments')->assertForbidden();
    }
}
