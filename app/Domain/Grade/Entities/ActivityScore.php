<?php

namespace App\Domain\Grade\Entities;

/**
 * Entidad que representa la nota de un estudiante
 * en una actividad específica para una competencia en un período.
 *
 * Es la unidad mínima de evaluación del sistema.
 * Una fila en activity_scores = un estudiante × una actividad
 * × una competencia (C1, C2 o C3) × un período.
 *
 * El score puede ser NULL si la actividad no se usó
 * para ese estudiante — las actividades NULL se ignoran
 * completamente en el cálculo del CompetencyCalculator.
 */
class ActivityScore
{
    /**
     * @param int        $activityId   ID de la actividad (base o adicional del docente)
     * @param int        $studentId    ID del estudiante
     * @param int        $competencyId ID de la competencia: 1=C1, 2=C2, 3=C3
     * @param int        $periodId     ID del período académico
     * @param int        $subjectId    ID de la asignatura
     * @param float|null $score        Nota de 0 a 100. NULL si la actividad no se usó en este período
     */
    public function __construct(
        public readonly int    $activityId,
        public readonly int    $studentId,
        public readonly int    $competencyId,
        public readonly int    $periodId,
        public readonly int    $subjectId,
        public readonly ?float $score = null,
    ) {}

    /**
     * Indica si esta actividad tiene una nota registrada.
     * Las actividades sin nota (NULL) se ignoran en el
     * cálculo del promedio de la competencia.
     */
    public function hasScore(): bool
    {
        return $this->score !== null;
    }

    /**
     * Valida que la nota esté dentro del rango permitido (0-100).
     * Las actividades sin nota (NULL) siempre son válidas.
     */
    public function isValidScore(): bool
    {
        if ($this->score === null) return true;
        return $this->score >= 0 && $this->score <= 100;
    }
}