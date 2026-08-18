<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Entities\PeriodGrade as PeriodGradeEntity;
use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
use App\Infrastructure\Models\FinalGrade as FinalGradeModel;
use App\Infrastructure\Models\Period as PeriodModel;
use App\Infrastructure\Models\PeriodGrade as PeriodGradeModel;
use App\Infrastructure\Models\Student as StudentModel;

class EloquentPeriodGradeRepository implements PeriodGradeRepositoryInterface
{
    /**
     * Inserta o actualiza la nota de período.
     * La unicidad es student_id + subject_id + period_id.
     */
    public function upsert(PeriodGradeEntity $periodGrade): PeriodGradeEntity
    {
        $model = PeriodGradeModel::updateOrCreate(
            [
                'student_id' => $periodGrade->studentId,
                'subject_id' => $periodGrade->subjectId,
                'period_id'  => $periodGrade->periodId,
            ],
            [
                'c1_score'     => $periodGrade->c1Score,
                'c2_score'     => $periodGrade->c2Score,
                'c3_score'     => $periodGrade->c3Score,
                'period_score' => $periodGrade->periodScore,
                'rp_score'     => $periodGrade->rpScore,
                'status'       => $periodGrade->status,
            ]
        );

        return $this->toEntity($model);
    }

    public function findByStudentSubjectPeriod(
        int $studentId,
        int $subjectId,
        int $periodId
    ): ?PeriodGradeEntity {
        $model = PeriodGradeModel::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * Devuelve los PeriodGrade de los 4 períodos de un año,
     * ordenados por número de período ascendente.
     */
    public function findAllPeriodsByStudentSubject(
        int $studentId,
        int $subjectId,
        int $academicYearId
    ): array {
        return PeriodGradeModel::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->whereHas('period', fn($q) => $q->where('academic_year_id', $academicYearId))
            ->with('period')
            ->get()
            ->sortBy(fn($m) => $m->period->number)
            ->map(fn($m) => $this->toEntity($m))
            ->values()
            ->all();
    }

    /** Cambia estado de todas las notas de una asignatura/período a 'in_review'. */
    public function submitForReview(int $subjectId, int $periodId, ?int $sectionId = null): void
    {
        PeriodGradeModel::where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->when($sectionId, fn($query) => $query->whereHas('student', fn($studentQuery) => $studentQuery->where('section_id', $sectionId)))
            ->where('status', 'draft')
            ->update(['status' => 'in_review']);
    }

    /** Aprueba las notas de período, registrando quién y cuándo. */
    public function approve(int $subjectId, int $periodId, int $approvedBy): void
    {
        PeriodGradeModel::where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->where('status', 'in_review')
            ->update([
                'status'      => 'official',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);
    }

    /** Rechaza las notas de período, devolviéndolas a 'draft'. */
    public function reject(int $subjectId, int $periodId): void
    {
        PeriodGradeModel::where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->where('status', 'in_review')
            ->update([
                'status'      => 'draft',
                'approved_by' => null,
                'approved_at' => null,
            ]);
    }

    public function findAllBySubjectPeriod(int $subjectId, int $periodId, ?int $sectionId = null): array
    {
        $grades = PeriodGradeModel::where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->when($sectionId, fn($query) => $query->whereHas('student', fn($studentQuery) => $studentQuery->where('section_id', $sectionId)))
            ->with('student:id,name,last_name')
            ->get();

        return $grades->map(fn($g) => [
            'student_id'     => $g->student_id,
            'student_name'   => $g->student
                ? $g->student->last_name . ', ' . $g->student->name
                : 'Estudiante ' . $g->student_id,
            'c1_score'       => $g->c1_score,
            'c2_score'       => $g->c2_score,
            'c3_score'       => $g->c3_score,
            'period_score'   => $g->period_score,
            'rp_score'       => $g->rp_score,
            'effective_score' => $g->rp_score ?? $g->period_score,
            'status'         => $g->status,
        ])->sortBy('student_name')->values()->all();
    }

    public function findGradebookSummary(
        int $sectionId,
        int $subjectId,
        int $academicYearId,
        array $course = []
    ): array {
        $periods = PeriodModel::where('academic_year_id', $academicYearId)
            ->orderBy('number')
            ->get(['id', 'number', 'name', 'months', 'status']);

        $students = StudentModel::where('section_id', $sectionId)
            ->where('active', true)
            ->orderBy('last_name')
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'enrollment_no']);

        $studentIds = $students->pluck('id')->all();
        $periodIds = $periods->pluck('id')->all();

        $periodGrades = PeriodGradeModel::whereIn('student_id', $studentIds)
            ->where('subject_id', $subjectId)
            ->whereIn('period_id', $periodIds)
            ->get()
            ->groupBy('student_id')
            ->map(fn($grades) => $grades->keyBy('period_id'));

        $finalGrades = FinalGradeModel::whereIn('student_id', $studentIds)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->keyBy('student_id');

        $rows = $students->map(function (StudentModel $student) use ($periods, $periodGrades, $finalGrades) {
            $gradesByPeriod = $periodGrades->get($student->id, collect());
            $finalGrade = $finalGrades->get($student->id);
            $periodPayload = [];
            $effectiveScores = [];
            $statuses = [];

            foreach ($periods as $period) {
                $grade = $gradesByPeriod->get($period->id);
                $effectiveScore = $grade ? ($grade->rp_score ?? $grade->period_score) : null;

                if ($effectiveScore !== null) {
                    $effectiveScores[] = (float) $effectiveScore;
                }

                if ($grade?->status) {
                    $statuses[] = $grade->status;
                }

                $periodPayload[] = [
                    'period_id'       => $period->id,
                    'period_number'   => $period->number,
                    'period_name'     => $period->name,
                    'c1_score'        => $grade?->c1_score,
                    'c2_score'        => $grade?->c2_score,
                    'c3_score'        => $grade?->c3_score,
                    'period_score'    => $grade?->period_score,
                    'rp_score'        => $grade?->rp_score,
                    'effective_score' => $effectiveScore,
                    'status'          => $grade?->status ?? 'pending',
                ];
            }

            $calculatedCf = count($effectiveScores) === 4
                ? (int) round(array_sum($effectiveScores) / 4)
                : null;

            $cf = $finalGrade?->cf ?? $calculatedCf;
            $status = $this->resolveGradebookStatus($statuses);

            return [
                'student_id'       => $student->id,
                'student_name'     => trim($student->last_name . ', ' . $student->name),
                'enrollment_no'    => $student->enrollment_no,
                'periods'          => $periodPayload,
                'cf'               => $cf,
                'pc'               => $cf,
                'final_recovery'   => $finalGrade?->final_recovery,
                'special_recovery' => $finalGrade?->special_recovery,
                'status'           => $status,
                'at_risk'          => $cf !== null && $cf < 70,
            ];
        })->values();

        $completedRows = $rows->filter(fn($row) => $row['cf'] !== null);
        $atRiskRows = $rows->filter(fn($row) => $row['at_risk']);

        return [
            'course' => [
                'section_id'       => $sectionId,
                'subject_id'       => $subjectId,
                'academic_year_id' => $academicYearId,
                'grade_name'       => $course['grade_name'] ?? null,
                'section_name'     => $course['section_name'] ?? null,
                'subject_name'     => $course['subject_name'] ?? null,
                'year_label'       => $course['year_label'] ?? null,
            ],
            'periods' => $periods->map(fn($period) => [
                'id'     => $period->id,
                'number' => $period->number,
                'name'   => $period->name,
                'months' => $period->months,
                'status' => $period->status,
            ])->values()->all(),
            'students' => $rows->all(),
            'summary' => [
                'total_students' => $rows->count(),
                'completed'      => $completedRows->count(),
                'pending'        => $rows->count() - $completedRows->count(),
                'at_risk'        => $atRiskRows->count(),
                'class_average'  => $completedRows->count() > 0
                    ? round($completedRows->avg('cf'), 1)
                    : null,
            ],
        ];
    }

    private function resolveGradebookStatus(array $statuses): string
    {
        if (empty($statuses)) {
            return 'pending';
        }

        if (in_array('draft', $statuses, true)) {
            return 'draft';
        }

        if (in_array('in_review', $statuses, true)) {
            return 'in_review';
        }

        return 'official';
    }

    /** Convierte el modelo Eloquent a la entidad de dominio PeriodGrade. */
    private function toEntity(PeriodGradeModel $model): PeriodGradeEntity
    {
        return new PeriodGradeEntity(
            studentId:   $model->student_id,
            subjectId:   $model->subject_id,
            periodId:    $model->period_id,
            c1Score:     (float) ($model->c1_score ?? 0),
            c2Score:     (float) ($model->c2_score ?? 0),
            c3Score:     (float) ($model->c3_score ?? 0),
            periodScore: (float) ($model->period_score ?? 0),
            rpScore:     $model->rp_score !== null ? (float) $model->rp_score : null,
            status:      $model->status,
        );
    }
}
