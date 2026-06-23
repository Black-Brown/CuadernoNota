<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Repositories\TeacherRepositoryInterface;
use App\Infrastructure\Models\Attendance as AttendanceModel;
use App\Infrastructure\Models\PeriodGrade as PeriodGradeModel;
use App\Infrastructure\Models\Student as StudentModel;
use App\Infrastructure\Models\TeacherSection as TeacherSectionModel;

class EloquentTeacherRepository implements TeacherRepositoryInterface
{
    public function findCoursesByTeacher(int $teacherId): array
    {
        return TeacherSectionModel::where('user_id', $teacherId)
            ->with(['section.grade', 'subject', 'academicYear'])
            ->get()
            ->map(fn($ts) => [
                'section_id'       => $ts->section_id,
                'subject_id'       => $ts->subject_id,
                'academic_year_id' => $ts->academic_year_id,
                'grade_name'       => $ts->section?->grade?->name ?? '',
                'section_name'     => $ts->section?->name ?? '',
                'subject_name'     => $ts->subject?->name ?? '',
                'year_label'       => $ts->academicYear?->label ?? '',
            ])
            ->all();
    }

    public function getDashboardSummary(int $teacherId): array
    {
        $teacherSections = TeacherSectionModel::where('user_id', $teacherId)
            ->with('section.students')
            ->get();

        $activeCourses = $teacherSections->count();

        $sectionIds = $teacherSections->pluck('section_id')->unique()->all();

        $totalStudents = StudentModel::whereIn('section_id', $sectionIds)
            ->where('active', true)
            ->count();

        $avgGrade = PeriodGradeModel::whereIn(
            'subject_id',
            $teacherSections->pluck('subject_id')->unique()->all()
        )->avg('period_score');

        $attendancePct = null;
        if (!empty($sectionIds)) {
            $total   = AttendanceModel::whereIn('section_id', $sectionIds)->count();
            $present = AttendanceModel::whereIn('section_id', $sectionIds)
                ->where('code', 'P')
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

    public function getSubjectDashboard(int $teacherId, int $sectionId, int $subjectId): array
    {
        $students = StudentModel::where('section_id', $sectionId)
            ->where('active', true)
            ->pluck('id')
            ->all();

        $grades = PeriodGradeModel::whereIn('student_id', $students)
            ->where('subject_id', $subjectId)
            ->get();

        $scores = $grades->pluck('period_score')->filter()->all();

        $groupAvg    = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
        $atRiskCount = $grades->filter(fn($g) => ($g->period_score ?? 0) < 70)->count();

        $total   = AttendanceModel::where('section_id', $sectionId)->count();
        $present = AttendanceModel::where('section_id', $sectionId)->where('code', 'P')->count();
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
