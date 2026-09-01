<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('section_id')
                ->nullable()
                ->after('subject_id')
                ->constrained('sections')
                ->nullOnDelete();
        });

        $activities = DB::table('activities')->whereNull('section_id')->orderBy('id')->get();

        foreach ($activities as $activity) {
            $assignments = DB::table('teacher_sections')
                ->where('subject_id', $activity->subject_id)
                ->where('academic_year_id', $activity->academic_year_id)
                ->when($activity->user_id, fn($query) => $query->where('user_id', $activity->user_id))
                ->orderBy('section_id')
                ->get();

            if ($assignments->isEmpty()) {
                continue;
            }

            $first = $assignments->shift();

            DB::table('activities')
                ->where('id', $activity->id)
                ->update(['section_id' => $first->section_id]);

            if (!$activity->is_base) {
                continue;
            }

            foreach ($assignments as $assignment) {
                $studentIds = DB::table('students')
                    ->where('section_id', $assignment->section_id)
                    ->pluck('id');

                $hasScoresForSection = DB::table('activity_scores')
                    ->where('activity_id', $activity->id)
                    ->whereIn('student_id', $studentIds)
                    ->exists();

                if (!$activity->is_base && !$hasScoresForSection) {
                    continue;
                }

                $copy = (array) $activity;
                unset($copy['id']);
                $copy['section_id'] = $assignment->section_id;
                $copy['created_at'] = now();
                $copy['updated_at'] = now();

                $newActivityId = DB::table('activities')->insertGetId($copy);

                DB::table('activity_scores')
                    ->where('activity_id', $activity->id)
                    ->whereIn('student_id', $studentIds)
                    ->update(['activity_id' => $newActivityId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['section_id']);
            $table->dropColumn('section_id');
        });
    }
};
