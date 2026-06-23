<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Activity\CreateActivity;
use App\Application\Activity\ToggleActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'description' => 'nullable|string|max:2000',
            'type'        => 'nullable|string|max:50',
            'period_id'   => 'nullable|integer|exists:periods,id',
            'status'      => 'nullable|in:active,draft,inactive',
            'due_date'    => 'nullable|date',
            'weight'      => 'nullable|numeric|min:0|max:100',
            'icon'        => 'nullable|string|max:50',
        ]);

        $validated['user_id'] = Auth::id() ?? 1;

        // Resolve the active academic year server-side — client doesn't need to know it
        $activeYear = DB::table('academic_years')->where('active', true)->first();
        $validated['academic_year_id'] = $activeYear?->id ?? 1;

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
        $activity = $this->toggleActivity->execute($id);
        $state    = $activity['active'] ? 'activada' : 'desactivada';

        return response()->json([
            'message'  => "Actividad {$state}.",
            'activity' => $activity,
        ]);
    }
}
