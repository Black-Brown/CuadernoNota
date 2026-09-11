<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Entities\PeriodGrade;
use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;

/**
 * Caso de uso: Registrar la nota de una actividad para un estudiante.
 *
 * Orquesta el Paso 1 y el Paso 2 del sistema de evaluación de forma
 * automática cada vez que el docente guarda o actualiza una nota.
 *
 * Flujo:
 *  1. Persiste la nota de la actividad vía ActivityScoreRepository.
 *  2. Recupera todas las notas del estudiante en esa asignatura/período.
 *  3. Paso 1 — Recalcula C1, C2 y C3 con CompetencyCalculator.
 *  4. Paso 2 — Recalcula la nota del período con GradeCalculator.
 *  5. Actualiza (upsert) el PeriodGrade correspondiente.
 *
 * Si alguna de las tres competencias no tiene actividades con nota,
 * el Paso 2 no se puede ejecutar. En ese caso se elimina cualquier
 * PeriodGrade anterior para no conservar cálculos desactualizados.
 */
class RegisterActivityScore
{
    /**
     * @param ActivityScoreRepositoryInterface $activityScoreRepo Repositorio de notas de actividades
     * @param RecalculatePeriodGrade           $recalculatePeriodGrade Recalcula el resumen del período
     */
    public function __construct(
        private readonly ActivityScoreRepositoryInterface $activityScoreRepo,
        private readonly RecalculatePeriodGrade $recalculatePeriodGrade,
    ) {}

    /**
     * Registra o actualiza la nota de una actividad y recalcula
     * automáticamente las notas de competencia y de período.
     *
     * @param array $scoreData Datos de la nota. Debe contener:
     *                          - student_id  (int)
     *                          - subject_id  (int)
     *                          - period_id   (int, 1-4)
     *                          - activity_id (int)
     *                          - competency_id (int, 1=C1, 2=C2, 3=C3)
     *                          - score       (float|null)
     *
     * @return PeriodGrade|null PeriodGrade actualizado, o NULL si alguna
     *                           competencia aún no tiene actividades con nota.
     */
    public function execute(array $scoreData): ?PeriodGrade
    {
        // Paso 1a: guardar la nota
        $this->activityScoreRepo->save($scoreData);

        return $this->recalculatePeriodGrade->execute(
            studentId: (int) $scoreData['student_id'],
            subjectId: (int) $scoreData['subject_id'],
            periodId: (int) $scoreData['period_id'],
            sectionId: isset($scoreData['section_id']) ? (int) $scoreData['section_id'] : null,
        );
    }
}
