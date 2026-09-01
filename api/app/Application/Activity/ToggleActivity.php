<?php

declare(strict_types=1);

namespace App\Application\Activity;

use App\Domain\Grade\Repositories\ActivityRepositoryInterface;

class ToggleActivity
{
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepo,
    ) {}

    public function execute(int $activityId): array
    {
        return $this->activityRepo->toggleActive($activityId);
    }
}
