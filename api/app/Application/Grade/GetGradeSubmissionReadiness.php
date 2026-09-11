<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;

/**
 * Resume si una sección está completa para enviar sus notas a revisión.
 */
class GetGradeSubmissionReadiness
{
    public function __construct(
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
    ) {}

    public function execute(int $subjectId, int $periodId, int $sectionId): array
    {
        return $this->periodGradeRepo->submissionReadiness(
            $subjectId,
            $periodId,
            $sectionId,
        );
    }
}
