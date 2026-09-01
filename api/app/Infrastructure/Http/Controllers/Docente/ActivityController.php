<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Activity\CreateActivity;
use App\Application\Activity\ToggleActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Infrastructure\Models\Period;

class ActivityController extends Controller
{
    public function __construct(
        private readonly CreateActivity $createActivity,
        private readonly ToggleActivity $toggleActivity,
    ) {}

    public function store(Request $request): JsonResponse
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
            'weight'      => 'nullable|numeric|min:0|max:100',
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

        $activity = $this->createActivity->execute($validated);

        return response()->json([
            'message'  => 'Actividad creada.',
            'activity' => $activity,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
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

        $activity = $this->toggleActivity->execute($id);
        $state    = $activity['active'] ? 'activada' : 'desactivada';

        return response()->json([
            'message'  => "Actividad {$state}.",
            'activity' => $activity,
        ]);
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
