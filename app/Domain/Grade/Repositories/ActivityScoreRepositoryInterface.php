<?php

namespace App\Domain\Grade\Repositories;

/**
 * Contrato del repositorio de notas de actividades.
 *
 * El dominio define QUÉ operaciones necesita, sin saber CÓMO
 * se implementan. La implementación concreta (Eloquent, PDO, etc.)
 * vive en Infrastructure/Persistence y se inyecta vía
 * AppServiceProvider.
 *
 * Corresponde a la tabla activity_scores.
 */
interface ActivityScoreRepositoryInterface
{
    /**
     * Guarda (o actualiza si ya existe) la nota de una actividad
     * para un estudiante en una competencia y período específicos.
     *
     * La unicidad se determina por la combinación:
     * activity_id + student_id + competency_id + period_id + subject_id
     *
     * @param array $data Debe contener las claves:
     *                     activity_id, student_id, competency_id,
     *                     period_id, subject_id, score
     */
    public function save(array $data): void;

    /**
     * Obtiene todas las notas de actividades de un estudiante
     * para una asignatura en un período específico.
     *
     * Se usa para recalcular C1, C2, C3 y period_score (Paso 2)
     * cada vez que se guarda una nueva nota.
     *
     * @param int $studentId ID del estudiante
     * @param int $subjectId ID de la asignatura
     * @param int $periodId  ID del período académico
     *
     * @return array Array de notas con la forma:
     *                [['competency_id' => int, 'score' => float|null], ...]
     */
    public function findByStudentSubjectPeriod(
        int $studentId,
        int $subjectId,
        int $periodId
    ): array;
}