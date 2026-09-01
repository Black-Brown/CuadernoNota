<?php

declare(strict_types=1);

namespace App\Application\Attendance;

use App\Domain\Attendance\Entities\AttendanceRecord;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Attendance\Services\AttendanceAlertService;

/**
 * Caso de uso: Registrar la asistencia de un estudiante.
 *
 * Persiste el registro de asistencia y, de forma inmediata,
 * evalúa las dos condiciones de alerta:
 *
 *  - consecutive: TRUE si el estudiante acumula 3 o más ausencias
 *                 no justificadas consecutivas en el mes actual.
 *  - lowAnnual:   TRUE si la asistencia anual cae por debajo del 80 %.
 *
 * El resultado se devuelve al controlador para que este decida cómo
 * notificar a docentes o tutores (email, notificación en dashboard, etc.).
 */
class RegisterAttendance
{
    /**
     * @param AttendanceRepositoryInterface $attendanceRepo Repositorio de registros de asistencia
     * @param AttendanceAlertService        $alertService   Servicio de evaluación de alertas
     */
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepo,
        private readonly AttendanceAlertService        $alertService,
    ) {}

    /**
     * Guarda el registro de asistencia y evalúa las alertas.
     *
     * @param AttendanceRecord $record Registro de asistencia a persistir
     *
     * @return array{consecutive: bool, lowAnnual: bool}
     *         Flags indicando qué alertas (si alguna) se activaron.
     */
    public function execute(AttendanceRecord $record): array
    {
        $this->attendanceRepo->save($record);

        $year  = (int) $record->date->format('Y');
        $month = (int) $record->date->format('n');

        $monthRecords = $this->attendanceRepo->findByStudentAndMonth(
            $record->studentId,
            $year,
            $month,
        );

        $yearRecords = $this->attendanceRepo->findByStudentAndYear(
            $record->studentId,
            $year,
        );

        return [
            'consecutive' => $this->alertService->hasConsecutiveAbsenceAlert($monthRecords),
            'lowAnnual'   => $this->alertService->hasBelowMinimumAnnualAttendance($yearRecords),
        ];
    }
}
