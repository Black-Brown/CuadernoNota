<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Grade\Repositories\TeacherRepositoryInterface;

class GetTeacherDashboard
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teacherRepo,
    ) {}

    public function execute(int $teacherId, ?int $periodId = null): array
    {
        return $this->teacherRepo->getDashboardSummary($teacherId, $periodId);
    }
}
