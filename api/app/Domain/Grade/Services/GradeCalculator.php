<?php

namespace App\Domain\Grade\Services;

/**
 * Servicio de dominio responsable del Paso 2 del sistema de evaluación.
 *
 * Calcula la nota del período por asignatura, a partir de las
 * notas ya calculadas de las 4 competencias (C1, C2, C3, C4).
 *
 * Esta nota del período (period_score) ES la que aparece en el
 * boletín oficial como P1, P2, P3 o P4, según corresponda al período.
 *
 * Fórmula: period_score = (C1 + C2 + C3 + C4) / 4
 *
 * Este cálculo es completamente automático — se ejecuta cada vez
 * que el docente guarda una nota de actividad (ver CU-26).
 */
class GradeCalculator
{
    /**
     * Calcula la nota del período a partir de las 4 competencias.
     *
     * @param float $c1 Nota de C1 (Comunicativa), escala 0-100
     * @param float $c2 Nota de C2 (Pensamiento Lógico), escala 0-100
     * @param float $c3 Nota de C3 (Científica), escala 0-100
     * @param float $c4 Nota de C4 (Ética y ciudadanía + Desarrollo personal y espiritual), escala 0-100
     *
     * @return float Nota del período = (C1+C2+C3+C4)/4, redondeada a 2 decimales.
     *                Escala 0-100. Esta es P1, P2, P3 o P4 en el boletín.
     */
    public function calculate(float $c1, float $c2, float $c3, float $c4): float
    {
        return round(($c1 + $c2 + $c3 + $c4) / 4, 2);
    }
}
