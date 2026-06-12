<?php

namespace App\Domain\Grade\Entities;

/**
 * Entidad que representa la Calificación Final (CF) de un estudiante
 * en una asignatura al cierre del año escolar.
 *
 * El CF se calcula como el promedio de los 4 period_scores
 * (o rp_scores si existen), redondeado al entero más cercano.
 * Se persiste en la tabla final_grades.
 *
 * Adicionalmente puede tener una Recuperación Final (al cierre del año)
 * o una Recuperación Especial (caso excepcional aprobado por dirección).
 */
class FinalGrade
{
    /**
     * @param int           $studentId       ID del estudiante
     * @param int           $subjectId       ID de la asignatura
     * @param int           $academicYearId  ID del año escolar
     * @param int           $cf              Calificación Final = promedio de 4 períodos, redondeado al entero. Escala 0-100
     * @param float|null    $finalRecovery   Nota de Recuperación Final al cierre del año. NULL si no aplica
     * @param float|null    $specialRecovery Nota de Recuperación Especial aprobada por dirección. NULL si no aplica
     */
    public function __construct(
        public readonly int    $studentId,
        public readonly int    $subjectId,
        public readonly int    $academicYearId,
        public readonly int    $cf,
        public readonly ?float $finalRecovery   = null,
        public readonly ?float $specialRecovery = null,
    ) {}

    /**
     * Indica si el estudiante aprobó la asignatura.
     * La nota mínima aprobatoria es 70.
     */
    public function isPassed(): bool
    {
        return $this->cf >= 70;
    }

    /**
     * Indica si el estudiante tiene algún tipo de recuperación
     * registrada (Final o Especial).
     */
    public function hasRecovery(): bool
    {
        return $this->finalRecovery !== null
            || $this->specialRecovery !== null;
    }

    /**
     * Devuelve la nota efectiva final del estudiante en la asignatura,
     * considerando las recuperaciones en este orden de prioridad:
     * 1. Recuperación Especial (si existe)
     * 2. Recuperación Final (si existe)
     * 3. CF original
     */
    public function effectiveCF(): float
    {
        if ($this->specialRecovery !== null) {
            return $this->specialRecovery;
        }

        if ($this->finalRecovery !== null) {
            return $this->finalRecovery;
        }

        return $this->cf;
    }
}