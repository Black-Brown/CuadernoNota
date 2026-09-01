<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Grade\Repositories\ActivityRepositoryInterface;
use App\Infrastructure\Models\Activity as ActivityModel;

class EloquentActivityRepository implements ActivityRepositoryInterface
{
    public function findBySubjectWithScoreCount(int $subjectId, ?int $sectionId = null, ?int $periodId = null): array
    {
        return ActivityModel::query()
            ->with(['template', 'courseOffering.section'])
            ->whereHas('courseOffering', fn($query) => $query
                ->where('subject_id', $subjectId)
                ->when($sectionId, fn($offeringQuery) => $offeringQuery->where('section_id', $sectionId)))
            ->when($periodId, fn($query) => $query->where('course_activities.period_id', $periodId))
            ->withCount(['activityScores as score_count' => fn($q) => $q
                ->whereNotNull('score')
                ->when($periodId, fn($scoreQuery) => $scoreQuery->where('period_id', $periodId))
            ])
            ->orderByRaw('CASE WHEN activity_template_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('course_activities.name')
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

        $offeringId = \App\Infrastructure\Models\CourseOffering::query()
            ->where('section_id', $data['section_id'])
            ->where('subject_id', $data['subject_id'])
            ->value('id');

        if (!$offeringId) {
            throw new \InvalidArgumentException('El curso indicado no existe.');
        }

        $activity = ActivityModel::create([
            'course_offering_id' => $offeringId,
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'type'             => $data['type'] ?? null,
            'status'           => $status,
            'due_date'         => $data['due_date'] ?? null,
            'weight'           => isset($data['weight']) ? (float) $data['weight'] : null,
            'period_id'        => $data['period_id'] ?? null,
            'created_by'       => $data['user_id'],
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
        $activity->status = $activity->status === 'active' ? 'inactive' : 'active';
        $activity->save();

        return [
            'id'      => $activity->id,
            'name'    => $activity->name,
            'is_base' => (bool) $activity->is_base,
            'active'  => (bool) $activity->active,
        ];
    }
}
