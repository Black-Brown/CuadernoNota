<?php

namespace App\Domain\Grade\Services;

use App\Domain\Grade\Entities\PeriodGrade;
use InvalidArgumentException;

/**
 * Servicio de dominio responsable del cálculo de la
 * Calificación Final (CF) de una asignatura al cierre del año escolar.
 *
 * El CF es el promedio de las notas efectivas de los 4 períodos
 * académicos, redondeado al entero más cercano.
 *
 * "Nota efectiva" significa: si el período tiene una Recuperación
 * Pedagógica (rp_score), esa nota sustituye a period_score en
 * el cálculo. Esto lo determina PeriodGrade::effectiveScore().
 *
 * Fórmula: CF = ROUND( (efectiva_P1 + efectiva_P2 + efectiva_P3 + efectiva_P4) / 4 )
 */
class CFCalculator
{
    /**
     * Calcula el CF a partir de los 4 períodos del año escolar.
     *
     * @param PeriodGrade[] $periodGrades Array con exactamente 4 instancias
     *                                     de PeriodGrade, una por cada
     *                                     período académico (1, 2, 3 y 4).
     *
     * @return int Calificación Final, redondeada al entero más cercano.
     *              Escala 0-100.
     *
     * @throws InvalidArgumentException Si el array no contiene
     *                                   exactamente 4 períodos.
     */
    public function calculate(array $periodGrades): int
    {
        if (count($periodGrades) !== 4) {
            throw new InvalidArgumentException(
                'Se requieren exactamente 4 períodos para calcular el CF. ' .
                'Recibidos: ' . count($periodGrades)
            );
        }

        // Suma de las notas efectivas (RP si existe, period_score si no)
        $sum = array_sum(
            array_map(
                fn(PeriodGrade $pg): float => $pg->effectiveScore(),
                $periodGrades
            )
        );

        return (int) round($sum / 4);
    }
}