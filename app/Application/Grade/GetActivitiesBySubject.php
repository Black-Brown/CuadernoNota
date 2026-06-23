<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\ActivityRepositoryInterface;

class GetActivitiesBySubject
{
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepo,
    ) {}

    public function execute(int $subjectId): array
    {
        return $this->activityRepo->findBySubjectWithScoreCount($subjectId);
    }
}
