<?php

declare(strict_types=1);

namespace App\Application\Observation;

use App\Domain\Observation\Repositories\ObservationRepositoryInterface;

class CreateObservation
{
    public function __construct(
        private readonly ObservationRepositoryInterface $observationRepo,
    ) {}

    public function execute(array $data): void
    {
        $this->observationRepo->save($data);
    }
}
