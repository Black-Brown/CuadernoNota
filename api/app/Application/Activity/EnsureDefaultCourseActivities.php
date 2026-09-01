<?php

namespace App\Application\Activity;

use App\Infrastructure\Models\CourseOffering;
use App\Infrastructure\Models\ActivityTemplate;
use Illuminate\Support\Facades\DB;

class EnsureDefaultCourseActivities
{
    public const NAMES = [
        'Proyectos',
        'Examen',
        'Tareas',
        'Ensayo',
        'Producción en aula',
        'Diagnósticas',
    ];

    public function execute(CourseOffering $offering, ?int $teacherId = null): void
    {
        $offering->loadMissing('section');

        if (!$offering->section) {
            return;
        }

        // Mark legacy defaults once. Afterwards is_fixed, rather than the editable
        // display name, is the permanent identity of an institutional template.
        foreach (self::NAMES as $name) {
            $template = ActivityTemplate::where('name', $name)->first();
            if ($template && ! (bool) $template->getRawOriginal('is_fixed')) {
                $template->forceFill(['is_fixed' => true])->saveQuietly();
            }
        }

        $missingCount = max(0, count(self::NAMES) - ActivityTemplate::where('is_fixed', true)->count());
        foreach (self::NAMES as $name) {
            if ($missingCount === 0) {
                break;
            }

            $template = ActivityTemplate::firstOrCreate(
                ['name' => $name],
                ['icon' => 'assignment', 'active' => true]
            );
            if (! (bool) $template->getRawOriginal('is_fixed')) {
                $template->forceFill(['is_fixed' => true])->saveQuietly();
                $missingCount--;
            }
        }

        $periodIds = DB::table('periods')
            ->where('academic_year_id', $offering->section->academic_year_id)
            ->pluck('id');

        $templateIds = DB::table('activity_templates')
            ->where('is_fixed', true)
            ->where('active', true)
            ->orderBy('id')
            ->pluck('id');

        foreach ($periodIds as $periodId) {
            foreach ($templateIds as $templateId) {
                DB::table('course_activities')->insertOrIgnore([
                    'course_offering_id' => $offering->id,
                    'period_id' => $periodId,
                    'activity_template_id' => $templateId,
                    'created_by' => $teacherId,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
