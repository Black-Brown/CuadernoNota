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
     * @param  AttendanceRecord  $record  Entidad con el nuevo registro
     */
    public function save(AttendanceRecord $record): void;

    /**
     * Actualiza un registro de asistencia existente.
     * Se usa principalmente para cambiar el estado de absent a excused
     * cuando el estudiante presenta una justificación.
     *
     * @param  AttendanceRecord  $record  Entidad actualizada (mismo id, nuevo status)
     */
    public function update(AttendanceRecord $record): void;

    /**
     * Obtiene un registro de asistencia por su ID.
     * Devuelve NULL si no existe.
     *
     * @param  int  $id  ID del registro
     */
    public function findById(int $id): ?AttendanceRecord;

    /**
     * Obtiene los registros del estudiante para la materia y el año escolar
     * de la sección indicada, ordenados por fecha.
     *
     * @return AttendanceRecord[]
     */
    public function findByStudentAndCourseAcademicYear(
        int $studentId,
        int $sectionId,
        int $subjectId,
    ): array;

    /**
     * Devuelve el registro de asistencia de todos los estudiantes activos de
     * una sección en una fecha. Los estudiantes sin registro aparecen con
     * status null.
     *
     * @return array [['attendance_id', 'student_id', 'student_name', 'status'], ...]
     */
    public function findByCourseAndDate(int $sectionId, int $subjectId, string $date): array;
}
