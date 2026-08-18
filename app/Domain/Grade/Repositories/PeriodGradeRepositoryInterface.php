<?php

namespace App\Domain\Grade\Repositories;

use App\Domain\Grade\Entities\PeriodGrade;

/**
 * Contrato del repositorio de notas calculadas por período.
 *
 * Corresponde a la tabla period_grades — el resultado del
 * Paso 2 automático (C1, C2, C3 y period_score).
 */
interface PeriodGradeRepositoryInterface
{
    /**
     * Inserta o actualiza la nota de período de un estudiante
     * para una asignatura y período específicos.
     *
     * La unicidad se determina por: student_id + subject_id + period_id.
     * Si ya existe un registro, se actualizan c1_score, c2_score,
     * c3_score y period_score con los nuevos valores calculados.
     *
     * @param PeriodGrade $periodGrade Entidad con los valores calculados
     *
     * @return PeriodGrade La entidad persistida (con los valores guardados)
     */
    public function upsert(PeriodGrade $periodGrade): PeriodGrade;

    /**
     * Obtiene la nota de período de un estudiante para una
     * asignatura y período específicos.
     *
     * @param int $studentId ID del estudiante
     * @param int $subjectId ID de la asignatura
     * @param int $periodId  ID del período académico
     *
     * @return PeriodGrade|null La entidad si existe, o NULL si no
     *                           se ha calculado ninguna nota todavía.
     */
    public function findByStudentSubjectPeriod(
        int $studentId,
        int $subjectId,
        int $periodId
    ): ?PeriodGrade;

    /**
     * Obtiene las notas de período de un estudiante para una
     * asignatura, en los 4 períodos del año escolar.
     *
     * Se usa para calcular el CF (CFCalculator requiere
     * exactamente 4 PeriodGrade).
     *
     * @param int $studentId      ID del estudiante
     * @param int $subjectId      ID de la asignatura
     * @param int $academicYearId ID del año escolar
     *
     * @return PeriodGrade[] Array con los PeriodGrade de los 4 períodos,
     *                        ordenados por número de período (1 a 4).
     */
    public function findAllPeriodsByStudentSubject(
        int $studentId,
        int $subjectId,
        int $academicYearId
    ): array;

    /**
     * Cambia el estado de las notas de período de un docente
     * para una asignatura y período de 'draft' a 'in_review'.
     *
     * Corresponde a CU-06: Enviar notas a aprobación.
     *
     * @param int $subjectId ID de la asignatura
     * @param int $periodId  ID del período académico
     */
    public function submitForReview(int $subjectId, int $periodId, ?int $sectionId = null): void;

    /**
     * Aprueba las notas de período, cambiando su estado a 'official'
     * y registrando quién y cuándo las aprobó.
     *
     * Corresponde a CU-11 (Coordinador) y CU-18 (Administrador).
     *
     * @param int $subjectId  ID de la asignatura
     * @param int $periodId   ID del período académico
     * @param int $approvedBy ID del usuario (coordinator o admin) que aprueba
     */
    public function approve(int $subjectId, int $periodId, int $approvedBy): void;

    /**
     * Rechaza las notas de período, devolviéndolas a estado 'draft'
     * para que el docente las corrija.
     *
     * Corresponde a los flujos alternativos de CU-11 y CU-18.
     *
     * @param int $subjectId ID de la asignatura
     * @param int $periodId  ID del período académico
     */
    public function reject(int $subjectId, int $periodId): void;

    /**
     * Devuelve las notas de período de TODOS los estudiantes de una
     * asignatura en un período determinado.
     *
     * Se usa en la pantalla "Notas Final de Periodo" (Track A, solo lectura).
     *
     * @return array [['student_id', 'student_name', 'c1_score', 'c2_score', 'c3_score', 'period_score', 'rp_score', 'effective_score', 'status'], ...]
     */
    public function findAllBySubjectPeriod(int $subjectId, int $periodId, ?int $sectionId = null): array;

    /**
     * Devuelve el resumen anual de calificaciones por estudiante para una
     * sección/asignatura: P1-RP1, P2-RP2, P3-RP3, P4-RP4, PC/CF y estado.
     *
     * @return array{course: array, periods: array, students: array, summary: array}
     */
    public function findGradebookSummary(
        int $sectionId,
        int $subjectId,
        int $academicYearId,
        array $course = []
    ): array;
}
