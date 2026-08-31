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

        // Eloquent serializes loaded relationships using snake_case, including
        // assignedBy -> assigned_by (the related object, not only the FK).
        $this->getJson('/api/admin/teacher-assignments')->assertOk()
            ->assertJsonPath('0.teacher.name', 'Profesora de Robótica')
            ->assertJsonPath('0.course_offering.section.grade.name', '4to Primaria')
            ->assertJsonPath('0.course_offering.section.name', 'A')
            ->assertJsonPath('0.course_offering.section.shift', 'Matutina')
            ->assertJsonPath('0.course_offering.subject.name', 'Robótica')
            ->assertJsonPath('0.course_offering.section.academic_year.name', '2026-2027')
            ->assertJsonPath('0.assigned_by.name', $admin->name);

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

    public function test_admin_can_assign_one_teacher_to_many_courses_atomically(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        Sanctum::actingAs($admin);
        $year = DB::table('academic_years')->insertGetId(['name' => '2028-2029', 'start_date' => '2028-08-01', 'end_date' => '2029-06-30']);
        DB::table('periods')->insert(['academic_year_id' => $year, 'number' => 1, 'name' => 'P1', 'months' => 'Ago-Oct', 'start_date' => '2028-08-01', 'end_date' => '2028-10-31']);
        $grade = DB::table('grades')->insertGetId(['name' => '1ro', 'level' => 'Primaria', 'sort_order' => 1]);
        $section = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'A', 'shift' => 'Matutina']);
        $courseIds = collect(['MAT' => 'Matemática', 'ROB' => 'Robótica', 'LEN' => 'Lengua'])->map(function ($name, $code) use ($section) {
            $subject = DB::table('subjects')->insertGetId(['name' => $name, 'code' => $code, 'active' => true]);

            return DB::table('course_offerings')->insertGetId(['section_id' => $section, 'subject_id' => $subject, 'active' => true]);
        })->values()->all();

        $this->postJson('/api/admin/teacher-assignments', [
            'teacher_id' => $teacher->id, 'course_offering_ids' => $courseIds,
        ])->assertCreated()->assertJsonPath('assigned_count', 3)->assertJsonCount(3, 'assignments');
        $this->assertSame(3, DB::table('teacher_assignments')->where('teacher_id', $teacher->id)->where('active', true)->count());
        $this->assertSame(18, DB::table('course_activities')->count());

        $this->postJson('/api/admin/teacher-assignments', [
            'teacher_id' => $teacher->id, 'course_offering_ids' => [$courseIds[0], 999999],
        ])->assertUnprocessable();
        $this->assertSame(3, DB::table('teacher_assignments')->where('teacher_id', $teacher->id)->count());

        $assignmentId = DB::table('teacher_assignments')->where('teacher_id', $teacher->id)->where('course_offering_id', $courseIds[0])->value('id');
        $this->deleteJson("/api/admin/teacher-assignments/{$assignmentId}")->assertOk()
            ->assertJsonPath('message', 'Asignación docente eliminada correctamente.');
        $this->assertDatabaseMissing('teacher_assignments', ['id' => $assignmentId]);
        $this->assertSame(18, DB::table('course_activities')->count());
    }
}
