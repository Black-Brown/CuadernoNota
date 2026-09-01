<?php

declare(strict_types=1);

namespace App\Application\Grade;

use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
use App\Domain\Grade\Repositories\TeacherRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;

class GetGradebookSummary
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teacherRepo,
        private readonly PeriodGradeRepositoryInterface $periodGradeRepo,
    ) {}

    public function execute(
        int $teacherId,
        int $sectionId,
        int $subjectId,
        ?int $academicYearId = null
    ): array {
        $courses = $this->teacherRepo->findCoursesByTeacher($teacherId);

        $course = collect($courses)->first(function (array $course) use ($sectionId, $subjectId, $academicYearId) {
            if ((int) $course['section_id'] !== $sectionId) {
                return false;
            }

            if ((int) $course['subject_id'] !== $subjectId) {
                return false;
            }

            return $academicYearId === null || (int) $course['academic_year_id'] === $academicYearId;
        });

        if (!$course) {
            throw new AuthorizationException('No tienes acceso a este curso.');
        }

        return $this->periodGradeRepo->findGradebookSummary(
            sectionId: (int) $course['section_id'],
            subjectId: (int) $course['subject_id'],
            academicYearId: (int) $course['academic_year_id'],
            course: $course,
        );
    }
}
