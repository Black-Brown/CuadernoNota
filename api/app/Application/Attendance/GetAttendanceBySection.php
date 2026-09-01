<?php

declare(strict_types=1);

namespace App\Application\Attendance;

use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;

class GetAttendanceBySection
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepo,
    ) {}

    public function execute(int $sectionId, string $date): array
    {
        return $this->attendanceRepo->findBySectionAndDate($sectionId, $date);
    }
}
