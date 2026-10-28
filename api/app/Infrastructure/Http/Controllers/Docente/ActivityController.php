<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Activity\CreateActivity;
use App\Application\Activity\ToggleActivity;
use App\Application\Grade\RecalculateActivityPeriodGrades;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Infrastructure\Models\Period;
use App\Infrastructure\Support\ActivityWeighting;

class ActivityController extends Controller
{
    public function __construct(
        private readonly CreateActivity $createActivity,
        private readonly ToggleActivity $toggleActivity,
        private readonly RecalculateActivityPeriodGrades $recalculateActivityPeriodGrades,
    ) {}

    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            DB::table('course_offerings')->where('section_id', $request->integer('section_id'))
                ->where('subject_id', $request->integer('subject_id'))->lockForUpdate()->first();
            return $this->storeLocked($request);
        });
    }

    private function storeLocked(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'subject_id'  => 'required|integer|exists:subjects,id',
            'section_id'  => 'required|integer|exists:sections,id',
            'description' => 'nullable|string|max:2000',
            'type'        => 'nullable|string|max:50',
            'period_id'   => 'required|integer|exists:periods,id',
            'status'      => 'nullable|in:active,draft,inactive',
            'due_date'    => 'nullable|date',
            'weight'      => 'nullable|numeric|gt:0|max:100|decimal:0,2',
            'icon'        => 'nullable|string|max:50',
        ]);

        if (!$this->isPeriodOpen((int) $validated['period_id'])) {
            return response()->json([
                'message' => 'Este período está cerrado. Solicita permiso al coordinador para modificarlo.',
            ], 423);
        }

        $validated['user_id'] = Auth::id() ?? 1;

        // Resolve the active academic year server-side — client doesn't need to know it
        $activeYear = DB::table('academic_years')->where('active', true)->first();
        $validated['academic_year_id'] = $activeYear?->id ?? 1;

        $isAssignedCourse = DB::table('teacher_sections')
            ->where('user_id', $validated['user_id'])
            ->where('section_id', $validated['section_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->exists();

        if (!$isAssignedCourse) {
            return response()->json([
                'message' => 'No tienes permiso para crear actividades en este curso.',
            ], 403);
        }

        if ($this->hasGradesUnderReviewOrOfficial(
            (int) $validated['subject_id'],
            (int) $validated['section_id'],
            (int) $validated['period_id']
        )) {
            return response()->json([
                'message' => 'Estas calificaciones ya están en revisión u oficiales. No puedes modificar este workspace.',
            ], 423);
        }

        $offeringId = DB::table('course_offerings')->where('section_id', $validated['section_id'])->where('subject_id', $validated['subject_id'])->value('id');
        abort_unless($offeringId, 404);
        $this->authorizeWeights((int) $offeringId, (int) $validated['period_id']);
        $activity = $this->createActivity->execute($validated);
        if (ActivityWeighting::summary((int) $validated['section_id'], (int) $validated['subject_id'], (int) $validated['period_id'])['mode'] === 'weighted') {
            ActivityWeighting::recalculate((int) $validated['section_id'], (int) $validated['subject_id'], (int) $validated['period_id']);
        }

        return response()->json([
            'message'  => 'Actividad creada.',
            'activity' => $activity,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $model = \App\Infrastructure\Models\Activity::findOrFail($id);
            DB::table('course_offerings')->where('id', $model->course_offering_id)->lockForUpdate()->first();
            return $this->updateLocked($request, $id);
        });
    }

    private function updateLocked(Request $request, int $id): JsonResponse
    {
        if ($request->exists('weight')) {
            $data = $request->validate(['weight' => 'required|numeric|gt:0|max:100|decimal:0,2']);
            $model = \App\Infrastructure\Models\Activity::findOrFail($id);
            $this->authorizeWeights((int) $model->course_offering_id, (int) $model->period_id);
            $model->update(['weight' => $data['weight'], 'weight_configured' => true]);
            ActivityWeighting::recalculate((int) $model->section_id, (int) $model->subject_id, (int) $model->period_id);
            return response()->json(['message' => 'Ponderación guardada. Las actividades activas deben sumar 100%.',
                'weighting' => ActivityWeighting::summary((int) $model->section_id, (int) $model->subject_id, (int) $model->period_id)]);
        }
        // Rename request: PATCH with { name }
        if ($request->has('name')) {
            $validated = $request->validate(['name' => 'required|string|max:255']);
            $model     = \App\Infrastructure\Models\Activity::findOrFail($id);

            if (!$this->isPeriodOpen((int) $model->period_id)) {
                return response()->json([
                    'message' => 'Este período está cerrado. Solicita permiso al coordinador para modificarlo.',
                ], 423);
            }

            if ($this->hasGradesUnderReviewOrOfficial((int) $model->subject_id, (int) $model->section_id, (int) $model->period_id)) {
                return response()->json([
                    'message' => 'Estas calificaciones ya están en revisión u oficiales. No puedes modificar este workspace.',
                ], 423);
            }

            if ((int) $model->user_id !== (int) (Auth::id() ?? 1)) {
                return response()->json([
                    'message' => 'No tienes permiso para modificar esta actividad.',
                ], 403);
            }

            $model->name = $validated['name'];
            $model->save();

            return response()->json([
                'message'  => 'Nombre actualizado.',
                'activity' => [
                    'id'      => $model->id,
                    'name'    => $model->name,
                    'is_base' => (bool) $model->is_base,
                    'active'  => (bool) $model->active,
                ],
            ]);
        }

        // Toggle-active request: PATCH with empty body or { active }
        $model = \App\Infrastructure\Models\Activity::findOrFail($id);

        if (!$this->isPeriodOpen((int) $model->period_id)) {
            return response()->json([
                'message' => 'Este período está cerrado. Solicita permiso al coordinador para modificarlo.',
            ], 423);
        }

        if ($this->hasGradesUnderReviewOrOfficial((int) $model->subject_id, (int) $model->section_id, (int) $model->period_id)) {
            return response()->json([
                'message' => 'Estas calificaciones ya están en revisión u oficiales. No puedes modificar este workspace.',
            ], 423);
        }

        $canToggle = $model->is_base
            ? $this->isAssignedToOffering((int) (Auth::id() ?? 1), (int) $model->course_offering_id)
            : (int) $model->user_id === (int) (Auth::id() ?? 1);
        if (! $canToggle) {
            return response()->json([
                'message' => 'No tienes permiso para modificar esta actividad.',
            ], 403);
        }

        $activity = DB::transaction(function () use ($id, $model): array {
            $activity = $this->toggleActivity->execute($id);
            $activity['recalculated_students'] = DB::table('activity_scores')->where('activity_id', $id)->distinct()->count('student_id');
            if (ActivityWeighting::summary((int) $model->section_id, (int) $model->subject_id, (int) $model->period_id)['mode'] === 'weighted') {
                ActivityWeighting::recalculate((int) $model->section_id, (int) $model->subject_id, (int) $model->period_id);
            } else {
                $this->recalculateActivityPeriodGrades->execute($id, (int) $model->subject_id, (int) $model->period_id, (int) $model->section_id);
            }

            return $activity;
        });
        $state    = $activity['active'] ? 'activada' : 'desactivada';

        return response()->json([
            'message'  => "Actividad {$state}.",
            'activity' => $activity,
        ]);
    }

    public function weights(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'period_id' => 'required|integer|exists:periods,id',
            'weights' => 'required|array|min:1',
            'weights.*.id' => 'required|integer|distinct',
            'weights.*.weight' => 'required|numeric|gt:0|max:100|decimal:0,2',
        ]);
        return DB::transaction(function () use ($data) {
            $offering = DB::table('course_offerings')->where('section_id', $data['section_id'])
                ->where('subject_id', $data['subject_id'])->lockForUpdate()->first();
            abort_unless($offering, 404);
            $this->authorizeWeights((int) $offering->id, (int) $data['period_id']);
            $active = DB::table('course_activities')->where('course_offering_id', $offering->id)
                ->where('period_id', $data['period_id'])->where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $ids = array_map(fn ($w) => (int) $w['id'], $data['weights']);
            sort($ids);
            if ($active !== $ids || array_sum(array_map(fn ($w) => (int) round($w['weight'] * 100), $data['weights'])) !== 10000) {
                throw \Illuminate\Validation\ValidationException::withMessages(['weights' => 'Incluye todas las actividades activas de este curso y período, con un total de 100%.']);
            }
            foreach ($data['weights'] as $weight) {
                DB::table('course_activities')->where('id', $weight['id'])->update([
                    'weight' => $weight['weight'], 'weight_configured' => true, 'updated_at' => now(),
                ]);
            }
            ActivityWeighting::recalculate((int) $data['section_id'], (int) $data['subject_id'], (int) $data['period_id']);
            return response()->json(['message' => 'Ponderaciones guardadas y borradores recalculados.',
                'weighting' => ActivityWeighting::summary((int) $data['section_id'], (int) $data['subject_id'], (int) $data['period_id'])]);
        });
    }

    private function authorizeWeights(int $offeringId, int $periodId): void
    {
        abort_unless($this->isAssignedToOffering((int) Auth::id(), $offeringId), 403, 'No tienes permiso para ponderar este curso.');
        $offering = DB::table('course_offerings')->join('sections', 'sections.id', '=', 'course_offerings.section_id')
            ->where('course_offerings.id', $offeringId)->first(['course_offerings.subject_id', 'course_offerings.section_id', 'sections.academic_year_id']);
        abort_unless(Period::where('id', $periodId)->where('academic_year_id', $offering->academic_year_id)->exists(), 422, 'El período no pertenece al curso.');
        abort_unless($this->isPeriodOpen($periodId), 423, 'El período no admite cambios.');
        abort_if($this->hasGradesUnderReviewOrOfficial((int) $offering->subject_id, (int) $offering->section_id, $periodId), 423, 'No se pueden cambiar ponderaciones de notas en revisión u oficiales.');
    }

    private function isPeriodOpen(int $periodId): bool
    {
        return Period::find($periodId)?->isOpenForTeacher() ?? false;
    }

    private function hasGradesUnderReviewOrOfficial(int $subjectId, int $sectionId, int $periodId): bool
    {
        return DB::table('period_grades')
            ->where('period_grades.subject_id', $subjectId)
            ->where('period_grades.period_id', $periodId)
            ->where('period_grades.section_id', $sectionId)
            ->whereIn('period_grades.status', ['in_review', 'official'])
            ->exists();
    }

    private function isAssignedToOffering(int $teacherId, int $offeringId): bool
    {
        if (DB::table('teacher_assignments')->where('teacher_id', $teacherId)
            ->where('course_offering_id', $offeringId)->where('active', true)->exists()) {
            return true;
        }

        return DB::table('course_offerings')->join('sections', 'sections.id', '=', 'course_offerings.section_id')
            ->join('teacher_sections', function ($join) use ($teacherId) {
                $join->on('teacher_sections.section_id', '=', 'sections.id')
                    ->on('teacher_sections.subject_id', '=', 'course_offerings.subject_id')
                    ->on('teacher_sections.academic_year_id', '=', 'sections.academic_year_id')
                    ->where('teacher_sections.user_id', '=', $teacherId);
            })->where('course_offerings.id', $offeringId)->exists();
    }
}
