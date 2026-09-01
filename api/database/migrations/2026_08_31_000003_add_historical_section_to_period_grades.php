<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('period_grades', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('student_id')->constrained('sections');
            $table->index(['section_id', 'subject_id', 'period_id'], 'period_grades_course_lookup');
        });

        DB::table('period_grades')
            ->join('periods', 'periods.id', '=', 'period_grades.period_id')
            ->select(
                'period_grades.id',
                'period_grades.student_id',
                'periods.academic_year_id',
                'periods.start_date',
                'periods.end_date'
            )
            ->orderBy('period_grades.id')
            ->each(function (object $grade): void {
                $sectionId = DB::table('student_enrollments')
                    ->join('sections', 'sections.id', '=', 'student_enrollments.section_id')
                    ->where('student_enrollments.student_id', $grade->student_id)
                    ->where('sections.academic_year_id', $grade->academic_year_id)
                    ->whereDate('student_enrollments.enrolled_at', '<=', $grade->end_date)
                    ->where(function ($query) use ($grade) {
                        $query->whereNull('student_enrollments.ended_at')
                            ->orWhereDate('student_enrollments.ended_at', '>=', $grade->start_date);
                    })
                    ->orderByDesc('student_enrollments.enrolled_at')
                    ->value('student_enrollments.section_id');

                if (! $sectionId) {
                    $sectionId = DB::table('students')
                        ->join('sections', 'sections.id', '=', 'students.section_id')
                        ->where('students.id', $grade->student_id)
                        ->where('sections.academic_year_id', $grade->academic_year_id)
                        ->value('students.section_id');
                }

                if ($sectionId) {
                    DB::table('period_grades')->where('id', $grade->id)->update(['section_id' => $sectionId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('period_grades', function (Blueprint $table) {
            $table->dropIndex('period_grades_course_lookup');
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
