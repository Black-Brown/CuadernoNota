<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_records_are_isolated_and_preserve_historical_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $year = DB::table('academic_years')->insertGetId(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true]);
        $otherYear = DB::table('academic_years')->insertGetId(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'active' => false]);
        $grade = DB::table('grades')->insertGetId(['name' => '1RO SECUNDARIA', 'level' => 'Secundaria', 'sort_order' => 1]);
        $section = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'A', 'shift' => 'Matutina']);
        $other = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'B', 'shift' => 'Matutina']);
        $student = DB::table('students')->insertGetId(['name' => 'Ana', 'last_name' => 'Prueba', 'enrollment_no' => 'TEST-1', 'active' => false, 'section_id' => $other]);
        foreach ([[$section, '2026-09-01', 'A'], [$section, '2026-09-02', 'T'], [$other, '2026-09-03', 'P']] as [$id, $date, $code]) {
            DB::table('attendances')->insert(['student_id' => $student, 'section_id' => $id, 'user_id' => $admin->id, 'date' => $date, 'code' => $code]);
        }
        $url = "/api/admin/reports/attendance/courses/{$section}?academic_year_id={$year}";
        $this->getJson("/api/admin/reports/attendance/courses?academic_year_id={$year}")->assertOk()->assertJsonCount(2);
        $this->getJson($url)->assertOk()->assertJsonPath('date', '2026-09-02')->assertJsonCount(2, 'dates')->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.code', 'T')->assertJsonPath('rows.0.subject', 'Sin materia (histórico)');
        $this->getJson($url.'&date=2026-09-01')->assertOk()->assertJsonPath('rows.0.code', 'A');
        $this->getJson($url.'&date=2026-09-03')->assertOk()->assertJsonCount(0, 'rows');
        $subject = DB::table('subjects')->insertGetId(['name' => 'Matemática', 'code' => 'MAT', 'active' => true]);
        DB::table('attendances')->insert(['student_id' => $student, 'section_id' => $section, 'subject_id' => $subject, 'user_id' => $admin->id, 'date' => '2026-09-02', 'code' => 'P']);
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'rows');
        $this->getJson("/api/admin/reports/attendance?academic_year_id={$year}")->assertOk()->assertJsonCount(3);
        $this->getJson($url.'&date=invalid')->assertUnprocessable();
        $this->getJson("/api/admin/reports/attendance/courses/{$section}?academic_year_id={$otherYear}")->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher', 'active' => true]));
        $this->getJson($url)->assertForbidden();
        $this->getJson("/api/admin/reports/attendance/courses?academic_year_id={$year}")->assertForbidden();
    }
}
