<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;
use App\Infrastructure\Models\Activity as ActivityModel;
use App\Infrastructure\Models\ActivityScore as ActivityScoreModel;
use App\Infrastructure\Models\Student as StudentModel;
use App\Infrastructure\Models\TeacherSection as TeacherSectionModel;

class EloquentActivityScoreRepository implements ActivityScoreRepositoryInterface
{
    /**
     * Inserta o actualiza la nota de una actividad.
     * La unicidad se determina por la combinación de 5 columnas.
     *
     * @param array $data activity_id, student_id, competency_id, period_id, subject_id, score
     */
    public function save(array $data): void
    {
        ActivityScoreModel::updateOrCreate(
            [
                'activity_id'   => $data['activity_id'],
                'student_id'    => $data['student_id'],
                'competency_id' => $data['competency_id'],
                'period_id'     => $data['period_id'],
                'subject_id'    => $data['subject_id'],
            ],
            ['score' => $data['score']]
        );
    }

    /**
     * Devuelve todas las notas de actividades para un estudiante
     * en una asignatura y período, en el formato que espera CompetencyCalculator:
     * [['competency_id' => int, 'score' => float|null], ...]
     */
    public function findByStudentSubjectPeriod(
        int $studentId,
        int $subjectId,
        int $periodId
    ): array {
        return ActivityScoreModel::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->get(['competency_id', 'score'])
            ->map(fn($m) => [
                'competency_id' => (int) $m->competency_id,
                'score'         => $m->score !== null ? (float) $m->score : null,
            ])
            ->all();
    }

    public function findStudentScoresByActivity(int $activityId, int $periodId, ?int $sectionId = null): array
    {
        $activity = ActivityModel::findOrFail($activityId);

        // Prefer explicit sectionId; fall back to resolving via teacher_section
        if ($sectionId === null) {
            $teacherSection = TeacherSectionModel::where('user_id', $activity->user_id)
                ->where('subject_id', $activity->subject_id)
                ->first();
            $sectionId = $teacherSection?->section_id;
        }

        $students = $sectionId
            ? StudentModel::where('section_id', $sectionId)
                ->where('active', true)
                ->orderBy('last_name')
                ->orderBy('name')
                ->get()
            : collect();

        $scores = ActivityScoreModel::where('activity_id', $activityId)
            ->where('period_id', $periodId)
            ->get()
            ->groupBy('student_id');

        return $students->map(fn($student) => [
            'student_id'    => $student->id,
            'student_name'  => $student->last_name . ', ' . $student->name,
            'enrollment_no' => $student->enrollment_no,
            'c1'            => isset($scores[$student->id])
                ? optional($scores[$student->id]->firstWhere('competency_id', 1))->score
                : null,
            'c2'            => isset($scores[$student->id])
                ? optional($scores[$student->id]->firstWhere('competency_id', 2))->score
                : null,
            'c3'            => isset($scores[$student->id])
                ? optional($scores[$student->id]->firstWhere('competency_id', 3))->score
                : null,
        ])->all();
    }
}
