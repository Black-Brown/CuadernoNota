<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Entities;

use DateTimeImmutable;

/**
 * Entidad que representa el registro de asistencia de un estudiante
 * en una fecha concreta.
 *
 * Estados posibles:
 *  - present: El estudiante asistió.
 *  - late:    El estudiante asistió con tardanza.
 *  - absent:  El estudiante faltó sin justificación.
 *  - excused: El estudiante faltó con justificación válida.
 *
 * Reglas de negocio:
 *  - 3 ausencias no justificadas (absent) consecutivas en el mismo mes
 *    activan una alerta — ver AttendanceAlertService.
 *  - Asistencia anual < 80 % (días presentes / total de días registrados)
 *    activa una alerta independiente.
 *  - Las excusas (excused) no cuentan como ausencias consecutivas pero sí
 *    reducen el porcentaje anual porque el estudiante no estaba presente.
 */
class AttendanceRecord
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT  = 'absent';
    public const STATUS_LATE    = 'late';
    public const STATUS_EXCUSED = 'excused';

    /**
     * @param int               $id        Identificador único del registro
     * @param int               $studentId ID del estudiante
     * @param DateTimeImmutable $date      Fecha del registro de asistencia
     * @param string            $status    Estado: present | late | absent | excused
     */
    public function __construct(
        public readonly int               $id,
        public readonly int               $studentId,
        public readonly DateTimeImmutable $date,
        public readonly string            $status = self::STATUS_PRESENT,
    ) {}

    /**
     * Indica si el estudiante asistió ese día.
     */
    public function isPresent(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }

    /**
     * Indica si el estudiante faltó sin justificación.
     * Solo las ausencias de este tipo cuentan para la
     * alerta de ausencias consecutivas.
     */
    public function isAbsent(): bool
    {
        return $this->status === self::STATUS_ABSENT;
    }

    public function isLate(): bool
    {
        return $this->status === self::STATUS_LATE;
    }

    public function countsAsAttendance(): bool
    {
        return $this->isPresent() || $this->isLate();
    }

    /**
     * Indica si la falta fue justificada con una excusa válida.
     */
    public function isExcused(): bool
    {
        return $this->status === self::STATUS_EXCUSED;
    }
}
