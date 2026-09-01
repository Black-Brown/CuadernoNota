<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\ActivityRepositoryInterface;

class GetActivitiesBySubject
{
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepo,
    ) {}

    public function execute(int $subjectId, ?int $sectionId = null, ?int $periodId = null): array
    {
        return $this->activityRepo->findBySubjectWithScoreCount($subjectId, $sectionId, $periodId);
    }
}
