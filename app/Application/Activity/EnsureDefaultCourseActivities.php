<?php

namespace App\Application\Activity;

use App\Infrastructure\Models\CourseOffering;
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

        $periodIds = DB::table('periods')
            ->where('academic_year_id', $offering->section->academic_year_id)
            ->pluck('id');

        $templates = DB::table('activity_templates')
            ->whereIn('name', self::NAMES)
            ->pluck('id', 'name');

        foreach ($periodIds as $periodId) {
            foreach (self::NAMES as $name) {
                $templateId = $templates[$name] ?? null;
                if (!$templateId) {
                    continue;
                }

                DB::table('course_activities')->updateOrInsert(
                    [
                        'course_offering_id' => $offering->id,
                        'period_id' => $periodId,
                        'activity_template_id' => $templateId,
                    ],
                    [
                        'created_by' => $teacherId,
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
