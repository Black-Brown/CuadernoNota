<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherAssignmentController extends Controller
{
    public function options(): JsonResponse
    {
        return response()->json([
            'teachers' => User::query()
                ->where('role', 'teacher')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'active']),
            'courses' => DB::table('course_offerings')
                ->join('sections', 'course_offerings.section_id', '=', 'sections.id')
                ->join('grades', 'sections.grade_id', '=', 'grades.id')
                ->join('subjects', 'course_offerings.subject_id', '=', 'subjects.id')
                ->join('academic_years', 'sections.academic_year_id', '=', 'academic_years.id')
                ->where('course_offerings.active', true)
                ->where('subjects.active', true)
                ->orderBy('academic_years.start_date', 'desc')
                ->orderBy('grades.sort_order')
                ->orderBy('sections.name')
                ->orderBy('subjects.name')
                ->get([
                    'course_offerings.id',
                    'sections.id as section_id',
                    'subjects.id as subject_id',
                    'grades.name as grade_name',
                    'sections.name as section_name',
                    'sections.shift',
                    'subjects.name as subject_name',
                    'academic_years.name as academic_year_name',
                ]),
        ]);
    }

    public function index(): JsonResponse
    {
        $assignments = TeacherAssignment::query()
            ->with(['teacher:id,name,email,active', 'assignedBy:id,name,email', 'courseOffering.section.grade', 'courseOffering.section.academicYear', 'courseOffering.subject'])
            ->latest('assigned_at')
            ->get();

        return response()->json($assignments);
    }

    public function storeTeacher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $teacher = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password'] ?? Str::random(40)),
            'role' => 'teacher',
            'active' => $data['active'] ?? true,
        ]);

        return response()->json($teacher->only(['id', 'name', 'email', 'role', 'active']), 201);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
            'course_offering_id' => ['required_without:course_offering_ids', 'integer', 'exists:course_offerings,id'],
            'course_offering_ids' => ['required_without:course_offering_id', 'array', 'min:1', 'max:100'],
            'course_offering_ids.*' => ['integer', 'distinct', 'exists:course_offerings,id'],
        ]);

        $teacher = User::findOrFail($data['teacher_id']);
        if ($teacher->role !== 'teacher' || ! $teacher->active) {
            throw ValidationException::withMessages([
                'teacher_id' => 'El usuario debe ser un profesor activo.',
            ]);
        }

        $courseIds = collect($data['course_offering_ids'] ?? [$data['course_offering_id']])->map(fn ($id) => (int) $id)->unique()->values();
        $assignments = DB::transaction(function () use ($teacher, $courseIds, $request) {
            $activeCourseIds = DB::table('course_offerings')->whereIn('id', $courseIds)->where('active', true)->lockForUpdate()->pluck('id');
            if ($activeCourseIds->count() !== $courseIds->count()) {
                throw ValidationException::withMessages(['course_offering_ids' => 'Todos los cursos seleccionados deben estar activos.']);
            }
            $result = collect();
            foreach ($courseIds as $courseId) {
                $result->push(TeacherAssignment::updateOrCreate(
                    ['teacher_id' => $teacher->id, 'course_offering_id' => $courseId],
                    ['assigned_by' => $request->user()->id, 'assigned_at' => now(), 'active' => true]
                ));
            }

            return $result;
        });

        // Preserve the original one-course response contract for existing clients.
        if (! isset($data['course_offering_ids'])) {
            return response()->json($assignments->first()->fresh(), 201);
        }

        return response()->json([
            'message' => $assignments->count().' cursos asignados correctamente.',
            'assigned_count' => $assignments->count(),
            'assignments' => TeacherAssignment::query()->whereIn('id', $assignments->pluck('id'))
                ->with(['teacher:id,name,email', 'courseOffering.section.grade', 'courseOffering.section.academicYear', 'courseOffering.subject'])
                ->get(),
        ], 201);
    }

    public function update(Request $request, TeacherAssignment $teacherAssignment): JsonResponse
    {
        $data = $request->validate([
            'course_offering_id' => [
                'sometimes',
                'integer',
                'exists:course_offerings,id',
                Rule::unique('teacher_assignments')->where(fn ($query) => $query
                    ->where('teacher_id', $teacherAssignment->teacher_id)
                    ->where('course_offering_id', $request->integer('course_offering_id'))
                )->ignore($teacherAssignment->id),
            ],
            'active' => ['sometimes', 'boolean'],
        ]);

        $teacher = $teacherAssignment->teacher;
        if (($data['active'] ?? $teacherAssignment->active) && ($teacher->role !== 'teacher' || ! $teacher->active)) {
            throw ValidationException::withMessages([
                'active' => 'No se puede activar una asignación de un profesor inactivo.',
            ]);
        }

        if (isset($data['course_offering_id'])) {
            $courseIsActive = DB::table('course_offerings')
                ->join('subjects', 'subjects.id', '=', 'course_offerings.subject_id')
                ->where('course_offerings.id', $data['course_offering_id'])
                ->where('course_offerings.active', true)
                ->where('subjects.active', true)
                ->exists();

            if (! $courseIsActive) {
                throw ValidationException::withMessages([
                    'course_offering_id' => 'El curso seleccionado debe estar activo.',
                ]);
            }
        }

        $teacherAssignment->update($data);

        return response()->json($teacherAssignment->fresh());
    }

    public function destroy(TeacherAssignment $teacherAssignment): JsonResponse
    {
        $teacherAssignment->delete();

        return response()->json(['message' => 'Asignación docente eliminada correctamente.']);
    }
}
