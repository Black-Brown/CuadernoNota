<?php

declare(strict_types=1);

namespace App\Application\Observation;

use App\Domain\Observation\Repositories\ObservationRepositoryInterface;

class GetObservations
{
    public function __construct(
        private readonly ObservationRepositoryInterface $observationRepo,
    ) {}

    public function execute(int $studentId): array
    {
        return $this->observationRepo->findByStudent($studentId);
    }
}
