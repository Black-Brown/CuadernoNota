<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Entities\PeriodGrade;
use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;
use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
use App\Domain\Grade\Services\CompetencyCalculator;
use App\Domain\Grade\Services\GradeCalculator;

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
     * @param PeriodGradeRepositoryInterface   $periodGradeRepo   Repositorio de notas por período
     * @param CompetencyCalculator             $competencyCalc    Servicio del Paso 1
     * @param GradeCalculator                  $gradeCalc         Servicio del Paso 2
     */
    public function __construct(
        private readonly ActivityScoreRepositoryInterface $activityScoreRepo,
        private readonly PeriodGradeRepositoryInterface   $periodGradeRepo,
        private readonly CompetencyCalculator              $competencyCalc,
        private readonly GradeCalculator                   $gradeCalc,
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

        // Paso 1b: recuperar todas las notas del período para recalcular
        $allScores = $this->activityScoreRepo->findByStudentSubjectPeriod(
            $scoreData['student_id'],
            $scoreData['subject_id'],
            $scoreData['period_id'],
        );

        // Paso 1 — Calcular nota de cada competencia
        $c1 = $this->competencyCalc->calculate($allScores, 1);
        $c2 = $this->competencyCalc->calculate($allScores, 2);
        $c3 = $this->competencyCalc->calculate($allScores, 3);

        // Si alguna competencia queda sin notas, el cálculo anterior deja de ser válido.
        if ($c1 === null || $c2 === null || $c3 === null) {
            $this->periodGradeRepo->deleteByStudentSubjectPeriod(
                $scoreData['student_id'],
                $scoreData['subject_id'],
                $scoreData['period_id'],
            );

            return null;
        }

        // Paso 2 — Calcular nota del período
        $periodScore = $this->gradeCalc->calculate($c1, $c2, $c3);

        // Recuperar el PeriodGrade existente para preservar rpScore y status
        $existing = $this->periodGradeRepo->findByStudentSubjectPeriod(
            $scoreData['student_id'],
            $scoreData['subject_id'],
            $scoreData['period_id'],
        );

        $periodGrade = new PeriodGrade(
            studentId:   $scoreData['student_id'],
            subjectId:   $scoreData['subject_id'],
            periodId:    $scoreData['period_id'],
            c1Score:     $c1,
            c2Score:     $c2,
            c3Score:     $c3,
            periodScore: $periodScore,
            rpScore:     $existing?->rpScore,
            status:      $existing?->status ?? 'draft',
            sectionId:   isset($scoreData['section_id']) ? (int) $scoreData['section_id'] : $existing?->sectionId,
        );

        return $this->periodGradeRepo->upsert($periodGrade);
    }
}
