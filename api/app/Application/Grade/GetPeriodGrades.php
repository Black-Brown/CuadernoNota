<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;

class GetPeriodGrades
{
    public function __construct(
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
    ) {}

    public function execute(int $subjectId, int $periodId, ?int $sectionId = null): array
    {
        return $this->periodGradeRepo->findAllBySubjectPeriod($subjectId, $periodId, $sectionId);
    }
}
