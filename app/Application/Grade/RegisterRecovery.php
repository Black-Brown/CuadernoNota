<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Entities\FinalGrade;
use App\Domain\Grade\Entities\PeriodGrade;
use App\Domain\Grade\Repositories\FinalGradeRepositoryInterface;
use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
use App\Domain\Grade\Services\CFCalculator;
use RuntimeException;

/**
 * Caso de uso: Registrar una Recuperación Pedagógica (RP) en un período.
 *
 * Cuando un estudiante realiza una recuperación, su nota de RP se
 * almacena en el campo rpScore del PeriodGrade correspondiente.
 * Esa nota sustituye al period_score original en el cálculo del CF.
 *
 * Flujo automático tras registrar la RP:
 *  1. Actualiza rpScore en el PeriodGrade del período indicado.
 *  2. Obtiene los 4 períodos del año para recalcular el CF.
 *  3. Recalcula el CF con CFCalculator (usa effectiveScore en cada período).
 *  4. Hace upsert del FinalGrade con el nuevo CF.
 *
 * Devuelve el FinalGrade actualizado para que el controlador lo retorne
 * al cliente sin necesidad de una segunda consulta.
 */
class RegisterRecovery
{
    /**
     * @param PeriodGradeRepositoryInterface $periodGradeRepo Repositorio de notas por período
     * @param FinalGradeRepositoryInterface  $finalGradeRepo  Repositorio de calificaciones finales
     * @param CFCalculator                   $cfCalculator    Servicio de cálculo del CF
     */
    public function __construct(
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
        private readonly FinalGradeRepositoryInterface  $finalGradeRepo,
        private readonly CFCalculator                   $cfCalculator,
    ) {}

    /**
     * Registra la nota de recuperación pedagógica y recalcula el CF.
     *
     * @param int   $studentId      ID del estudiante
     * @param int   $subjectId      ID de la asignatura
     * @param int   $periodId       Período en que se realiza la RP (1-4)
     * @param int   $academicYearId ID del año escolar
     * @param float $rpScore        Nota obtenida en la recuperación (0-100)
     *
     * @return FinalGrade FinalGrade con el CF recalculado.
     *
     * @throws RuntimeException Si el período indicado no tiene nota previa,
     *                           o si faltan períodos para calcular el CF.
     */
    public function execute(
        int   $studentId,
        int   $subjectId,
        int   $periodId,
        int   $academicYearId,
        float $rpScore,
    ): FinalGrade {
        // 1. Obtener el PeriodGrade que va a recibir la RP
        $existing = $this->periodGradeRepo->findByStudentSubjectPeriod($studentId, $subjectId, $periodId);

        if ($existing === null) {
            throw new RuntimeException(
                "No existe nota para el período {$periodId} del estudiante {$studentId} en la asignatura {$subjectId}."
            );
        }

        // 2. Crear un nuevo PeriodGrade con la RP incluida (readonly — se reconstruye)
        $withRp = new PeriodGrade(
            studentId:   $existing->studentId,
            subjectId:   $existing->subjectId,
            periodId:    $existing->periodId,
            c1Score:     $existing->c1Score,
            c2Score:     $existing->c2Score,
            c3Score:     $existing->c3Score,
            periodScore: $existing->periodScore,
            rpScore:     $rpScore,
            status:      $existing->status,
            sectionId:   $existing->sectionId,
        );

        // 3. Obtener y validar los 4 períodos antes de modificar datos.
        $allPeriods = $this->periodGradeRepo->findAllPeriodsByStudentSubject(
            $studentId,
            $subjectId,
            $academicYearId,
        );

        if (count($allPeriods) < 4) {
            throw new RuntimeException(
                "Se necesitan 4 períodos para recalcular el CF. " .
                "El estudiante {$studentId} solo tiene " . count($allPeriods) . " período(s) registrado(s)."
            );
        }

        $allPeriods = array_map(
            fn (PeriodGrade $periodGrade): PeriodGrade => $periodGrade->periodId === $periodId ? $withRp : $periodGrade,
            $allPeriods,
        );

        // 4. Recalcular el CF
        $newCf = $this->cfCalculator->calculate($allPeriods);

        // Persistir solamente después de completar todas las validaciones y cálculos.
        $this->periodGradeRepo->upsert($withRp);

        $existingFg = $this->finalGradeRepo->findByStudentSubjectYear($studentId, $subjectId, $academicYearId);

        $finalGrade = new FinalGrade(
            studentId:       $studentId,
            subjectId:       $subjectId,
            academicYearId:  $academicYearId,
            cf:              $newCf,
            finalRecovery:   $existingFg?->finalRecovery,
            specialRecovery: $existingFg?->specialRecovery,
        );

        return $this->finalGradeRepo->upsert($finalGrade);
    }
}
