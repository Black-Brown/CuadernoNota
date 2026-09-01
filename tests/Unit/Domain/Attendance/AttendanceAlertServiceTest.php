<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Attendance;

use DateTimeImmutable;
use Tests\TestCase;
use App\Domain\Attendance\Entities\AttendanceRecord;
use App\Domain\Attendance\Services\AttendanceAlertService;

/**
 * Tests del AttendanceAlertService.
 *
 * Cubre las dos reglas de alerta del sistema:
 *  Regla 1 — Ausencias consecutivas: 3 o más ausencias no justificadas
 *            seguidas en el mismo mes activan la alerta.
 *  Regla 2 — Asistencia anual: porcentaje de días presentes < 80 %
 *            activa la alerta.
 */
class AttendanceAlertServiceTest extends TestCase
{
    private AttendanceAlertService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceAlertService();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Crea un AttendanceRecord con los datos mínimos necesarios para los tests.
     *
     * @param string $date   Fecha en formato 'Y-m-d'
     * @param string $status present | absent | excused
     */
    private function makeRecord(string $date, string $status): AttendanceRecord
    {
        return new AttendanceRecord(
            id:        1,
            studentId: 1,
            date:      new DateTimeImmutable($date),
            status:    $status,
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Regla 1 — Ausencias consecutivas
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Caso base: exactamente 3 ausencias consecutivas deben activar la alerta.
     */
    public function test_tres_ausencias_consecutivas_activan_la_alerta(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_ABSENT),
        ];

        $this->assertTrue($this->service->hasConsecutiveAbsenceAlert($records));
    }

    /**
     * Solo 2 ausencias consecutivas NO alcanzan el umbral — sin alerta.
     */
    public function test_dos_ausencias_consecutivas_no_activan_la_alerta(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_PRESENT),
        ];

        $this->assertFalse($this->service->hasConsecutiveAbsenceAlert($records));
    }

    /**
     * 4 ausencias consecutivas también activan la alerta (supera el umbral de 3).
     */
    public function test_cuatro_ausencias_consecutivas_activan_la_alerta(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-04', AttendanceRecord::STATUS_ABSENT),
        ];

        $this->assertTrue($this->service->hasConsecutiveAbsenceAlert($records));
    }

    /**
     * Una presencia en el medio interrumpe la cadena: 2+2 no es lo mismo que 4.
     */
    public function test_presencia_en_el_medio_interrumpe_la_cadena(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_PRESENT),  // interrumpe
            $this->makeRecord('2025-10-04', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-05', AttendanceRecord::STATUS_ABSENT),
        ];

        $this->assertFalse($this->service->hasConsecutiveAbsenceAlert($records));
    }

    /**
     * Una excusa también interrumpe la cadena de ausencias no justificadas.
     */
    public function test_excusa_interrumpe_la_cadena_de_ausencias(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_EXCUSED), // interrumpe
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-04', AttendanceRecord::STATUS_ABSENT),
        ];

        $this->assertFalse($this->service->hasConsecutiveAbsenceAlert($records));
    }

    /**
     * El servicio ordena los registros por fecha internamente,
     * por lo que el orden de entrada no debe afectar el resultado.
     */
    public function test_alerta_funciona_con_registros_desordenados(): void
    {
        $records = [
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_ABSENT),
        ];

        $this->assertTrue($this->service->hasConsecutiveAbsenceAlert($records));
    }

    /**
     * Sin registros no puede haber alerta.
     */
    public function test_sin_registros_no_activa_la_alerta_consecutiva(): void
    {
        $this->assertFalse($this->service->hasConsecutiveAbsenceAlert([]));
    }

    /**
     * Todos los días presentes — nunca hay alerta.
     */
    public function test_solo_presencias_no_activa_la_alerta_consecutiva(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_PRESENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_PRESENT),
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_PRESENT),
            $this->makeRecord('2025-10-04', AttendanceRecord::STATUS_PRESENT),
        ];

        $this->assertFalse($this->service->hasConsecutiveAbsenceAlert($records));
    }

    public function test_tardanza_interrumpe_ausencias_y_cuenta_como_asistencia(): void
    {
        $records = [
            $this->makeRecord('2025-10-01', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-02', AttendanceRecord::STATUS_ABSENT),
            $this->makeRecord('2025-10-03', AttendanceRecord::STATUS_LATE),
            $this->makeRecord('2025-10-04', AttendanceRecord::STATUS_ABSENT),
        ];

        $this->assertFalse($this->service->hasConsecutiveAbsenceAlert($records));
        $this->assertSame(25.0, $this->service->calculateAnnualAttendancePercentage($records));
    }

    // ──────────────────────────────────────────────────────────────────────
    // Regla 2 — Asistencia anual
    // ──────────────────────────────────────────────────────────────────────

    /**
     * 7 presentes de 10 = 70 % — está por debajo del 80 %, alerta activa.
     */
    public function test_asistencia_anual_menor_a_80_activa_la_alerta(): void
    {
        $records = [];
        for ($i = 1; $i <= 7; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_PRESENT);
        }
        for ($i = 8; $i <= 10; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_ABSENT);
        }

        $this->assertTrue($this->service->hasBelowMinimumAnnualAttendance($records));
    }

    /**
     * Caso límite exacto: 8 presentes de 10 = 80.0 % — no activa la alerta.
     * El umbral es estricto (< 80), así que exactamente 80 es válido.
     */
    public function test_asistencia_anual_exactamente_80_no_activa_la_alerta(): void
    {
        $records = [];
        for ($i = 1; $i <= 8; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_PRESENT);
        }
        for ($i = 9; $i <= 10; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_ABSENT);
        }

        $this->assertFalse($this->service->hasBelowMinimumAnnualAttendance($records));
    }

    /**
     * 100 % de asistencia nunca activa la alerta anual.
     */
    public function test_asistencia_anual_100_no_activa_la_alerta(): void
    {
        $records = [];
        for ($i = 1; $i <= 10; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_PRESENT);
        }

        $this->assertFalse($this->service->hasBelowMinimumAnnualAttendance($records));
    }

    /**
     * 0 % de asistencia activa la alerta (todos absent).
     */
    public function test_asistencia_anual_cero_activa_la_alerta(): void
    {
        $records = [];
        for ($i = 1; $i <= 5; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_ABSENT);
        }

        $this->assertTrue($this->service->hasBelowMinimumAnnualAttendance($records));
    }

    /**
     * Los días excused NO cuentan como presentes en el porcentaje anual.
     * 5 excused + 5 absent = 0 % asistencia — alerta activa.
     */
    public function test_excusas_no_cuentan_como_presencia_en_el_calculo_anual(): void
    {
        $records = [];
        for ($i = 1; $i <= 5; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_EXCUSED);
        }
        for ($i = 6; $i <= 10; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_ABSENT);
        }

        $this->assertTrue($this->service->hasBelowMinimumAnnualAttendance($records));
    }

    /**
     * Sin registros anuales no hay alerta (se asume 100 % por defecto).
     */
    public function test_sin_registros_anuales_no_activa_la_alerta(): void
    {
        $this->assertFalse($this->service->hasBelowMinimumAnnualAttendance([]));
    }

    // ──────────────────────────────────────────────────────────────────────
    // calculateAnnualAttendancePercentage
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Verifica que el porcentaje se calcula correctamente.
     */
    public function test_calcula_porcentaje_anual_correctamente(): void
    {
        $records = [];
        for ($i = 1; $i <= 8; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_PRESENT);
        }
        for ($i = 9; $i <= 10; $i++) {
            $records[] = $this->makeRecord("2025-03-{$i}", AttendanceRecord::STATUS_ABSENT);
        }

        $percentage = $this->service->calculateAnnualAttendancePercentage($records);

        $this->assertEquals(80.0, $percentage);
    }

    /**
     * Sin registros devuelve 100 % (valor por defecto, sin penalización).
     */
    public function test_sin_registros_devuelve_100_de_asistencia(): void
    {
        $percentage = $this->service->calculateAnnualAttendancePercentage([]);

        $this->assertEquals(100.0, $percentage);
    }
}
