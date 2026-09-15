<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicReportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_only_returns_official_grades_for_the_exact_section_and_year(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $year = DB::table('academic_years')->insertGetId(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true]);
        $old = DB::table('academic_years')->insertGetId(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'active' => false]);
        $grade = DB::table('grades')->insertGetId(['name' => 'Primero', 'level' => 'Secundaria', 'sort_order' => 1]);
        $section = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'A', 'shift' => 'Matutina']);
        $other = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'B', 'shift' => 'Matutina']);
        $period = DB::table('periods')->insertGetId(['academic_year_id' => $year, 'number' => 1, 'name' => 'Primero', 'months' => 'Sep-Nov', 'start_date' => '2026-09-01', 'end_date' => '2026-11-30', 'status' => 'open']);
        $oldPeriod = DB::table('periods')->insertGetId(['academic_year_id' => $old, 'number' => 1, 'name' => 'Primero', 'months' => 'Sep-Nov', 'start_date' => '2025-09-01', 'end_date' => '2025-11-30', 'status' => 'closed']);
        $subject = DB::table('subjects')->insertGetId(['name' => 'Matemática', 'code' => 'MAT', 'active' => true]);
        foreach ([[$section, $period, 'official'], [$other, $period, 'official'], [$section, $period, 'draft'], [$section, $period, 'in_review'], [$section, $oldPeriod, 'official']] as $index => [$destination, $p, $status]) {
            $student = DB::table('students')->insertGetId(['name' => 'Ana', 'last_name' => 'Prueba', 'enrollment_no' => 'TEST-'.$index, 'active' => false, 'section_id' => $other]);
            DB::table('period_grades')->insert(['student_id' => $student, 'section_id' => $destination, 'period_id' => $p, 'subject_id' => $subject, 'status' => $status, 'period_score' => 60, 'rp_score' => 75]);
        }
        $url = "/api/admin/reports/academic/courses/{$section}?academic_year_id={$year}";
        $this->getJson("/api/admin/reports/academic/courses?academic_year_id={$year}")->assertOk()->assertJsonCount(2);
        $response = $this->getJson($url)->assertOk()->assertJsonCount(1, 'rows')->assertJsonCount(1, 'periods')->assertJsonPath('rows.0.enrollment_no', 'TEST-0');
        $this->assertEquals(75, $response->json('rows.0.effective_score'));
        $this->assertNull($response->json('rows.0.c1_score'));
        $this->getJson("/api/admin/reports/academic/courses/{$section}?academic_year_id={$old}")->assertNotFound();
        $this->getJson("/api/admin/reports/academic/courses/{$section}")->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher', 'active' => true]));
        $this->getJson($url)->assertForbidden();
        $this->getJson("/api/admin/reports/academic/courses?academic_year_id={$year}")->assertForbidden();
    }
}
