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
        DB::transaction(function () use ($studentId, $academicYearId): void {
            DB::table('students')->where('id', $studentId)->lockForUpdate()->firstOrFail();
            $existing = DB::table('promotion_decisions')->where('student_id', $studentId)
                ->where('academic_year_id', $academicYearId)->orderByDesc('decided_at')->orderByDesc('id')->first();
            if ($existing) {
                if ($existing->status !== 'promoted') {
                    throw new \DomainException('Existe una decisión de no promoción que no puede sobrescribirse automáticamente.');
                }
                return;
            }
            DB::table('promotion_decisions')->insert([
                'student_id' => $studentId, 'academic_year_id' => $academicYearId,
                'status' => 'promoted', 'source' => 'automatic', 'placement_status' => null,
                'decided_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function isPromoted(int $studentId, int $academicYearId): bool
    {
        return DB::table('promotion_decisions')
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->orderByDesc('decided_at')->orderByDesc('id')->value('status') === 'promoted';
    }
}
