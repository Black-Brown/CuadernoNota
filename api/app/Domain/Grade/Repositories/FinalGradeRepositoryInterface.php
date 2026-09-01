<?php

namespace App\Domain\Grade\Repositories;

use App\Domain\Grade\Entities\FinalGrade;

/**
 * Contrato del repositorio de Calificaciones Finales (CF).
 *
 * Corresponde a la tabla final_grades — calculada al cierre
 * del año escolar para cada estudiante/asignatura.
 */
interface FinalGradeRepositoryInterface
{
    /**
     * Inserta o actualiza la Calificación Final de un estudiante
     * para una asignatura en un año escolar específico.
     *
     * La unicidad se determina por:
     * student_id + subject_id + academic_year_id.
     *
     * @param FinalGrade $finalGrade Entidad con el CF calculado
     *
     * @return FinalGrade La entidad persistida
     */
    public function upsert(FinalGrade $finalGrade): FinalGrade;

    /**
     * Obtiene la Calificación Final de un estudiante para una
     * asignatura en un año escolar específico.
     *
     * @param int $studentId      ID del estudiante
     * @param int $subjectId      ID de la asignatura
     * @param int $academicYearId ID del año escolar
     *
     * @return FinalGrade|null La entidad si existe, o NULL si el
     *                          año aún no ha cerrado / no se ha calculado.
     */
    public function findByStudentSubjectYear(
        int $studentId,
        int $subjectId,
        int $academicYearId
    ): ?FinalGrade;

    /**
     * Registra una Recuperación Final para un estudiante.
     * Se aplica al cierre del año para estudiantes con CF < 70.
     *
     * Corresponde a CU-09.
     *
     * @param int   $studentId ID del estudiante
     * @param int   $subjectId ID de la asignatura
     * @param int   $academicYearId ID del año escolar
     * @param float $score     Nota de la Recuperación Final (0-100)
     */
    public function registerFinalRecovery(
        int $studentId,
        int $subjectId,
        int $academicYearId,
        float $score
    ): void;

    /**
     * Registra una Recuperación Especial para un estudiante.
     * Requiere autorización previa del Administrador.
     *
     * Corresponde a CU-09.
     *
     * @param int   $studentId ID del estudiante
     * @param int   $subjectId ID de la asignatura
     * @param int   $academicYearId ID del año escolar
     * @param float $score     Nota de la Recuperación Especial (0-100)
     */
    public function registerSpecialRecovery(
        int $studentId,
        int $subjectId,
        int $academicYearId,
        float $score
    ): void;
}