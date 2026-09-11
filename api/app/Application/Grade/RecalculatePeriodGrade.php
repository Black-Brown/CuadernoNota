<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Entities\PeriodGrade;
use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;
use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
use App\Domain\Grade\Services\CompetencyCalculator;
use App\Domain\Grade\Services\GradeCalculator;

/**
 * Recalcula el resumen de un estudiante usando únicamente actividades activas.
 */
class RecalculatePeriodGrade
{
    public function __construct(
        private readonly ActivityScoreRepositoryInterface $activityScoreRepo,
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
        private readonly CompetencyCalculator $competencyCalc,
        private readonly GradeCalculator $gradeCalc,
    ) {}

    public function execute(
        int $studentId,
        int $subjectId,
        int $periodId,
        ?int $sectionId = null,
    ): ?PeriodGrade {
        $scores = $this->activityScoreRepo->findByStudentSubjectPeriod(
            $studentId,
            $subjectId,
            $periodId,
        );

        $c1 = $this->competencyCalc->calculate($scores, 1);
        $c2 = $this->competencyCalc->calculate($scores, 2);
        $c3 = $this->competencyCalc->calculate($scores, 3);

        if ($c1 === null || $c2 === null || $c3 === null) {
            $this->periodGradeRepo->deleteByStudentSubjectPeriod(
                $studentId,
                $subjectId,
                $periodId,
            );

            return null;
        }

        $existing = $this->periodGradeRepo->findByStudentSubjectPeriod(
            $studentId,
            $subjectId,
            $periodId,
        );

        return $this->periodGradeRepo->upsert(new PeriodGrade(
            studentId: $studentId,
            subjectId: $subjectId,
            periodId: $periodId,
            c1Score: $c1,
            c2Score: $c2,
            c3Score: $c3,
            periodScore: $this->gradeCalc->calculate($c1, $c2, $c3),
            rpScore: $existing?->rpScore,
            status: $existing?->status ?? 'draft',
            sectionId: $sectionId ?? $existing?->sectionId,
        ));
    }
}
