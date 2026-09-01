<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Entities\AttendanceRecord;

/**
 * Servicio de dominio que evalúa los registros de asistencia
 * y determina si se deben activar alertas.
 *
 * Regla 1 — Ausencias consecutivas en el mes:
 *   Si un estudiante acumula 3 o más ausencias no justificadas
 *   de forma consecutiva dentro del mismo mes, se activa la alerta.
 *   Las excusas (excused) interrumpen la cadena, igual que la presencia.
 *
 * Regla 2 — Asistencia anual por debajo del mínimo:
 *   Si los días presentes o con tardanza representan menos del 80 % del total de
 *   días registrados en el año, se activa la alerta.
 *   Los días excused y absent cuentan como NO presentes.
 */
class AttendanceAlertService
{
    /** Número de ausencias no justificadas consecutivas que activan la alerta mensual. */
    public const CONSECUTIVE_ABSENCE_THRESHOLD = 3;

    /** Porcentaje mínimo de asistencia anual para no activar la alerta. */
    public const MINIMUM_ANNUAL_ATTENDANCE_PERCENT = 80.0;

    /**
     * Evalúa si existen 3 o más ausencias no justificadas consecutivas
     * dentro del conjunto de registros del mes.
     *
     * @param AttendanceRecord[] $monthRecords Registros del mes para el estudiante.
     *                                          No es obligatorio que lleguen ordenados;
     *                                          el método los ordena internamente por fecha.
     *
     * @return bool TRUE si la alerta debe activarse, FALSE en caso contrario.
     */
    public function hasConsecutiveAbsenceAlert(array $monthRecords): bool
    {
        if (empty($monthRecords)) {
            return false;
        }

        usort(
            $monthRecords,
            fn(AttendanceRecord $a, AttendanceRecord $b): int => $a->date <=> $b->date
        );

        $consecutive = 0;

        foreach ($monthRecords as $record) {
            if ($record->isAbsent()) {
                $consecutive++;
                if ($consecutive >= self::CONSECUTIVE_ABSENCE_THRESHOLD) {
                    return true;
                }
            } else {
                // Presencia o excusa interrumpen la cadena
                $consecutive = 0;
            }
        }

        return false;
    }

    /**
     * Evalúa si la asistencia anual del estudiante está por debajo del 80 %.
     *
     * @param AttendanceRecord[] $yearRecords Todos los registros del año para el estudiante.
     *
     * @return bool TRUE si la alerta debe activarse, FALSE en caso contrario.
     */
    public function hasBelowMinimumAnnualAttendance(array $yearRecords): bool
    {
        $total = count($yearRecords);

        if ($total === 0) {
            return false;
        }

        $presentDays = count(
            array_filter($yearRecords, fn(AttendanceRecord $r): bool => $r->countsAsAttendance())
        );

        $percentage = ($presentDays / $total) * 100.0;

        return $percentage < self::MINIMUM_ANNUAL_ATTENDANCE_PERCENT;
    }

    /**
     * Calcula el porcentaje de asistencia anual del estudiante.
     * Útil para mostrar el dato en reportes sin activar ninguna alerta.
     *
     * @param AttendanceRecord[] $yearRecords
     *
     * @return float Porcentaje de 0 a 100. Devuelve 100.0 si no hay registros.
     */
    public function calculateAnnualAttendancePercentage(array $yearRecords): float
    {
        $total = count($yearRecords);

        if ($total === 0) {
            return 100.0;
        }

        $presentDays = count(
            array_filter($yearRecords, fn(AttendanceRecord $r): bool => $r->countsAsAttendance())
        );

        return ($presentDays / $total) * 100.0;
    }
}
