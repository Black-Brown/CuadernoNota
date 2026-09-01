<?php

namespace Tests\Unit\Domain\Grade;

use Tests\TestCase;
use InvalidArgumentException;
use App\Domain\Grade\Services\CFCalculator;
use App\Domain\Grade\Entities\PeriodGrade;

/**
 * Tests del CFCalculator — Calificación Final del área.
 *
 * Verifica que el CF se calcula como el promedio de las notas
 * efectivas (RP si existe, period_score si no) de los 4 períodos,
 * redondeado al entero más cercano.
 */
class CFCalculatorTest extends TestCase
{
    private CFCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CFCalculator();
    }

    /**
     * Crea un PeriodGrade de prueba con valores mínimos necesarios.
     */
    private function makePeriod(
        float $periodScore,
        ?float $rpScore = null
    ): PeriodGrade {
        return new PeriodGrade(
            studentId:   1,
            subjectId:   1,
            periodId:    1,
            c1Score:     0,
            c2Score:     0,
            c3Score:     0,
            periodScore: $periodScore,
            rpScore:     $rpScore,
        );
    }

    /**
     * Caso normal: 4 períodos sin recuperaciones.
     * CF = promedio redondeado al entero.
     */
    public function test_calcula_cf_redondeado_al_entero(): void
    {
        $periods = [
            $this->makePeriod(91.67),
            $this->makePeriod(78.8),
            $this->makePeriod(82.5),
            $this->makePeriod(78.5),
        ];

        $cf = $this->calculator->calculate($periods);

        // (91.67+78.8+82.5+78.5)/4 = 82.8675 → 83
        $this->assertEquals(83, $cf);
    }

    /**
     * Regla de negocio clave: si un período tiene RP, la RP
     * sustituye a la period_score original en el cálculo del CF.
     */
    public function test_rp_sustituye_period_score_en_el_cf(): void
    {
        $periods = [
            $this->makePeriod(periodScore: 50.0, rpScore: 80.0), // RP=80 sustituye a 50
            $this->makePeriod(periodScore: 90.0),
            $this->makePeriod(periodScore: 85.0),
            $this->makePeriod(periodScore: 88.0),
        ];

        $cf = $this->calculator->calculate($periods);

        // (80+90+85+88)/4 = 85.75 → 86 (usa 80, no 50)
        $this->assertEquals(86, $cf);
    }

    /**
     * Redondeo hacia abajo cuando el decimal es menor a 0.5.
     */
    public function test_redondeo_hacia_abajo(): void
    {
        $periods = [
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
            $this->makePeriod(71.0),
        ];

        $cf = $this->calculator->calculate($periods);

        // (70+70+70+71)/4 = 70.25 → 70
        $this->assertEquals(70, $cf);
    }

    /**
     * Redondeo hacia arriba cuando el decimal es 0.5 o mayor.
     */
    public function test_redondeo_hacia_arriba(): void
    {
        $periods = [
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
            $this->makePeriod(72.0),
        ];

        $cf = $this->calculator->calculate($periods);

        // (70+70+70+72)/4 = 70.5 → 71
        $this->assertEquals(71, $cf);
    }

    /**
     * Validación: el cálculo requiere exactamente 4 períodos.
     * Con menos de 4, debe lanzar una excepción.
     */
    public function test_lanza_excepcion_si_no_son_4_periodos(): void
    {
        $periods = [
            $this->makePeriod(80.0),
            $this->makePeriod(80.0),
            $this->makePeriod(80.0),
        ];

        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate($periods);
    }

    /**
     * Caso de aprobación exacta en el límite: CF=70 es aprobado.
     */
    public function test_cf_en_el_limite_de_aprobacion(): void
    {
        $periods = [
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
            $this->makePeriod(70.0),
        ];

        $cf = $this->calculator->calculate($periods);

        $this->assertEquals(70, $cf);
    }
}