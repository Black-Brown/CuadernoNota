<?php

declare(strict_types=1);

namespace App\Application\Attendance;

use App\Domain\Attendance\Entities\AttendanceRecord;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use RuntimeException;

/**
 * Caso de uso: Cambiar una falta no justificada a justificada (excused).
 *
 * Se usa cuando el estudiante presenta una justificación válida (nota
 * médica, permiso oficial, etc.) después de que la asistencia fue
 * registrada como ausente.
 *
 * Reglas:
 *  - Solo se puede justificar un registro con status 'absent'.
 *  - Si el registro ya es 'excused' la operación es idempotente (no falla).
 *  - No se puede justificar un registro 'present'.
 *
 * El cambio a 'excused' elimina al registro del conteo de ausencias
 * consecutivas pero no lo cuenta como presencia en el porcentaje anual.
 */
class UpdateToExcuse
{
    /**
     * @param AttendanceRepositoryInterface $attendanceRepo Repositorio de registros de asistencia
     */
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepo,
    ) {}

    /**
     * Marca un registro de asistencia como justificado.
     *
     * @param int $attendanceId ID del registro de asistencia a actualizar
     *
     * @throws RuntimeException Si el registro no existe o si intenta
     *                           justificar un registro de presencia.
     */
    public function execute(int $attendanceId): void
    {
        $record = $this->attendanceRepo->findById($attendanceId);

        if ($record === null) {
            throw new RuntimeException(
                "No se encontró el registro de asistencia con ID {$attendanceId}."
            );
        }

        if ($record->isPresent()) {
            throw new RuntimeException(
                "No se puede justificar un registro que ya está marcado como presente."
            );
        }

        // Idempotente: si ya está excused no hace nada
        if ($record->isExcused()) {
            return;
        }

        $excused = new AttendanceRecord(
            id:        $record->id,
            studentId: $record->studentId,
            date:      $record->date,
            status:    AttendanceRecord::STATUS_EXCUSED,
        );

        $this->attendanceRepo->update($excused);
    }
}
