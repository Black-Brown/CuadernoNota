<?php

namespace Tests\Feature;

use App\Application\Activity\EnsureDefaultCourseActivities;
use App\Infrastructure\Models\ActivityTemplate;
use App\Infrastructure\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FixedActivityTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_or_deactivate_a_fixed_template_without_removing_it(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));

        $template = ActivityTemplate::where('name', 'Proyectos')->firstOrFail();
        $this->assertTrue($template->is_fixed);
        $url = '/api/admin/activity-templates/'.$template->id;

        $this->patchJson($url, ['name' => 'Proyectos institucionales', 'icon' => 'school'])
            ->assertOk()
            ->assertJsonPath('name', 'Proyectos institucionales')
            ->assertJsonPath('icon', 'school')
            ->assertJsonPath('is_fixed', true);
        $this->deleteJson($url)->assertOk();

        $this->assertDatabaseHas('activity_templates', [
            'id' => $template->id,
            'name' => 'Proyectos institucionales',
            'active' => false,
            'is_fixed' => true,
        ]);
        $this->getJson('/api/admin/activity-templates')->assertOk()->assertJsonFragment(['is_fixed' => true]);
    }

    public function test_model_also_rejects_physical_deletion_of_fixed_templates(): void
    {
        $template = ActivityTemplate::where('name', 'Proyectos')->firstOrFail();
        try {
            $template->delete();
            $this->fail('A fixed template must not be deleted');
        } catch (ValidationException $exception) {
            $this->assertDatabaseHas('activity_templates', ['id' => $template->id, 'name' => 'Proyectos']);
        }
    }

    public function test_icons_remain_editable_and_non_fixed_templates_keep_existing_behavior(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        $template = ActivityTemplate::where('name', 'Proyectos')->firstOrFail();
        $this->patchJson('/api/admin/activity-templates/'.$template->id, ['icon' => 'school'])
            ->assertOk()->assertJsonPath('icon', 'school')->assertJsonPath('is_fixed', true);
        $custom = ActivityTemplate::create(['name' => 'Presentación', 'active' => true]);
        $this->assertFalse($custom->is_fixed);
        $this->deleteJson('/api/admin/activity-templates/'.$custom->id)->assertOk();
    }

    public function test_assigned_teacher_can_toggle_base_activity_and_the_choice_is_preserved(): void
    {
        $this->travelTo(Carbon::parse('2030-09-01'));
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $teacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'active' => true]);
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => '2030-2031', 'start_date' => '2030-08-01', 'end_date' => '2031-06-30', 'active' => true,
        ]);
        $periodId = DB::table('periods')->insertGetId([
            'academic_year_id' => $yearId, 'number' => 1, 'name' => 'Primer período', 'months' => 'Ago-Oct',
            'start_date' => '2030-08-01', 'end_date' => '2030-10-31', 'status' => 'open',
        ]);
        $gradeId = DB::table('grades')->insertGetId(['name' => 'Prueba base', 'level' => 'Secundaria', 'sort_order' => 99]);
        $sectionId = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina',
        ]);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Materia de prueba', 'code' => 'BASE-TEST', 'active' => true]);
        $offeringId = DB::table('course_offerings')->insertGetId([
            'section_id' => $sectionId, 'subject_id' => $subjectId, 'active' => true,
        ]);
        DB::table('teacher_assignments')->insert([
            'teacher_id' => $teacher->id, 'course_offering_id' => $offeringId, 'assigned_by' => $admin->id,
            'assigned_at' => now(), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $offering = CourseOffering::findOrFail($offeringId);
        app(EnsureDefaultCourseActivities::class)->execute($offering, $otherTeacher->id);
        $activityId = DB::table('course_activities')
            ->where('course_offering_id', $offeringId)
            ->where('period_id', $periodId)
            ->value('id');

        Sanctum::actingAs($teacher);
        $this->patchJson('/api/docente/activities/'.$activityId, [])->assertOk()->assertJsonPath('activity.active', false);

        app(EnsureDefaultCourseActivities::class)->execute($offering, $otherTeacher->id);
        $this->assertDatabaseHas('course_activities', ['id' => $activityId, 'status' => 'inactive']);
    }
}
