<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Entities\PeriodGrade as PeriodGradeEntity;
use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
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
    public function submitForReview(int $subjectId, int $periodId): void
    {
        PeriodGradeModel::where('subject_id', $subjectId)
            ->where('period_id', $periodId)
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

    public function findAllBySubjectPeriod(int $subjectId, int $periodId): array
    {
        $grades = PeriodGradeModel::where('subject_id', $subjectId)
            ->where('period_id', $periodId)
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
