<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_scores')
            ->join('activities', 'activity_scores.activity_id', '=', 'activities.id')
            ->join('students', 'activity_scores.student_id', '=', 'students.id')
            ->whereNotNull('activities.section_id')
            ->whereColumn('activities.section_id', '!=', 'students.section_id')
            ->select(
                'activity_scores.id as score_id',
                'activities.id as activity_id',
                'activities.name',
                'activities.subject_id',
                'activities.user_id',
                'activities.academic_year_id',
                'activities.period_id',
                'activities.is_base',
                'students.section_id as student_section_id'
            )
            ->orderBy('activity_scores.id')
            ->get()
            ->each(function (object $row): void {
                $targetActivityId = DB::table('activities')
                    ->where('name', $row->name)
                    ->where('subject_id', $row->subject_id)
                    ->where('user_id', $row->user_id)
                    ->where('academic_year_id', $row->academic_year_id)
                    ->where('period_id', $row->period_id)
                    ->where('section_id', $row->student_section_id)
                    ->where('is_base', $row->is_base)
                    ->value('id');

                if ($targetActivityId === null) {
                    $source = (array) DB::table('activities')->where('id', $row->activity_id)->first();
                    unset($source['id']);
                    $source['section_id'] = $row->student_section_id;
                    $source['created_at'] = now();
                    $source['updated_at'] = now();
                    $targetActivityId = DB::table('activities')->insertGetId($source);
                }

                DB::table('activity_scores')
                    ->where('id', $row->score_id)
                    ->update(['activity_id' => $targetActivityId]);
            });
    }

    public function down(): void
    {
        // Revertir estas asociaciones podría volver a corromper la relación
        // entre la nota y la sección real del estudiante.
    }
};
