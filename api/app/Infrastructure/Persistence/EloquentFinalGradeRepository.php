<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Entities\FinalGrade as FinalGradeEntity;
use App\Domain\Grade\Repositories\FinalGradeRepositoryInterface;
use App\Infrastructure\Models\FinalGrade as FinalGradeModel;

class EloquentFinalGradeRepository implements FinalGradeRepositoryInterface
{
    /**
     * Inserta o actualiza la Calificación Final.
     * La unicidad es student_id + subject_id + academic_year_id.
     */
    public function upsert(FinalGradeEntity $finalGrade): FinalGradeEntity
    {
        $model = FinalGradeModel::updateOrCreate(
            [
                'student_id'      => $finalGrade->studentId,
                'subject_id'      => $finalGrade->subjectId,
                'academic_year_id' => $finalGrade->academicYearId,
            ],
            [
                'cf'               => $finalGrade->cf,
                'final_recovery'   => $finalGrade->finalRecovery,
                'special_recovery' => $finalGrade->specialRecovery,
            ]
        );

        return $this->toEntity($model);
    }

    public function findByStudentSubjectYear(
        int $studentId,
        int $subjectId,
        int $academicYearId
    ): ?FinalGradeEntity {
        $model = FinalGradeModel::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /** Registra la Recuperación Final en el campo final_recovery. */
    public function registerFinalRecovery(
        int   $studentId,
        int   $subjectId,
        int   $academicYearId,
        float $score
    ): void {
        FinalGradeModel::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->update(['final_recovery' => $score]);
    }

    /** Registra la Recuperación Especial en el campo special_recovery. */
    public function registerSpecialRecovery(
        int   $studentId,
        int   $subjectId,
        int   $academicYearId,
        float $score
    ): void {
        FinalGradeModel::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->update(['special_recovery' => $score]);
    }

    /** Convierte el modelo Eloquent a la entidad de dominio FinalGrade. */
    private function toEntity(FinalGradeModel $model): FinalGradeEntity
    {
        return new FinalGradeEntity(
            studentId:       $model->student_id,
            subjectId:       $model->subject_id,
            academicYearId:  $model->academic_year_id,
            cf:              (int) $model->cf,
            finalRecovery:   $model->final_recovery !== null ? (float) $model->final_recovery : null,
            specialRecovery: $model->special_recovery !== null ? (float) $model->special_recovery : null,
        );
    }
}
