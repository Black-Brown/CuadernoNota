<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAssignmentLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_without_subjects_remain_visible_and_courses_include_explicit_levels(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $year = DB::table('academic_years')->insertGetId(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true]);
        $sections = [];
        foreach (['PRIMARIA', 'SECUNDARIA'] as $level) {
            $grade = DB::table('grades')->insertGetId(['name' => 'Primero '.$level, 'level' => $level, 'sort_order' => 1, 'active' => true]);
            $sections[$level] = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'A', 'shift' => 'Matutina']);
        }
        $subject = DB::table('subjects')->insertGetId(['name' => 'Lengua', 'code' => 'LEN', 'active' => true]);
        DB::table('course_offerings')->insert(['section_id' => $sections['SECUNDARIA'], 'subject_id' => $subject, 'active' => true]);
        $response = $this->getJson('/api/admin/teacher-assignments/options')->assertOk()->assertJsonCount(2, 'sections')->assertJsonCount(1, 'courses');
        $this->assertEqualsCanonicalizing(['PRIMARIA', 'SECUNDARIA'], array_column($response->json('sections'), 'grade_level'));
        $response->assertJsonPath('courses.0.grade_level', 'SECUNDARIA')->assertJsonPath('courses.0.academic_year_id', $year);
        $this->assertDatabaseCount('course_offerings', 1); // GET must not create a curriculum.
        DB::table('course_offerings')->insert(['section_id' => $sections['PRIMARIA'], 'subject_id' => $subject, 'active' => true]);
        $response = $this->getJson('/api/admin/teacher-assignments/options')->assertOk()->assertJsonCount(2, 'courses');
        $this->assertEqualsCanonicalizing(['PRIMARIA', 'SECUNDARIA'], array_column($response->json('courses'), 'grade_level'));
        foreach (['attendance', 'academic'] as $report) {
            $rows = $this->getJson("/api/admin/reports/{$report}/courses?academic_year_id={$year}")->assertOk()->assertJsonCount(2)->json();
            $this->assertEqualsCanonicalizing(['PRIMARIA', 'SECUNDARIA'], array_column($rows, 'grade_level'));
        }
        DB::table('subjects')->where('id', $subject)->update(['active' => false]);
        $this->getJson('/api/admin/teacher-assignments/options')->assertOk()->assertJsonCount(2, 'sections')->assertJsonCount(0, 'courses');
        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator', 'active' => true, 'coordinator_level' => 'Primaria']));
        $this->getJson('/api/admin/teacher-assignments/options')->assertForbidden();
    }
}
