<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminGradeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
    }

    private function createGrade(string $name = '1ro Primaria', int $sortOrder = 1): int
    {
        return $this->postJson('/api/admin/grades', [
            'name' => $name, 'level' => 'Primaria', 'sort_order' => $sortOrder,
        ])->assertCreated()->json('id');
    }

    public function test_a_grade_without_relations_can_be_deleted(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();

        $this->getJson("/api/admin/grades/{$gradeId}/deletion-check")
            ->assertOk()
            ->assertJson(['can_delete' => true, 'relations' => ['sections' => 0, 'subjects' => 0]]);

        $this->deleteJson("/api/admin/grades/{$gradeId}")->assertOk();
        $this->assertDatabaseMissing('grades', ['id' => $gradeId]);
    }

    public function test_a_grade_with_a_section_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $yearId = $this->postJson('/api/admin/academic-years', [
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true,
        ])->assertCreated()->json('id');
        $this->postJson('/api/admin/sections', [
            'grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina',
        ])->assertCreated();

        $this->getJson("/api/admin/grades/{$gradeId}/deletion-check")
            ->assertOk()
            ->assertJson(['can_delete' => false, 'relations' => ['sections' => 1, 'subjects' => 0]]);

        $this->deleteJson("/api/admin/grades/{$gradeId}")->assertUnprocessable();
        $this->assertDatabaseHas('grades', ['id' => $gradeId]);
    }

    public function test_a_grade_with_a_subject_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $this->postJson('/api/admin/subjects', [
            'name' => 'Robótica', 'code' => 'ROB', 'active' => true, 'grade_ids' => [$gradeId],
        ])->assertCreated();

        $this->getJson("/api/admin/grades/{$gradeId}/deletion-check")
            ->assertOk()
            ->assertJson(['can_delete' => false, 'relations' => ['sections' => 0, 'subjects' => 1]]);

        $this->deleteJson("/api/admin/grades/{$gradeId}")->assertUnprocessable();
    }

    public function test_a_grade_with_sections_and_subjects_reports_both_relations(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $yearId = $this->postJson('/api/admin/academic-years', [
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30', 'active' => true,
        ])->assertCreated()->json('id');
        $this->postJson('/api/admin/sections', [
            'grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina',
        ])->assertCreated();
        $this->postJson('/api/admin/subjects', [
            'name' => 'Matemáticas', 'code' => 'MAT', 'active' => true, 'grade_ids' => [$gradeId],
        ])->assertCreated();

        $this->getJson("/api/admin/grades/{$gradeId}/deletion-check")
            ->assertOk()
            ->assertJson(['can_delete' => false, 'relations' => ['sections' => 1, 'subjects' => 1]]);
    }

    public function test_deactivating_a_grade_preserves_its_relations(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $this->postJson('/api/admin/subjects', [
            'name' => 'Ciencias', 'code' => 'CIE', 'active' => true, 'grade_ids' => [$gradeId],
        ])->assertCreated();

        $this->patchJson("/api/admin/grades/{$gradeId}/deactivate")->assertOk();
        $this->assertDatabaseHas('grades', ['id' => $gradeId, 'active' => false]);
        $this->assertDatabaseHas('grade_subjects', ['grade_id' => $gradeId]);
    }

    public function test_grade_listing_can_be_filtered_by_status(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $this->patchJson("/api/admin/grades/{$gradeId}/deactivate")->assertOk();

        $this->getJson('/api/admin/grades')->assertOk()->assertJsonMissing(['id' => $gradeId]);
        $this->getJson('/api/admin/grades?status=inactive')->assertOk()->assertJsonFragment(['id' => $gradeId]);
        $this->getJson('/api/admin/grades?status=all')->assertOk()->assertJsonFragment(['id' => $gradeId]);
    }

    public function test_reactivating_a_grade_restores_active_status(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $this->patchJson("/api/admin/grades/{$gradeId}/deactivate")->assertOk();

        $this->patchJson("/api/admin/grades/{$gradeId}/reactivate")->assertOk();
        $this->assertDatabaseHas('grades', ['id' => $gradeId, 'active' => true]);
    }

    public function test_an_inactive_grade_with_relations_still_cannot_be_deleted(): void
    {
        $this->actingAsAdmin();
        $gradeId = $this->createGrade();
        $this->postJson('/api/admin/subjects', [
            'name' => 'Historia', 'code' => 'HIS', 'active' => true, 'grade_ids' => [$gradeId],
        ])->assertCreated();
        $this->patchJson("/api/admin/grades/{$gradeId}/deactivate")->assertOk();

        $this->getJson("/api/admin/grades/{$gradeId}/deletion-check")->assertOk()->assertJson(['can_delete' => false]);
        $this->deleteJson("/api/admin/grades/{$gradeId}")->assertUnprocessable();
    }
}
