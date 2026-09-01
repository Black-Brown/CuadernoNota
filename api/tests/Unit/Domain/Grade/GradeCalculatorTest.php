<?php

namespace Tests\Unit\Domain\Grade;

use Tests\TestCase;
use App\Domain\Grade\Services\GradeCalculator;

/**
 * Tests del GradeCalculator — Paso 2 del sistema de evaluación.
 *
 * Verifica que la nota del período se calcula correctamente
 * como el promedio simple de C1, C2 y C3.
 */
class GradeCalculatorTest extends TestCase
{
    private GradeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GradeCalculator();
    }

    /**
     * Caso del Excel de la directora: Lengua Española Período 1
     * C1=80, C2=100, C3=95 → promedio = 91.67
     */
    public function test_calcula_promedio_de_las_tres_competencias(): void
    {
        $result = $this->calculator->calculate(c1: 80, c2: 100, c3: 95);

        $this->assertEquals(91.67, $result);
    }

    /**
     * Cuando las 3 competencias tienen la misma nota,
     * el resultado debe ser esa misma nota.
     */
    public function test_promedio_con_notas_iguales(): void
    {
        $result = $this->calculator->calculate(c1: 75, c2: 75, c3: 75);

        $this->assertEquals(75.0, $result);
    }

    /**
     * Redondeo correcto a 2 decimales en casos no exactos.
     */
    public function test_redondea_a_dos_decimales(): void
    {
        $result = $this->calculator->calculate(c1: 70, c2: 80, c3: 85);

        // (70+80+85)/3 = 78.3333... → 78.33
        $this->assertEquals(78.33, $result);
    }

    /**
     * Caso límite: las 3 competencias en 0 → resultado 0.
     */
    public function test_promedio_minimo(): void
    {
        $result = $this->calculator->calculate(c1: 0, c2: 0, c3: 0);

        $this->assertEquals(0.0, $result);
    }

    /**
     * Caso límite: las 3 competencias en 100 → resultado 100.
     */
    public function test_promedio_maximo(): void
    {
        $result = $this->calculator->calculate(c1: 100, c2: 100, c3: 100);

        $this->assertEquals(100.0, $result);
    }
}