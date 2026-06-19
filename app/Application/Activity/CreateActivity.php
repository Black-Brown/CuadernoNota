<?php

declare(strict_types=1);

namespace App\Application\Activity;

use App\Domain\Grade\Repositories\ActivityRepositoryInterface;

class CreateActivity
{
    public function __construct(
        private readonly ActivityRepositoryInterface $activityRepo,
    ) {}

    public function execute(array $data): array
    {
        return $this->activityRepo->save($data);
    }
}
