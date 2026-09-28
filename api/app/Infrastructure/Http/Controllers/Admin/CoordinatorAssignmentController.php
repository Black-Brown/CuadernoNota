<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CoordinatorAssignmentController extends Controller
{
    public function show(User $user)
    {
        abort_unless($user->role === 'coordinator', 422, 'Selecciona un coordinador.');
        return response()->json([
            'coordinator_level' => $user->coordinator_level,
            'unclassified_grades' => DB::table('grades')->whereRaw('LOWER(TRIM(level)) NOT IN (?, ?)', ['primaria', 'secundaria'])->get(['id', 'name', 'level']),
            'section_ids' => DB::table('coordinator_sections')->where('user_id', $user->id)->pluck('section_id'),
            'sections' => DB::table('sections')->join('grades', 'grades.id', '=', 'sections.grade_id')
                ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
                ->orderByDesc('academic_years.start_date')->orderBy('grades.sort_order')->orderBy('sections.name')
                ->get(['sections.id', 'sections.name', 'sections.shift', 'grades.name as grade_name', 'academic_years.name as year_name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->role === 'coordinator', 422, 'Selecciona un coordinador.');
        $data = $request->validate([
            'section_ids' => ['present', 'array', 'max:500'],
            'section_ids.*' => ['integer', 'distinct', 'exists:sections,id'],
        ]);
        DB::transaction(function () use ($user, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'coordinator', 422);
            abort_if($locked->coordinator_level !== null, 409, 'Este coordinador supervisa un nivel completo. Modifica su nivel desde Usuarios.');
            DB::table('coordinator_sections')->where('user_id', $user->id)->delete();
            foreach ($data['section_ids'] as $sectionId) {
                DB::table('coordinator_sections')->insert([
                    'user_id' => $user->id, 'section_id' => $sectionId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        return response()->json(['message' => 'Secciones de supervisión actualizadas.']);
    }
}
