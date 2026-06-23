<?php

declare(strict_types=1);

namespace App\Domain\Promotion\Repositories;

use App\Domain\Grade\Entities\FinalGrade;

/**
 * Contrato del repositorio de promociones.
 *
 * Proporciona acceso a las Calificaciones Finales de todas las
 * asignaturas de un estudiante (lectura a través de la capa de
 * dominio de Grade) y permite registrar el resultado del proceso
 * de promoción en la tabla student_promotions.
 */
interface PromotionRepositoryInterface
{
    /**
     * Obtiene todas las Calificaciones Finales de un estudiante
     * en un año escolar específico, una por asignatura.
     *
     * Se usa en PromotionService para verificar que el estudiante
     * aprobó TODAS sus asignaturas antes de promoverlo.
     *
     * @param int $studentId      ID del estudiante
     * @param int $academicYearId ID del año escolar
     *
     * @return FinalGrade[] Array vacío si aún no hay calificaciones cerradas.
     */
    public function findFinalGradesByStudentYear(int $studentId, int $academicYearId): array;

    /**
     * Registra al estudiante como promovido al siguiente año escolar.
     * Si ya existe el registro, lo actualiza.
     *
     * @param int $studentId      ID del estudiante
     * @param int $academicYearId ID del año escolar que se está cerrando
     */
    public function markAsPromoted(int $studentId, int $academicYearId): void;

    /**
     * Consulta si un estudiante ya fue marcado como promovido
     * para el año escolar dado.
     *
     * @param int $studentId      ID del estudiante
     * @param int $academicYearId ID del año escolar
     *
     * @return bool
     */
    public function isPromoted(int $studentId, int $academicYearId): bool;
}
