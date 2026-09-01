<?php

namespace App\Domain\Grade\Services;

/**
 * Servicio de dominio responsable del Paso 1 del sistema de evaluación.
 *
 * Calcula la nota de una competencia (C1, C2 o C3) a partir de las
 * notas de actividades registradas por el docente.
 *
 * Regla de negocio: solo se promedian las actividades que tienen
 * nota registrada (score !== null). Las actividades vacías se
 * ignoran completamente — no cuentan ni en la suma ni en el divisor.
 *
 * Ejemplo: si hay 6 actividades pero el docente solo registró
 * nota en 5, el promedio se calcula sobre esas 5, no sobre 6.
 */
class CompetencyCalculator
{
    /**
     * Calcula la nota de una competencia específica.
     *
     * @param array $scores Array de notas de actividades. Cada elemento
     *                       debe tener la forma:
     *                       ['competency_id' => int, 'score' => float|null]
     * @param int   $competencyId ID de la competencia a calcular: 1=C1, 2=C2, 3=C3
     *
     * @return float|null Promedio de las actividades con nota (0-100),
     *                     redondeado a 2 decimales. Devuelve NULL si
     *                     ninguna actividad de esta competencia tiene nota.
     */
    public function calculate(array $scores, int $competencyId): ?float
    {
        // Filtrar solo las actividades de esta competencia que tienen nota
        $filtered = array_filter(
            $scores,
            fn(array $s): bool => $s['competency_id'] === $competencyId
                && $s['score'] !== null
        );

        // Si ninguna actividad tiene nota, no se puede calcular
        if (empty($filtered)) {
            return null;
        }

        $sum   = array_sum(array_column($filtered, 'score'));
        $count = count($filtered);

        return round($sum / $count, 2);
    }
}