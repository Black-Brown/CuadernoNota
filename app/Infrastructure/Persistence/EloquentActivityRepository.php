<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Repositories\ActivityRepositoryInterface;
use App\Infrastructure\Models\Activity as ActivityModel;

class EloquentActivityRepository implements ActivityRepositoryInterface
{
    public function findBySubjectWithScoreCount(int $subjectId, ?int $sectionId = null, ?int $periodId = null): array
    {
        return ActivityModel::where('subject_id', $subjectId)
            ->when($sectionId, fn($query) => $query->where('section_id', $sectionId))
            ->when($periodId, fn($query) => $query->where('period_id', $periodId))
            ->withCount(['activityScores as score_count' => fn($q) => $q
                ->whereNotNull('score')
                ->when($periodId, fn($scoreQuery) => $scoreQuery->where('period_id', $periodId))
            ])
            ->orderBy('is_base', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'name'        => $a->name,
                'description' => $a->description,
                'type'        => $a->type,
                'status'      => $a->status ?? ($a->active ? 'active' : 'inactive'),
                'due_date'    => $a->due_date ? $a->due_date->format('Y-m-d') : null,
                'weight'      => $a->weight,
                'icon'        => $a->icon ?? 'assignment',
                'is_base'     => (bool) $a->is_base,
                'active'      => (bool) $a->active,
                'section_id'  => $a->section_id,
                'period_id'   => $a->period_id,
                'score_count' => (int) $a->score_count,
            ])
            ->all();
    }

    public function save(array $data): array
    {
        $status = $data['status'] ?? 'active';

        $activity = ActivityModel::create([
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'type'             => $data['type'] ?? null,
            'status'           => $status,
            'due_date'         => $data['due_date'] ?? null,
            'weight'           => isset($data['weight']) ? (float) $data['weight'] : null,
            'icon'             => $data['icon'] ?? 'assignment',
            'is_base'          => false,
            'subject_id'       => $data['subject_id'],
            'section_id'       => $data['section_id'],
            'period_id'        => $data['period_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'],
            'user_id'          => $data['user_id'],
            'active'           => $status === 'active',
        ]);

        return [
            'id'          => $activity->id,
            'name'        => $activity->name,
            'description' => $activity->description,
            'type'        => $activity->type,
            'status'      => $activity->status,
            'due_date'    => $activity->due_date ? $activity->due_date->format('Y-m-d') : null,
            'weight'      => $activity->weight,
            'icon'        => $activity->icon ?? 'assignment',
            'is_base'     => false,
            'active'      => (bool) $activity->active,
            'section_id'  => $activity->section_id,
            'period_id'   => $activity->period_id,
            'score_count' => 0,
        ];
    }

    public function toggleActive(int $activityId): array
    {
        $activity = ActivityModel::findOrFail($activityId);
        $activity->active = !$activity->active;
        $activity->save();

        return [
            'id'      => $activity->id,
            'name'    => $activity->name,
            'is_base' => (bool) $activity->is_base,
            'active'  => (bool) $activity->active,
        ];
    }
}
