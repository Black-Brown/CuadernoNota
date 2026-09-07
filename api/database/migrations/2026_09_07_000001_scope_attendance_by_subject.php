<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Nullable preserves legacy rows whose subject cannot be inferred safely.
            $table->foreignId('subject_id')
                ->nullable()
                ->after('section_id')
                ->constrained('subjects');
        });

        DB::table('attendances')
            ->whereNull('subject_id')
            ->orderBy('id')
            ->eachById(function (object $attendance): void {
                $subjectIds = DB::table('teacher_assignments')
                    ->join('course_offerings', 'course_offerings.id', '=', 'teacher_assignments.course_offering_id')
                    ->where('teacher_assignments.teacher_id', $attendance->user_id)
                    ->where('course_offerings.section_id', $attendance->section_id)
                    ->distinct()
                    ->pluck('course_offerings.subject_id');

                if ($subjectIds->isEmpty()) {
                    $subjectIds = DB::table('course_offerings')
                        ->where('section_id', $attendance->section_id)
                        ->distinct()
                        ->pluck('subject_id');
                }

                if ($subjectIds->count() === 1) {
                    DB::table('attendances')
                        ->where('id', $attendance->id)
                        ->update(['subject_id' => $subjectIds->first()]);
                }
            });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('unique_attendance_day');
            $table->unique(['student_id', 'subject_id', 'date'], 'unique_attendance_subject_day');
            $table->index(['section_id', 'subject_id', 'date'], 'attendance_course_date_index');
        });
    }

    public function down(): void
    {
        $duplicateIds = DB::table('attendances')
            ->orderBy('id')
            ->get(['id', 'student_id', 'date'])
            ->groupBy(fn (object $row): string => "{$row->student_id}|{$row->date}")
            ->flatMap(fn ($rows) => $rows->slice(1)->pluck('id'));

        if ($duplicateIds->isNotEmpty()) {
            DB::table('attendances')->whereIn('id', $duplicateIds)->delete();
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('unique_attendance_subject_day');
            $table->dropIndex('attendance_course_date_index');
            $table->dropConstrainedForeignId('subject_id');
            $table->unique(['student_id', 'date'], 'unique_attendance_day');
        });
    }
};
