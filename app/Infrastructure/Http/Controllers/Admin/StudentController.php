<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\Student;
use App\Infrastructure\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Student::with(['section.grade', 'section.academicYear'])
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id))
            ->when($request->search, fn ($q, $search) => $q->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('enrollment_no', 'like', "%{$search}%")))
            ->orderBy('last_name')->orderBy('name')->paginate($request->integer('per_page', 25)));
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json($student->load(['enrollments.section.grade', 'enrollments.section.academicYear']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $student = DB::transaction(function () use ($data, $request) {
            $section = DB::table('sections')->find($data['section_id']);
            $student = Student::create($data + ['academic_year_id' => $section->academic_year_id, 'active' => true]);
            StudentEnrollment::create([
                'student_id' => $student->id, 'section_id' => $section->id, 'status' => 'active',
                'enrolled_at' => $data['enrolled_at'], 'created_by' => $request->user()->id,
            ]);
            return $student;
        });
        return response()->json($student->load('section.grade'), 201);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $student->update($request->validate($this->rules($student)));
        return response()->json($student->fresh());
    }

    public function enroll(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate(['section_id' => ['required', 'exists:sections,id'], 'enrolled_at' => ['required', 'date']]);
        $enrollment = DB::transaction(function () use ($student, $data, $request) {
            $section = DB::table('sections')->find($data['section_id']);
            StudentEnrollment::where('student_id', $student->id)->where('status', 'active')->update(['status' => 'completed', 'ended_at' => $data['enrolled_at']]);
            $enrollment = StudentEnrollment::updateOrCreate(
                ['student_id' => $student->id, 'section_id' => $section->id],
                ['status' => 'active', 'enrolled_at' => $data['enrolled_at'], 'ended_at' => null, 'end_reason' => null, 'created_by' => $request->user()->id]
            );
            $student->update(['section_id' => $section->id, 'academic_year_id' => $section->academic_year_id, 'active' => true, 'deactivation_date' => null, 'deactivation_reason' => null]);
            return $enrollment;
        });
        return response()->json($enrollment->load('section.academicYear'), 201);
    }

    public function deactivate(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200'], 'date' => ['nullable', 'date']]);
        $date = $data['date'] ?? now()->toDateString();
        DB::transaction(function () use ($student, $data, $date) {
            $student->update(['active' => false, 'deactivation_date' => $date, 'deactivation_reason' => $data['reason']]);
            StudentEnrollment::where('student_id', $student->id)->where('status', 'active')->update(['status' => 'withdrawn', 'ended_at' => $date, 'end_reason' => $data['reason']]);
        });
        return response()->json(['message' => 'Estudiante dado de baja; su historial fue conservado.']);
    }

    private function rules(?Student $student = null): array
    {
        $mode = $student ? 'sometimes' : 'required';
        $rules = [
            'name' => [$mode, 'string', 'max:60'], 'last_name' => [$mode, 'string', 'max:60'],
            'enrollment_no' => [$mode, 'string', 'max:20', Rule::unique('students')->ignore($student?->id)],
        ];
        if (!$student) {
            $rules['section_id'] = ['required', 'exists:sections,id'];
            $rules['enrolled_at'] = ['required', 'date'];
        }
        return $rules;
    }
}
