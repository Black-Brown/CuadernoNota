<?php

declare(strict_types=1);

namespace App\Application\Attendance;

use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;

class GetAttendanceByCourse
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepo,
    ) {}

    public function execute(int $sectionId, int $subjectId, string $date): array
    {
        return $this->attendanceRepo->findByCourseAndDate($sectionId, $subjectId, $date);
    }
}
