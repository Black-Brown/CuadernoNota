<?php

namespace App\Domain\Grade\Entities;

/**
 * Entidad que representa la calificación de un estudiante
 * en una asignatura durante un período académico.
 *
 * Contiene las notas calculadas de las 3 competencias (C1, C2, C3),
 * la nota del período (period_score = promedio de C1+C2+C3),
 * y opcionalmente una Recuperación Pedagógica (rp_score).
 *
 * Esta entidad es el resultado del Paso 2 automático del sistema.
 * Se persiste en la tabla period_grades.
 */
class PeriodGrade
{
    /**
     * @param int                   $studentId    ID del estudiante
     * @param int                   $subjectId    ID de la asignatura
     * @param int                   $periodId     ID del período (1, 2, 3 o 4)
     * @param float                 $c1Score      Nota de C1 (Comunicativa) — promedio de actividades con nota
     * @param float                 $c2Score      Nota de C2 (Pensamiento Lógico) — promedio de actividades con nota
     * @param float                 $c3Score      Nota de C3 (Científica) — promedio de actividades con nota
     * @param float                 $periodScore  Nota del período = (C1 + C2 + C3) / 3. Es P1, P2, P3 o P4 en el boletín
     * @param float|null            $rpScore      Recuperación Pedagógica. Si existe, sustituye a periodScore en el CF. NULL si no aplica
     * @param string                $status       Estado: draft | in_review | official
     */

    public function __construct(
        public readonly int    $studentId,
        public readonly int    $subjectId,
        public readonly int    $periodId,
        public readonly float  $c1Score,
        public readonly float  $c2Score,
        public readonly float  $c3Score,
        public readonly float  $periodScore,
        public readonly ?float $rpScore = null,
        public readonly string $status = 'draft',
    ) {}

    /**
     * Devuelve la nota efectiva del período para el cálculo del CF.
     * Si existe Recuperación Pedagógica (rp_score), esta sustituye
     * a period_score. Si no existe, se usa period_score directamente.
     */
    public function effectiveScore(): float
    {
        return $this->rpScore ?? $this->periodScore;
    }

    /**
     * Indica si el estudiante está en riesgo de reprobar esta asignatura
     * en este período. El umbral de riesgo es una nota menor a 70.
     */
    public function isAtRisk(): bool
    {
        return $this->effectiveScore() < 70;
    }

    /**
     * Indica si las calificaciones de este período ya fueron
     * aprobadas por el Coordinador o Administrador.
     * Solo las notas con status='official' aparecen en el boletín.
     */
    public function isOfficial(): bool
    {
        return $this->status === 'official';
    }
}