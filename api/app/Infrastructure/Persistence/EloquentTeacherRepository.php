<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Repositories\TeacherRepositoryInterface;
use App\Infrastructure\Models\Attendance as AttendanceModel;
use App\Infrastructure\Models\PeriodGrade as PeriodGradeModel;
use App\Infrastructure\Models\Student as StudentModel;
use App\Infrastructure\Models\TeacherSection as TeacherSectionModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EloquentTeacherRepository implements TeacherRepositoryInterface
{
    public function findCoursesByTeacher(int $teacherId): array
    {
        $courses = TeacherSectionModel::where('user_id', $teacherId)
            ->with(['section.grade', 'subject', 'academicYear'])
            ->get();

        $studentCounts = StudentModel::query()
            ->whereIn('section_id', $courses->pluck('section_id')->unique())
            ->where('active', true)
            ->selectRaw('section_id, COUNT(*) as students_count')
            ->groupBy('section_id')
            ->pluck('students_count', 'section_id');

        return $courses
            ->map(fn($ts) => [
                'section_id'       => $ts->section_id,
                'subject_id'       => $ts->subject_id,
                'academic_year_id' => $ts->academic_year_id,
                'grade_name'       => $ts->section?->grade?->name ?? '',
                'section_name'     => $ts->section?->name ?? '',
                'subject_name'     => $ts->subject?->name ?? '',
                'year_label'       => $ts->academicYear?->name ?? '',
                // teacher_sections is a compatibility view that only exposes
                // active teacher assignments backed by active course offerings.
                'status'           => 'active',
                'students_count'   => (int) ($studentCounts[$ts->section_id] ?? 0),
            ])
            ->all();
    }

    public function getDashboardSummary(int $teacherId, ?int $periodId = null): array
    {
        $period = $periodId ? DB::table('periods')->where('id', $periodId)->first() : null;
        $academicYearId = $period?->academic_year_id
            ?? DB::table('academic_years')->where('active', true)->value('id');

        $teacherSections = TeacherSectionModel::where('user_id', $teacherId)
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->with('section.students')
            ->get();

        $activeCourses = $teacherSections->count();

        $sectionIds = $teacherSections->pluck('section_id')->unique()->all();

        $totalStudents = StudentModel::whereIn('section_id', $sectionIds)
            ->where('active', true)
            ->count();

        $avgGrade = DB::table('period_grades')
            ->join('teacher_sections', function ($join) use ($teacherId) {
                $join->on('teacher_sections.section_id', '=', 'period_grades.section_id')
                    ->on('teacher_sections.subject_id', '=', 'period_grades.subject_id')
                    ->where('teacher_sections.user_id', '=', $teacherId);
            })
            ->when($academicYearId, fn ($query) => $query->where('teacher_sections.academic_year_id', $academicYearId))
            ->when($periodId, fn ($query) => $query->where('period_grades.period_id', $periodId))
            ->avg(DB::raw('COALESCE(period_grades.rp_score, period_grades.period_score)'));

        $attendancePct = null;
        if (!empty($sectionIds)) {
            $attendance = AttendanceModel::whereIn('section_id', $sectionIds)
                ->when($period, fn ($query) => $query
                    ->whereDate('date', '>=', $period->start_date)
                    ->whereDate('date', '<=', $period->end_date));
            $total = (clone $attendance)->count();
            $present = (clone $attendance)
                ->whereIn('code', ['P', 'T'])
                ->count();
            $attendancePct = $total > 0 ? round($present / $total * 100, 1) : null;
        }

        return [
            'active_courses'  => $activeCourses,
            'total_students'  => $totalStudents,
            'avg_grade'       => $avgGrade !== null ? round((float) $avgGrade, 1) : null,
            'attendance_avg'  => $attendancePct,
        ];
    }

    public function getSubjectDashboard(int $teacherId, int $sectionId, int $subjectId, ?int $periodId = null): array
    {
        $period = $periodId ? DB::table('periods')->where('id', $periodId)->first() : null;
        $assignment = TeacherSectionModel::where('user_id', $teacherId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->when($period, fn ($query) => $query->where('academic_year_id', $period->academic_year_id))
            ->exists();

        if (! $assignment) {
            throw new AuthorizationException('No tienes acceso a este curso en el período seleccionado.');
        }

        $students = StudentModel::where('section_id', $sectionId)
            ->where('active', true)
            ->pluck('id')
            ->all();

        $grades = PeriodGradeModel::whereIn('student_id', $students)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->when($periodId, fn ($query) => $query->where('period_id', $periodId))
            ->get();

        $scores = $grades->pluck('period_score')->filter()->all();

        $groupAvg    = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
        $atRiskCount = $grades->filter(fn($g) => ($g->period_score ?? 0) < 70)->count();

        $attendance = AttendanceModel::where('section_id', $sectionId)
            ->when($period, fn ($query) => $query
                ->whereDate('date', '>=', $period->start_date)
                ->whereDate('date', '<=', $period->end_date));
        $total = (clone $attendance)->count();
        $present = (clone $attendance)->whereIn('code', ['P', 'T'])->count();
        $attendancePct = $total > 0 ? round($present / $total * 100, 1) : null;

        $distribution = [
            'below_70'  => 0,
            '70_to_80'  => 0,
            '80_to_90'  => 0,
            '90_to_95'  => 0,
            '95_to_100' => 0,
        ];
        foreach ($scores as $s) {
            if ($s < 70)       $distribution['below_70']++;
            elseif ($s < 80)   $distribution['70_to_80']++;
            elseif ($s < 90)   $distribution['80_to_90']++;
            elseif ($s < 95)   $distribution['90_to_95']++;
            else               $distribution['95_to_100']++;
        }

        return [
            'group_avg'       => $groupAvg,
            'at_risk_count'   => $atRiskCount,
            'attendance_pct'  => $attendancePct,
            'distribution'    => $distribution,
        ];
    }
}
