<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\TeacherRepositoryInterface;

class GetTeacherCourses
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teacherRepo,
    ) {}

    public function execute(int $teacherId): array
    {
        return $this->teacherRepo->findCoursesByTeacher($teacherId);
    }
}
