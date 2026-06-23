<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Domain\Grade\Repositories\TeacherRepositoryInterface;

class GetDashboardBySubject
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teacherRepo,
    ) {}

    public function execute(int $teacherId, int $sectionId, int $subjectId): array
    {
        return $this->teacherRepo->getSubjectDashboard($teacherId, $sectionId, $subjectId);
    }
}
