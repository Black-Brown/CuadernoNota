<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Entities\AttendanceRecord;

/**
 * Contrato del repositorio de registros de asistencia.
 *
 * El dominio define QUÉ operaciones necesita, sin saber CÓMO
 * se implementan. La implementación concreta vive en Infrastructure
 * y se inyecta vía AppServiceProvider.
 *
 * Corresponde a la tabla attendance_records.
 */
interface AttendanceRepositoryInterface
{
    /**
     * Persiste un nuevo registro de asistencia.
     *
     * @param AttendanceRecord $record Entidad con el nuevo registro
     */
    public function save(AttendanceRecord $record): void;

    /**
     * Actualiza un registro de asistencia existente.
     * Se usa principalmente para cambiar el estado de absent a excused
     * cuando el estudiante presenta una justificación.
     *
     * @param AttendanceRecord $record Entidad actualizada (mismo id, nuevo status)
     */
    public function update(AttendanceRecord $record): void;

    /**
     * Obtiene un registro de asistencia por su ID.
     * Devuelve NULL si no existe.
     *
     * @param int $id ID del registro
     *
     * @return AttendanceRecord|null
     */
    public function findById(int $id): ?AttendanceRecord;

    /**
     * Obtiene todos los registros de asistencia de un estudiante
     * en un mes y año específicos, ordenados por fecha ascendente.
     *
     * Se usa en AttendanceAlertService para detectar ausencias
     * consecutivas dentro del mes.
     *
     * @param int $studentId ID del estudiante
     * @param int $year      Año (ej. 2025)
     * @param int $month     Número de mes (1-12)
     *
     * @return AttendanceRecord[]
     */
    public function findByStudentAndMonth(int $studentId, int $year, int $month): array;

    /**
     * Obtiene todos los registros de asistencia de un estudiante
     * a lo largo de un año escolar completo.
     *
     * Se usa en AttendanceAlertService para calcular el porcentaje
     * de asistencia anual.
     *
     * @param int $studentId ID del estudiante
     * @param int $year      Año (ej. 2025)
     *
     * @return AttendanceRecord[]
     */
    public function findByStudentAndYear(int $studentId, int $year): array;

    /**
     * Devuelve el registro de asistencia de todos los estudiantes activos de
     * una sección en una fecha. Los estudiantes sin registro aparecen con
     * status null.
     *
     * @return array [['attendance_id', 'student_id', 'student_name', 'status'], ...]
     */
    public function findBySectionAndDate(int $sectionId, string $date): array;
}
