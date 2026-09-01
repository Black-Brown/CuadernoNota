<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;

/**
 * Caso de uso: Aprobar las notas de período de una asignatura.
 *
 * Cambia el estado de las notas de 'in_review' a 'official'.
 * Solo las notas con estado 'official' aparecen en el boletín
 * y pueden ser usadas para calcular el CF.
 *
 * Este caso de uso es invocado por el Coordinador (CU-11) o el
 * Administrador (CU-18) luego de revisar las notas enviadas por
 * el docente.
 */
class ApproveGrades
{
    /**
     * @param PeriodGradeRepositoryInterface $periodGradeRepo Repositorio de notas por período
     */
    public function __construct(
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
    ) {}

    /**
     * Aprueba las notas de período de una asignatura, haciéndolas oficiales.
     *
     * @param int $subjectId  ID de la asignatura cuyas notas se aprueban
     * @param int $periodId   ID del período académico (1-4)
     * @param int $approvedBy ID del usuario (coordinador o administrador) que aprueba
     */
    public function execute(int $subjectId, int $periodId, int $approvedBy): void
    {
        $this->periodGradeRepo->approve($subjectId, $periodId, $approvedBy);
    }
}
