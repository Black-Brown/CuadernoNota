<?php

namespace Tests\Unit\Domain\Grade;

use Tests\TestCase;
use App\Domain\Grade\Services\CompetencyCalculator;

/**
 * Tests del CompetencyCalculator — Paso 1 del sistema de evaluación.
 *
 * Verifica que la nota de una competencia se calcula correctamente
 * como el promedio de las actividades CON NOTA, ignorando las vacías.
 */
class CompetencyCalculatorTest extends TestCase
{
    private CompetencyCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CompetencyCalculator();
    }

    /**
     * Caso del Excel de la directora: 5 actividades con nota
     * para C1 → (80+90+80+70+80)/5 = 80
     */
    public function test_calcula_promedio_de_actividades_con_nota(): void
    {
        $scores = [
            ['competency_id' => 1, 'score' => 80],
            ['competency_id' => 1, 'score' => 90],
            ['competency_id' => 1, 'score' => 80],
            ['competency_id' => 1, 'score' => 70],
            ['competency_id' => 1, 'score' => 80],
        ];

        $result = $this->calculator->calculate($scores, 1);

        $this->assertEquals(80.0, $result);
    }

    /**
     * Regla de negocio clave: si hay 6 actividades pero solo 5
     * tienen nota, el promedio se calcula sobre 5, no sobre 6.
     */
    public function test_ignora_actividades_sin_nota_en_el_promedio(): void
    {
        $scores = [
            ['competency_id' => 1, 'score' => 90],
            ['competency_id' => 1, 'score' => 70],
            ['competency_id' => 1, 'score' => 90],
            ['competency_id' => 1, 'score' => 80],
            ['competency_id' => 1, 'score' => 70],
            ['competency_id' => 1, 'score' => null], // 6ta actividad, sin nota
        ];

        $result = $this->calculator->calculate($scores, 1);

        // (90+70+90+80+70) / 5 = 80, NO /6
        $this->assertEquals(80.0, $result);
    }

    /**
     * Si ninguna actividad de la competencia tiene nota,
     * no se puede calcular — devuelve NULL.
     */
    public function test_retorna_null_si_todas_las_actividades_estan_vacias(): void
    {
        $scores = [
            ['competency_id' => 1, 'score' => null],
            ['competency_id' => 1, 'score' => null],
        ];

        $result = $this->calculator->calculate($scores, 1);

        $this->assertNull($result);
    }

    /**
     * Solo deben considerarse las actividades de la competencia
     * solicitada — actividades de C2 o C3 no deben mezclarse.
     */
    public function test_filtra_solo_la_competencia_solicitada(): void
    {
        $scores = [
            ['competency_id' => 1, 'score' => 100], // C1
            ['competency_id' => 2, 'score' => 50],  // C2 — no debe contar
            ['competency_id' => 1, 'score' => 80],  // C1
        ];

        $result = $this->calculator->calculate($scores, 1);

        // (100+80)/2 = 90, ignorando el 50 de C2
        $this->assertEquals(90.0, $result);
    }

    /**
     * Redondeo a 2 decimales cuando el promedio no es exacto.
     */
    public function test_redondea_a_dos_decimales(): void
    {
        $scores = [
            ['competency_id' => 1, 'score' => 80],
            ['competency_id' => 1, 'score' => 100],
            ['competency_id' => 1, 'score' => 95],
        ];

        $result = $this->calculator->calculate($scores, 1);

        // (80+100+95)/3 = 91.6666... → 91.67
        $this->assertEquals(91.67, $result);
    }
}