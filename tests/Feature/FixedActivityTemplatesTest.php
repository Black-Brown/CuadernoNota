<?php

namespace Tests\Feature;

use App\Application\Activity\EnsureDefaultCourseActivities;
use App\Infrastructure\Models\ActivityTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FixedActivityTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_deactivate_or_rename_any_fixed_template(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
        foreach (EnsureDefaultCourseActivities::NAMES as $name) {
            $template = ActivityTemplate::where('name', $name)->firstOrFail();
            $this->assertTrue($template->is_fixed);
            $url = '/api/admin/activity-templates/'.$template->id;
            $this->deleteJson($url)->assertUnprocessable();
            $this->patchJson($url, ['active' => false])->assertUnprocessable();
            $this->patchJson($url, ['name' => 'Renombrada'])->assertUnprocessable();
            $this->assertSame($name, $template->fresh()->name);
            $this->assertTrue($template->fresh()->active);
        }
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
}
