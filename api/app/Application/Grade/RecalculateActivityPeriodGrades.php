<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;

/**
 * Actualiza los resúmenes afectados cuando una actividad cambia de estado.
 */
class RecalculateActivityPeriodGrades
{
    public function __construct(
        private readonly ActivityScoreRepositoryInterface $activityScoreRepo,
        private readonly RecalculatePeriodGrade $recalculatePeriodGrade,
    ) {}

    public function execute(
        int $activityId,
        int $subjectId,
        int $periodId,
        int $sectionId,
    ): int {
        $studentIds = $this->activityScoreRepo->findStudentIdsByActivity($activityId);

        foreach ($studentIds as $studentId) {
            $this->recalculatePeriodGrade->execute(
                studentId: $studentId,
                subjectId: $subjectId,
                periodId: $periodId,
                sectionId: $sectionId,
            );
        }

        return count($studentIds);
    }
}
