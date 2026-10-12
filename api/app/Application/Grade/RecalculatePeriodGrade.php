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
        $existing = $this->periodGradeRepo->findByStudentSubjectPeriod($studentId, $subjectId, $periodId);
        if ($existing && $existing->status !== 'draft') {
            return $existing;
        }
        $scores = $this->activityScoreRepo->findByStudentSubjectPeriod(
            $studentId,
            $subjectId,
            $periodId,
        );

        $c1 = $this->competencyCalc->calculate($scores, 1);
        $c2 = $this->competencyCalc->calculate($scores, 2);
        $c3 = $this->competencyCalc->calculate($scores, 3);
        $c4 = $this->competencyCalc->calculate($scores, 4);

        // Every started activity needs all four scores. Completely blank activities stay pending.
        $activities = [];
        foreach ($scores as $score) {
            if ($score['score'] !== null) {
                $activities[$score['activity_id'] ?? 0][$score['competency_id']] = true;
            }
        }
        $incompleteActivity = false;
        foreach ($activities as $competencies) {
            if (array_diff([1, 2, 3, 4], array_keys($competencies)) !== []) {
                $incompleteActivity = true;
                break;
            }
        }
        if ($incompleteActivity || $c1 === null || $c2 === null || $c3 === null || $c4 === null) {
            $this->periodGradeRepo->deleteByStudentSubjectPeriod(
                $studentId,
                $subjectId,
                $periodId,
            );

            return null;
        }

        return $this->periodGradeRepo->upsert(new PeriodGrade(
            studentId: $studentId,
            subjectId: $subjectId,
            periodId: $periodId,
            c1Score: $c1,
            c2Score: $c2,
            c3Score: $c3,
            c4Score: $c4,
            periodScore: $this->gradeCalc->calculate($c1, $c2, $c3, $c4),
            rpScore: $existing?->rpScore,
            status: $existing?->status ?? 'draft',
            sectionId: $sectionId ?? $existing?->sectionId,
        ));
    }
}
