<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Observation\Repositories\ObservationRepositoryInterface;
use App\Infrastructure\Models\Observation as ObservationModel;
use Illuminate\Database\Eloquent\Builder;

class EloquentObservationRepository implements ObservationRepositoryInterface
{
    public function findByStudent(int $studentId): array
    {
        return ObservationModel::where('student_id', $studentId)
            ->with('user:id,name')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($o) => $this->toPayload($o))
            ->all();
    }

    public function findByWorkspace(int $sectionId, int $subjectId, int $periodId): array
    {
        return $this->workspaceQuery($sectionId, $subjectId, $periodId)
            ->with(['student:id,name,last_name,enrollment_no', 'user:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($o) => $this->toPayload($o))
            ->all();
    }

    public function findByWorkspaceStudent(int $sectionId, int $subjectId, int $periodId, int $studentId): array
    {
        return $this->workspaceQuery($sectionId, $subjectId, $periodId)
            ->where('student_id', $studentId)
            ->with(['student:id,name,last_name,enrollment_no', 'user:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($o) => $this->toPayload($o))
            ->all();
    }

    public function save(array $data): void
    {
        ObservationModel::create([
            'student_id'  => $data['student_id'],
            'user_id'     => $data['user_id'],
            'section_id'  => $data['section_id'] ?? null,
            'subject_id'  => $data['subject_id'] ?? null,
            'period_id'   => $data['period_id'] ?? null,
            'date'        => $data['date'],
            'type'        => $data['type'],
            'description' => $data['description'],
        ]);
    }

    public function update(int $id, array $data): void
    {
        ObservationModel::where('id', $id)->update([
            'date'        => $data['date'],
            'type'        => $data['type'],
            'description' => $data['description'],
        ]);
    }

    public function delete(int $id): void
    {
        ObservationModel::where('id', $id)->delete();
    }

    private function workspaceQuery(int $sectionId, int $subjectId, int $periodId): Builder
    {
        return ObservationModel::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId);
    }

    private function toPayload(ObservationModel $observation): array
    {
        return [
            'id'           => $observation->id,
            'student_id'   => $observation->student_id,
            'student_name' => $observation->student
                ? $observation->student->last_name . ', ' . $observation->student->name
                : null,
            'enrollment_no' => $observation->student?->enrollment_no,
            'section_id'   => $observation->section_id,
            'subject_id'   => $observation->subject_id,
            'period_id'    => $observation->period_id,
            'date'         => $observation->date?->format('Y-m-d'),
            'type'         => $observation->type,
            'description'  => $observation->description,
            'teacher_name' => $observation->user?->name ?? '',
        ];
    }
}
