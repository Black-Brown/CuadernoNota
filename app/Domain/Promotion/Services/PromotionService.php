<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Services;

use App\Domain\Grade\Entities\FinalGrade;

/**
 * Servicio de dominio que determina si un estudiante es apto
 * para ser promovido al siguiente año escolar.
 *
 * Criterios de promoción (ambos deben cumplirse):
 *  1. El estudiante debe tener una CF >= 70 en TODAS las asignaturas
 *     en las que está inscrito.
 *  2. No debe haber reprobado (CF < 70) ninguna asignatura.
 *
 * Un estudiante sin calificaciones finales registradas, o con registros
 * incompletos (menos asignaturas de las esperadas), NO es promovido.
 */
class PromotionService
{
    /** Nota mínima aprobatoria para aprobar una asignatura. */
    public const MINIMUM_PASSING_GRADE = 70;

    /**
     * Determina si el estudiante cumple los criterios para ser promovido.
     *
     * @param FinalGrade[] $finalGrades        Calificaciones Finales del estudiante
     *                                          (una por cada asignatura).
     * @param int          $expectedSubjectCount Número de asignaturas en las que
     *                                           el estudiante está inscrito.
     *
     * @return bool TRUE si el estudiante puede ser promovido, FALSE en caso contrario.
     */
    public function isEligibleForPromotion(array $finalGrades, int $expectedSubjectCount): bool
    {
        // Sin calificaciones no se puede promover
        if (empty($finalGrades)) {
            return false;
        }

        // Calificaciones incompletas: aún faltan asignaturas por cerrar
        if (count($finalGrades) < $expectedSubjectCount) {
            return false;
        }

        // Cualquier asignatura reprobada bloquea la promoción
        foreach ($finalGrades as $grade) {
            if (!$grade->isPassed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Devuelve las asignaturas reprobadas (CF < 70) del estudiante.
     * Útil para mostrar el detalle en el boletín o informe de promoción.
     *
     * @param FinalGrade[] $finalGrades
     *
     * @return FinalGrade[] Calificaciones de las asignaturas reprobadas.
     */
    public function getFailedSubjects(array $finalGrades): array
    {
        return array_values(
            array_filter($finalGrades, fn(FinalGrade $g): bool => !$g->isPassed())
        );
    }
}
