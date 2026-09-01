<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RiskController extends Controller
{
    private const ACADEMIC_RISK_THRESHOLD = 70.0;
    private const HIGH_RISK_THRESHOLD = 60.0;
    private const ATTENDANCE_RISK_THRESHOLD = 80.0;
    private const HIGH_ATTENDANCE_RISK_THRESHOLD = 70.0;
    private const CONSECUTIVE_ABSENCE_THRESHOLD = 3;

    public function index(Request $request): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $periodId = $this->resolvePeriodId($request);

        $courses = $this->teacherCourses($teacherId)
            ->map(fn(object $course): array => $this->courseRiskSummary($course, $periodId))
            ->values();

        return response()->json([
            'period_id' => $periodId,
            'summary' => [
                'total_students_at_risk' => $courses->sum('at_risk_count'),
                'high_risk_students' => $courses->sum('high_risk_count'),
                'attendance_alerts' => $courses->sum('attendance_alert_count'),
                'competency_alerts' => $courses->sum('competency_alert_count'),
                'courses_with_risk' => $courses->where('at_risk_count', '>', 0)->count(),
            ],
            'courses' => $courses,
        ]);
    }

    public function course(Request $request, int $sectionId, int $subjectId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $periodId = $this->resolvePeriodId($request);
        $course = $this->assignedCourse($teacherId, $sectionId, $subjectId);
        $students = $this->riskStudents($sectionId, $subjectId, $periodId);

        return response()->json([
            'period_id' => $periodId,
            'course' => $this->coursePayload($course),
            'summary' => [
                'total_students_at_risk' => $students->count(),
                'high_risk_students' => $students->where('risk_level', 'high')->count(),
                'medium_risk_students' => $students->where('risk_level', 'medium')->count(),
                'attendance_alerts' => $students->filter(fn(array $student): bool => $student['attendance_risk'])->count(),
                'competency_alerts' => $students->filter(fn(array $student): bool => $student['competency_risk'])->count(),
            ],
            'students' => $students->values(),
        ]);
    }

    public function student(Request $request, int $sectionId, int $subjectId, int $studentId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $periodId = $this->resolvePeriodId($request);
        $course = $this->assignedCourse($teacherId, $sectionId, $subjectId);

        $student = DB::table('students')
            ->where('id', $studentId)
            ->where('section_id', $sectionId)
            ->where('active', true)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'El estudiante no pertenece a este curso.'], 404);
        }

        $risk = $this->buildStudentRisk($student, $sectionId, $subjectId, $periodId);

        return response()->json([
            'period_id' => $periodId,
            'course' => $this->coursePayload($course),
            'student' => $risk,
            'activity_performance' => $this->activityPerformance($studentId, $sectionId, $subjectId, $periodId),
            'competency_summary' => $this->competencySummary($studentId, $subjectId, $periodId),
            'alert_timeline' => $this->alertTimeline($studentId),
            'recent_observations' => $this->recentObservations($studentId),
            'risk_distribution' => [
                'academic_health' => $this->healthFromScore($risk['average_grade']),
                'attendance_health' => $risk['attendance_pct'],
                'competency_health' => $this->healthFromScore($risk['competency_average']),
            ],
        ]);
    }

    private function resolvePeriodId(Request $request): int
    {
        $periodId = $request->integer('period_id');

        if ($periodId > 0) {
            return $periodId;
        }

        return (int) DB::table('periods')
            ->where('status', 'open')
            ->orderByDesc('number')
            ->value('id')
            ?: (int) DB::table('periods')->orderByDesc('id')->value('id');
    }

    private function teacherCourses(int $teacherId): Collection
    {
        return DB::table('teacher_sections')
            ->join('sections', 'teacher_sections.section_id', '=', 'sections.id')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('subjects', 'teacher_sections.subject_id', '=', 'subjects.id')
            ->join('academic_years', 'teacher_sections.academic_year_id', '=', 'academic_years.id')
            ->where('teacher_sections.user_id', $teacherId)
            ->select(
                'teacher_sections.section_id',
                'teacher_sections.subject_id',
                'teacher_sections.academic_year_id',
                'grades.name as grade_name',
                'grades.level as grade_level',
                'sections.name as section_name',
                'subjects.name as subject_name',
                'academic_years.name as year_label'
            )
            ->orderBy('grades.sort_order')
            ->orderBy('sections.name')
            ->orderBy('subjects.name')
            ->get();
    }

    private function assignedCourse(int $teacherId, int $sectionId, int $subjectId): object
    {
        $course = $this->teacherCourses($teacherId)
            ->first(fn(object $course): bool => (int) $course->section_id === $sectionId && (int) $course->subject_id === $subjectId);

        if (!$course) {
            throw new AuthorizationException('No tienes permiso para consultar este curso.');
        }

        return $course;
    }

    private function courseRiskSummary(object $course, int $periodId): array
    {
        $students = $this->riskStudents((int) $course->section_id, (int) $course->subject_id, $periodId);
        $groupAverage = $this->groupAverage((int) $course->section_id, (int) $course->subject_id, $periodId);
        $attendancePct = $this->sectionAttendancePercentage((int) $course->section_id);
        $riskPressure = min(100, (int) round(($students->count() / max(1, $this->activeStudentCount((int) $course->section_id))) * 100));

        return array_merge($this->coursePayload($course), [
            'at_risk_count' => $students->count(),
            'high_risk_count' => $students->where('risk_level', 'high')->count(),
            'attendance_alert_count' => $students->filter(fn(array $student): bool => $student['attendance_risk'])->count(),
            'competency_alert_count' => $students->filter(fn(array $student): bool => $student['competency_risk'])->count(),
            'attendance_pct' => $attendancePct,
            'avg_grade' => $groupAverage,
            'risk_pressure' => $riskPressure,
            'risk_level' => $this->courseRiskLevel($students->count(), $students->where('risk_level', 'high')->count(), $groupAverage, $attendancePct),
        ]);
    }

    private function coursePayload(object $course): array
    {
        return [
            'section_id' => (int) $course->section_id,
            'subject_id' => (int) $course->subject_id,
            'academic_year_id' => (int) $course->academic_year_id,
            'grade_name' => $course->grade_name,
            'grade_level' => $course->grade_level,
            'section_name' => $course->section_name,
            'subject_name' => $course->subject_name,
            'year_label' => $course->year_label,
        ];
    }

    private function riskStudents(int $sectionId, int $subjectId, int $periodId): Collection
    {
        return DB::table('students')
            ->where('section_id', $sectionId)
            ->where('active', true)
            ->orderBy('last_name')
            ->orderBy('name')
            ->get()
            ->map(fn(object $student): array => $this->buildStudentRisk($student, $sectionId, $subjectId, $periodId))
            ->filter(fn(array $student): bool => $student['is_at_risk'])
            ->sortByDesc('risk_score')
            ->values();
    }

    private function buildStudentRisk(object $student, int $sectionId, int $subjectId, int $periodId): array
    {
        $periodGrade = DB::table('period_grades')
            ->where('student_id', $student->id)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->first();

        $averageGrade = $periodGrade
            ? round((float) ($periodGrade->rp_score ?? $periodGrade->period_score), 2)
            : null;

        $competencies = [
            'c1' => $periodGrade?->c1_score !== null ? (float) $periodGrade->c1_score : null,
            'c2' => $periodGrade?->c2_score !== null ? (float) $periodGrade->c2_score : null,
            'c3' => $periodGrade?->c3_score !== null ? (float) $periodGrade->c3_score : null,
        ];

        $attendancePct = $this->studentAttendancePercentage((int) $student->id);
        $hasConsecutiveAbsences = $this->hasConsecutiveAbsences((int) $student->id);
        $activeAlerts = $this->activeAlertCount((int) $student->id);
        $weakCompetencies = collect($competencies)->filter(fn(?float $score): bool => $score !== null && $score < self::ACADEMIC_RISK_THRESHOLD);

        $academicRisk = $averageGrade !== null && $averageGrade < self::ACADEMIC_RISK_THRESHOLD;
        $attendanceRisk = $attendancePct < self::ATTENDANCE_RISK_THRESHOLD || $hasConsecutiveAbsences;
        $competencyRisk = $weakCompetencies->isNotEmpty();
        $isAtRisk = $academicRisk || $attendanceRisk || $competencyRisk || $activeAlerts > 0;
        $riskScore = $this->riskScore($averageGrade, $attendancePct, $hasConsecutiveAbsences, $weakCompetencies->count(), $activeAlerts);

        return [
            'student_id' => (int) $student->id,
            'student_name' => "{$student->last_name}, {$student->name}",
            'display_name' => "{$student->name} {$student->last_name}",
            'enrollment_no' => $student->enrollment_no,
            'average_grade' => $averageGrade,
            'effective_grade' => $averageGrade,
            'period_score' => $periodGrade?->period_score !== null ? (float) $periodGrade->period_score : null,
            'rp_score' => $periodGrade?->rp_score !== null ? (float) $periodGrade->rp_score : null,
            'attendance_pct' => round($attendancePct, 2),
            'competency_average' => $this->competencyAverage($competencies),
            'critical_competency' => $this->criticalCompetency($competencies),
            'weak_competencies' => $weakCompetencies->keys()->values(),
            'active_alerts' => $activeAlerts,
            'academic_risk' => $academicRisk,
            'attendance_risk' => $attendanceRisk,
            'competency_risk' => $competencyRisk,
            'consecutive_absence_risk' => $hasConsecutiveAbsences,
            'is_at_risk' => $isAtRisk,
            'risk_score' => $riskScore,
            'risk_level' => $this->riskLevel($riskScore),
            'risk_reasons' => $this->riskReasons($academicRisk, $attendanceRisk, $competencyRisk, $hasConsecutiveAbsences, $activeAlerts),
            'grade_status' => $periodGrade?->status,
        ];
    }

    private function activityPerformance(int $studentId, int $sectionId, int $subjectId, int $periodId): array
    {
        $rows = DB::table('activity_scores')
            ->join('activities', 'activity_scores.activity_id', '=', 'activities.id')
            ->join('competencies', 'activity_scores.competency_id', '=', 'competencies.id')
            ->where('activity_scores.student_id', $studentId)
            ->where('activity_scores.subject_id', $subjectId)
            ->where('activity_scores.period_id', $periodId)
            ->where('activities.section_id', $sectionId)
            ->select('activities.id', 'activities.name', 'competencies.code', 'activity_scores.score')
            ->orderBy('activities.name')
            ->get();

        return $rows
            ->groupBy('id')
            ->map(function (Collection $scores): array {
                $first = $scores->first();
                $competencies = ['C1' => null, 'C2' => null, 'C3' => null];

                foreach ($scores as $score) {
                    $competencies[strtoupper($score->code)] = $score->score !== null ? (float) $score->score : null;
                }

                return [
                    'activity_id' => (int) $first->id,
                    'activity_name' => $first->name,
                    'c1' => $competencies['C1'],
                    'c2' => $competencies['C2'],
                    'c3' => $competencies['C3'],
                ];
            })
            ->values()
            ->all();
    }

    private function competencySummary(int $studentId, int $subjectId, int $periodId): array
    {
        $periodGrade = DB::table('period_grades')
            ->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->first();

        $competencies = DB::table('competencies')
            ->whereIn('code', ['C1', 'C2', 'C3'])
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        return collect(['C1' => 'c1_score', 'C2' => 'c2_score', 'C3' => 'c3_score'])
            ->map(function (string $column, string $code) use ($periodGrade, $competencies, $studentId, $subjectId, $periodId): array {
                $average = $periodGrade?->{$column} !== null ? (float) $periodGrade->{$column} : null;
                $weakest = $this->weakestActivity($studentId, $subjectId, $periodId, $code);

                return [
                    'code' => $code,
                    'name' => $competencies[$code]->name ?? $code,
                    'average' => $average,
                    'status' => $average !== null && $average < self::ACADEMIC_RISK_THRESHOLD ? 'risk' : 'ok',
                    'weakest_activity' => $weakest,
                ];
            })
            ->values()
            ->all();
    }

    private function weakestActivity(int $studentId, int $subjectId, int $periodId, string $competencyCode): ?array
    {
        $row = DB::table('activity_scores')
            ->join('activities', 'activity_scores.activity_id', '=', 'activities.id')
            ->join('competencies', 'activity_scores.competency_id', '=', 'competencies.id')
            ->where('activity_scores.student_id', $studentId)
            ->where('activity_scores.subject_id', $subjectId)
            ->where('activity_scores.period_id', $periodId)
            ->where('competencies.code', $competencyCode)
            ->whereNotNull('activity_scores.score')
            ->select('activities.name', 'activity_scores.score')
            ->orderBy('activity_scores.score')
            ->first();

        return $row ? ['activity_name' => $row->name, 'score' => (float) $row->score] : null;
    }

    private function alertTimeline(int $studentId): array
    {
        return DB::table('alerts')
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn(object $alert): array => [
                'id' => (int) $alert->id,
                'type' => $alert->type,
                'message' => $alert->message,
                'resolved' => (bool) $alert->resolved,
                'date' => $alert->created_at,
            ])
            ->all();
    }

    private function recentObservations(int $studentId): array
    {
        return DB::table('observations')
            ->leftJoin('users', 'observations.user_id', '=', 'users.id')
            ->where('observations.student_id', $studentId)
            ->orderByDesc('observations.date')
            ->orderByDesc('observations.id')
            ->limit(10)
            ->select('observations.id', 'observations.date', 'observations.type', 'observations.description', 'users.name as author_name')
            ->get()
            ->map(fn(object $observation): array => [
                'id' => (int) $observation->id,
                'date' => $observation->date,
                'type' => $observation->type,
                'description' => $observation->description,
                'author_name' => $observation->author_name,
            ])
            ->all();
    }

    private function studentAttendancePercentage(int $studentId): float
    {
        $records = DB::table('attendances')->where('student_id', $studentId)->get(['code']);

        if ($records->isEmpty()) {
            return 100.0;
        }

        return ($records->where('code', 'P')->count() / $records->count()) * 100.0;
    }

    private function sectionAttendancePercentage(int $sectionId): float
    {
        $records = DB::table('attendances')->where('section_id', $sectionId)->get(['code']);

        if ($records->isEmpty()) {
            return 100.0;
        }

        return round(($records->where('code', 'P')->count() / $records->count()) * 100.0, 2);
    }

    private function hasConsecutiveAbsences(int $studentId): bool
    {
        $records = DB::table('attendances')
            ->where('student_id', $studentId)
            ->orderBy('date')
            ->get(['date', 'code'])
            ->groupBy(fn(object $record): string => substr((string) $record->date, 0, 7));

        foreach ($records as $monthRecords) {
            $streak = 0;

            foreach ($monthRecords as $record) {
                if (in_array($record->code, ['A', 'T'], true)) {
                    $streak++;
                    if ($streak >= self::CONSECUTIVE_ABSENCE_THRESHOLD) {
                        return true;
                    }
                    continue;
                }

                $streak = 0;
            }
        }

        return false;
    }

    private function activeAlertCount(int $studentId): int
    {
        return DB::table('alerts')
            ->where('student_id', $studentId)
            ->where('resolved', false)
            ->count();
    }

    private function activeStudentCount(int $sectionId): int
    {
        return DB::table('students')
            ->where('section_id', $sectionId)
            ->where('active', true)
            ->count();
    }

    private function groupAverage(int $sectionId, int $subjectId, int $periodId): ?float
    {
        $avg = DB::table('period_grades')
            ->where('period_grades.section_id', $sectionId)
            ->where('period_grades.subject_id', $subjectId)
            ->where('period_grades.period_id', $periodId)
            ->selectRaw('AVG(COALESCE(period_grades.rp_score, period_grades.period_score)) as avg_score')
            ->value('avg_score');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    private function riskScore(?float $averageGrade, float $attendancePct, bool $hasConsecutiveAbsences, int $weakCompetencies, int $activeAlerts): int
    {
        $score = 0;

        if ($averageGrade !== null && $averageGrade < self::ACADEMIC_RISK_THRESHOLD) {
            $score += $averageGrade < self::HIGH_RISK_THRESHOLD ? 40 : 25;
        }

        if ($attendancePct < self::ATTENDANCE_RISK_THRESHOLD) {
            $score += $attendancePct < self::HIGH_ATTENDANCE_RISK_THRESHOLD ? 30 : 18;
        }

        if ($hasConsecutiveAbsences) {
            $score += 20;
        }

        $score += min(30, $weakCompetencies * 10);
        $score += min(20, $activeAlerts * 5);

        return min(100, $score);
    }

    private function riskLevel(int $riskScore): string
    {
        return match (true) {
            $riskScore >= 60 => 'high',
            $riskScore >= 30 => 'medium',
            default => 'low',
        };
    }

    private function courseRiskLevel(int $atRiskCount, int $highRiskCount, ?float $groupAverage, float $attendancePct): string
    {
        if ($highRiskCount > 0 || ($groupAverage !== null && $groupAverage < self::HIGH_RISK_THRESHOLD) || $attendancePct < self::HIGH_ATTENDANCE_RISK_THRESHOLD) {
            return 'high';
        }

        if ($atRiskCount > 0 || ($groupAverage !== null && $groupAverage < self::ACADEMIC_RISK_THRESHOLD) || $attendancePct < self::ATTENDANCE_RISK_THRESHOLD) {
            return 'medium';
        }

        return 'low';
    }

    private function competencyAverage(array $competencies): ?float
    {
        $values = collect($competencies)->filter(fn(?float $score): bool => $score !== null);

        return $values->isEmpty() ? null : round($values->avg(), 2);
    }

    private function criticalCompetency(array $competencies): ?string
    {
        return collect($competencies)
            ->filter(fn(?float $score): bool => $score !== null)
            ->sort()
            ->keys()
            ->first();
    }

    private function healthFromScore(?float $score): ?float
    {
        return $score !== null ? round(max(0, min(100, $score)), 2) : null;
    }

    private function riskReasons(bool $academicRisk, bool $attendanceRisk, bool $competencyRisk, bool $consecutiveAbsenceRisk, int $activeAlerts): array
    {
        $reasons = [];

        if ($academicRisk) {
            $reasons[] = 'academic_performance';
        }

        if ($attendanceRisk) {
            $reasons[] = 'attendance';
        }

        if ($competencyRisk) {
            $reasons[] = 'competency';
        }

        if ($consecutiveAbsenceRisk) {
            $reasons[] = 'consecutive_absences';
        }

        if ($activeAlerts > 0) {
            $reasons[] = 'active_alerts';
        }

        return array_values(array_unique($reasons));
    }
}
