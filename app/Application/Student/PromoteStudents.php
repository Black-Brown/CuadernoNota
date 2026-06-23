<?php

declare(strict_types=1);

namespace App\Application\Student;

use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;
use App\Domain\Promotion\Services\PromotionService;

/**
 * Caso de uso: Evaluar y registrar la promoción de un lote de estudiantes.
 *
 * Se ejecuta al cierre del año escolar, luego de que todas las
 * Calificaciones Finales han sido calculadas y aprobadas.
 *
 * Criterio de promoción (ver PromotionService):
 *  - CF >= 70 en TODAS las asignaturas inscritas.
 *  - Ninguna asignatura reprobada (CF < 70).
 *
 * Resultado:
 *  - Los estudiantes aptos se registran como 'promoted' en la base de datos.
 *  - Los no aptos permanecen sin promover (pueden iniciar recuperación final).
 *  - El método devuelve la clasificación para que el controlador la retorne.
 */
class PromoteStudents
{
    /**
     * @param PromotionRepositoryInterface $promotionRepo   Repositorio de promociones
     * @param PromotionService             $promotionService Servicio de elegibilidad
     */
    public function __construct(
        private readonly PromotionRepositoryInterface $promotionRepo,
        private readonly PromotionService             $promotionService,
    ) {}

    /**
     * Evalúa la promoción de una lista de estudiantes y registra el resultado.
     *
     * @param int[]              $studentIds           IDs de los estudiantes a evaluar
     * @param int                $academicYearId       ID del año escolar que se está cerrando
     * @param int                $expectedSubjectCount Número de asignaturas en que los
     *                                                  estudiantes deben tener CF (mismo
     *                                                  valor para todos los del grupo).
     *
     * @return array{promoted: int[], notPromoted: int[]}
     *         Arrays con los IDs clasificados según el resultado.
     */
    public function execute(array $studentIds, int $academicYearId, int $expectedSubjectCount): array
    {
        $promoted    = [];
        $notPromoted = [];

        foreach ($studentIds as $studentId) {
            $finalGrades = $this->promotionRepo->findFinalGradesByStudentYear(
                $studentId,
                $academicYearId,
            );

            if ($this->promotionService->isEligibleForPromotion($finalGrades, $expectedSubjectCount)) {
                $this->promotionRepo->markAsPromoted($studentId, $academicYearId);
                $promoted[] = $studentId;
            } else {
                $notPromoted[] = $studentId;
            }
        }

        return [
            'promoted'    => $promoted,
            'notPromoted' => $notPromoted,
        ];
    }
}
