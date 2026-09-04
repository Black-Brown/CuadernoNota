<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherCoursesTest extends TestCase
{
    use RefreshDatabase;

    public function test_courses_return_real_active_student_counts_and_only_active_assignments(): void
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
        $gradeId = DB::table('grades')->insertGetId([
            'name' => '1RO SECUNDARIA',
            'level' => 'Secundaria',
            'sort_order' => 1,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Ciencias Sociales',
            'code' => 'SOC',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sectionA = $this->section($gradeId, $yearId, 'A');
        $sectionB = $this->section($gradeId, $yearId, 'B');
        $sectionC = $this->section($gradeId, $yearId, 'C');
        $offeringA = $this->offering($sectionA, $subjectId);
        $offeringB = $this->offering($sectionB, $subjectId);
        $offeringC = $this->offering($sectionC, $subjectId);

        $this->assignment($teacher->id, $offeringA, true);
        $this->assignment($teacher->id, $offeringB, true);
        $this->assignment($teacher->id, $offeringC, false);

        $this->students($sectionA, $yearId, 22, 'A');
        $this->students($sectionB, $yearId, 22, 'B');
        $this->student($sectionA, $yearId, 'A-INACTIVE', false);

        Sanctum::actingAs($teacher);

        $response = $this->getJson('/api/docente/courses')
            ->assertOk()
            ->assertJsonCount(2, 'courses');

        $courses = collect($response->json('courses'))->keyBy('section_name');
        $this->assertSame('active', $courses['A']['status']);
        $this->assertSame(22, $courses['A']['students_count']);
        $this->assertSame('active', $courses['B']['status']);
        $this->assertSame(22, $courses['B']['students_count']);
        $this->assertFalse($courses->has('C'));
    }

    private function section(int $gradeId, int $yearId, string $name): int
    {
        return DB::table('sections')->insertGetId([
            'grade_id' => $gradeId,
            'academic_year_id' => $yearId,
            'name' => $name,
            'shift' => 'Matutina',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function offering(int $sectionId, int $subjectId): int
    {
        return DB::table('course_offerings')->insertGetId([
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignment(int $teacherId, int $offeringId, bool $active): void
    {
        DB::table('teacher_assignments')->insert([
            'teacher_id' => $teacherId,
            'course_offering_id' => $offeringId,
            'assigned_by' => null,
            'assigned_at' => now(),
            'active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function students(int $sectionId, int $yearId, int $count, string $prefix): void
    {
        foreach (range(1, $count) as $number) {
            $this->student($sectionId, $yearId, sprintf('%s-%02d', $prefix, $number));
        }
    }

    private function student(int $sectionId, int $yearId, string $enrollment, bool $active = true): void
    {
        DB::table('students')->insert([
            'name' => 'Estudiante',
            'last_name' => $enrollment,
            'enrollment_no' => $enrollment,
            'section_id' => $sectionId,
            'academic_year_id' => $yearId,
            'active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
