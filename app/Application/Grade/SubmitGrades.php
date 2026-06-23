<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;

class SubmitGrades
{
    public function __construct(
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
    ) {}

    public function execute(int $subjectId, int $periodId): void
    {
        $this->periodGradeRepo->submitForReview($subjectId, $periodId);
    }
}
