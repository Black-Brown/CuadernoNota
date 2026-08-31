<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_build_the_academic_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));

        $yearId = $this->postJson('/api/admin/academic-years', [
            'name' => '2026-2027', 'start_date' => '2026-08-01',
            'end_date' => '2027-06-30', 'active' => true,
        ])->assertCreated()->assertJsonPath('active', true)->json('id');

        $periodId = $this->postJson("/api/admin/academic-years/{$yearId}/periods", [
            'number' => 1, 'name' => 'Primer período', 'months' => 'Ago-Oct',
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'status' => 'open',
        ])->assertCreated()->json('id');

        $gradeId = $this->postJson('/api/admin/grades', [
            'name' => '4to Primaria', 'level' => 'Primaria', 'sort_order' => 4,
        ])->assertCreated()->json('id');

        $subjectId = $this->postJson('/api/admin/subjects', [
            'name' => 'Robótica y Programación', 'code' => 'ROBPROG',
            'active' => true, 'grade_ids' => [$gradeId],
        ])->assertCreated()->json('id');

        $sectionId = $this->postJson('/api/admin/sections', [
            'grade_id' => $gradeId, 'academic_year_id' => $yearId,
            'name' => 'A', 'shift' => 'Matutina',
        ])->assertCreated()->json('id');

        $offeringId = DB::table('course_offerings')
            ->where('section_id', $sectionId)->where('subject_id', $subjectId)->value('id');

        $this->assertNotNull($offeringId);
        $this->assertSame(6, DB::table('course_activities')
            ->where('course_offering_id', $offeringId)
            ->where('period_id', $periodId)
            ->whereNotNull('activity_template_id')->count());

        $templateId = $this->postJson('/api/admin/activity-templates', [
            'name' => 'Presentación', 'icon' => 'presentation', 'active' => true,
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('course_activities', [
            'course_offering_id' => $offeringId,
            'period_id' => $periodId,
            'activity_template_id' => $templateId,
        ]);

        $this->getJson('/api/admin/dashboard')->assertOk()
            ->assertJsonPath('active_academic_year.id', $yearId);
    }

    public function test_sections_are_locked_when_the_workspace_period_is_closed(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));

        $yearId = $this->postJson('/api/admin/academic-years', [
            'name' => '2027-2028', 'start_date' => '2027-08-01',
            'end_date' => '2028-06-30', 'active' => true,
        ])->assertCreated()->json('id');

        $periodId = $this->postJson("/api/admin/academic-years/{$yearId}/periods", [
            'number' => 1, 'name' => 'Primer período', 'months' => 'Ago-Oct',
            'start_date' => '2027-08-01', 'end_date' => '2027-10-31', 'status' => 'closed',
        ])->assertCreated()->json('id');

        $gradeId = $this->postJson('/api/admin/grades', [
            'name' => '5to Primaria', 'level' => 'Primaria', 'sort_order' => 5,
        ])->assertCreated()->json('id');

        $this->postJson('/api/admin/sections', [
            'grade_id' => $gradeId, 'academic_year_id' => $yearId,
            'name' => 'A', 'shift' => 'Matutina', 'period_id' => $periodId,
        ])->assertUnprocessable();

        $this->patchJson('/api/admin/periods/' . $periodId, [
            'months' => 'Ago-Oct', 'start_date' => '2027-08-01', 'end_date' => '2027-10-31', 'status' => 'open',
        ])->assertOk();

        $sectionId = $this->postJson('/api/admin/sections', [
            'grade_id' => $gradeId, 'academic_year_id' => $yearId,
            'name' => 'A', 'shift' => 'Matutina', 'period_id' => $periodId,
        ])->assertCreated()->json('id');

        $this->patchJson('/api/admin/periods/' . $periodId, [
            'months' => 'Ago-Oct', 'start_date' => '2027-08-01', 'end_date' => '2027-10-31', 'status' => 'closed',
        ])->assertOk();

        $this->patchJson("/api/admin/sections/{$sectionId}", [
            'shift' => 'Vespertina', 'period_id' => $periodId,
        ])->assertUnprocessable();

        $this->deleteJson("/api/admin/sections/{$sectionId}?period_id={$periodId}")
            ->assertUnprocessable();

        $this->getJson("/api/admin/periods/{$periodId}/activity-summary")->assertOk();
    }

    public function test_admin_can_manage_users_without_deactivating_itself(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        $teacherId = $this->postJson('/api/admin/users', [
            'name' => 'Profesor Robótica',
            'email' => 'robotica@happylearningschool.net',
            'role' => 'teacher',
        ])->assertCreated()->assertJsonMissingPath('password')->json('id');

        $this->patchJson("/api/admin/users/{$teacherId}", ['active' => false])
            ->assertOk()->assertJsonPath('active', false);

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertUnprocessable();
    }

    public function test_teacher_cannot_access_admin_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher', 'active' => true]));
        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
