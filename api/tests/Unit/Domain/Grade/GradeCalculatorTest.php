<?php

namespace Tests\Unit\Domain\Grade;

use Tests\TestCase;
use App\Domain\Grade\Services\GradeCalculator;

/**
 * Tests del GradeCalculator — Paso 2 del sistema de evaluación.
 *
 * Verifica que la nota del período se calcula correctamente
 * como el promedio simple de C1, C2, C3 y C4.
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
     * C1=80, C2=100, C3=95, C4=65 → promedio = 85.0
     */
    public function test_calcula_promedio_de_las_cuatro_competencias(): void
    {
        $result = $this->calculator->calculate(c1: 80, c2: 100, c3: 95, c4: 65);

        $this->assertEquals(85.0, $result);
    }

    /**
     * Cuando las 4 competencias tienen la misma nota,
     * el resultado debe ser esa misma nota.
     */
    public function test_promedio_con_notas_iguales(): void
    {
        $result = $this->calculator->calculate(c1: 75, c2: 75, c3: 75, c4: 75);

        $this->assertEquals(75.0, $result);
    }

    /**
     * Redondeo correcto a 2 decimales en casos no exactos.
     */
    public function test_redondea_a_dos_decimales(): void
    {
        $result = $this->calculator->calculate(c1: 70, c2: 80, c3: 85, c4: 82.25);

        // (70+80+85+82.25)/4 = 79.3125 → 79.31
        $this->assertEquals(79.31, $result);
    }

    /**
     * Caso límite: las 4 competencias en 0 → resultado 0.
     */
    public function test_promedio_minimo(): void
    {
        $result = $this->calculator->calculate(c1: 0, c2: 0, c3: 0, c4: 0);

        $this->assertEquals(0.0, $result);
    }

    /**
     * Caso límite: las 4 competencias en 100 → resultado 100.
     */
    public function test_promedio_maximo(): void
    {
        $result = $this->calculator->calculate(c1: 100, c2: 100, c3: 100, c4: 100);

        $this->assertEquals(100.0, $result);
    }
}
