<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;

class GetActivityGrades
{
    public function __construct(
        private readonly ActivityScoreRepositoryInterface $scoreRepo,
    ) {}

    public function execute(int $activityId, int $periodId, ?int $sectionId = null): array
    {
        return $this->scoreRepo->findStudentScoresByActivity($activityId, $periodId, $sectionId);
    }
}
