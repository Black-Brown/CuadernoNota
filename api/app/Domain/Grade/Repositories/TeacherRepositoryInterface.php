<?php

declare(strict_types=1);

namespace App\Domain\Grade\Repositories;

interface TeacherRepositoryInterface
{
    /**
     * Devuelve los cursos (sección + asignatura) asignados al docente, ordenados
     * por año vigente/reciente, orden académico del grado, sección y asignatura.
     *
     * @return array [['section_id', 'subject_id', 'academic_year_id', 'grade_name', 'section_name', 'subject_name', 'status', 'students_count'], ...]
     */
    public function findCoursesByTeacher(int $teacherId): array;

    /**
     * Resumen global para el dashboard "My Courses":
     * cursos activos, total de estudiantes, promedio general, % de asistencia.
     *
     * @return array{active_courses: int, total_students: int, avg_grade: float|null, attendance_avg: float|null}
     */
    public function getDashboardSummary(int $teacherId, ?int $periodId = null): array;

    /**
     * Dashboard de un curso específico:
     * promedio grupal, estudiantes en riesgo, progreso del período,
     * asistencia de la sección y distribución de calificaciones por rangos.
     *
     * @return array{group_avg: float|null, at_risk_count: int, period_progress: float, attendance_pct: float|null, distribution: array}
     */
    public function getSubjectDashboard(int $teacherId, int $sectionId, int $subjectId, ?int $periodId = null): array;
}
