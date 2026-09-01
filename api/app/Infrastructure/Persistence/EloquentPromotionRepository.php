<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Entities\FinalGrade as FinalGradeEntity;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use App\Infrastructure\Models\FinalGrade as FinalGradeModel;
use Illuminate\Support\Facades\DB;

class EloquentPromotionRepository implements PromotionRepositoryInterface
{
    /**
     * Devuelve todas las Calificaciones Finales de un estudiante
     * en el año escolar dado (una por asignatura).
     *
     * @return FinalGradeEntity[]
     */
    public function findFinalGradesByStudentYear(int $studentId, int $academicYearId): array
    {
        return FinalGradeModel::where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->map(fn($m) => new FinalGradeEntity(
                studentId:       $m->student_id,
                subjectId:       $m->subject_id,
                academicYearId:  $m->academic_year_id,
                cf:              (int) $m->cf,
                finalRecovery:   $m->final_recovery !== null ? (float) $m->final_recovery : null,
                specialRecovery: $m->special_recovery !== null ? (float) $m->special_recovery : null,
            ))
            ->all();
    }

    /**
     * Registra al estudiante como promovido.
     * Usa upsert para que sea idempotente.
     */
    public function markAsPromoted(int $studentId, int $academicYearId): void
    {
        DB::table('student_promotions')->updateOrInsert(
            ['student_id' => $studentId, 'academic_year_id' => $academicYearId],
            ['promoted_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function isPromoted(int $studentId, int $academicYearId): bool
    {
        return DB::table('student_promotions')
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }
}
