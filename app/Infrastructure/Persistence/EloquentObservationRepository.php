<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Observation\Repositories\ObservationRepositoryInterface;
use App\Infrastructure\Models\Observation as ObservationModel;

class EloquentObservationRepository implements ObservationRepositoryInterface
{
    public function findByStudent(int $studentId): array
    {
        return ObservationModel::where('student_id', $studentId)
            ->with('user:id,name')
            ->orderByDesc('date')
            ->get()
            ->map(fn($o) => [
                'id'           => $o->id,
                'date'         => $o->date?->format('Y-m-d'),
                'type'         => $o->type,
                'description'  => $o->description,
                'teacher_name' => $o->user?->name ?? '',
            ])
            ->all();
    }

    public function save(array $data): void
    {
        ObservationModel::create([
            'student_id'  => $data['student_id'],
            'user_id'     => $data['user_id'],
            'date'        => $data['date'],
            'type'        => $data['type'],
            'description' => $data['description'],
        ]);
    }
}
