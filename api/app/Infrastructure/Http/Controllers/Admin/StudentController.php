<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Student\BulkReplaceStudentEnrollmentNumbers;
use App\Application\Student\ImportStudentsFromCsv;
use App\Infrastructure\Models\AcademicYear;
use App\Infrastructure\Models\Section;
use App\Infrastructure\Models\Student;
use App\Infrastructure\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 25), 1), 1000);

        return response()->json(Student::with(['section.grade', 'section.academicYear'])
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->boolean('pending'), fn ($q) => $q->whereNull('section_id'))
            ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id))
            ->when($request->academic_year_id, fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when($request->grade_id, fn ($q, $id) => $q->whereHas('section', fn ($section) => $section->where('grade_id', $id)))
            ->when($request->search, fn ($q, $search) => $q->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('enrollment_no', 'like', "%{$search}%")))
            ->orderBy('last_name')->orderBy('name')->paginate($perPage));
    }

    public function workspaces(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]);
        $year = isset($data['academic_year_id'])
            ? AcademicYear::query()->findOrFail($data['academic_year_id'])
            : AcademicYear::query()->where('active', true)->orderByDesc('start_date')->first()
                ?? AcademicYear::query()->orderByDesc('start_date')->first();

        $sections = $year
            ? Section::query()
                ->with(['grade:id,name,level,sort_order', 'academicYear:id,name'])
                ->where('academic_year_id', $year->id)
                ->withCount('students')
                ->withCount(['students as active_students_count' => fn ($query) => $query->where('active', true)])
                ->withCount(['students as inactive_students_count' => fn ($query) => $query->where('active', false)])
                ->get()
                ->filter(fn (Section $section) => $section->students_count > 0)
                ->sortBy(fn (Section $section) => sprintf('%08d-%s', $section->grade?->sort_order ?? PHP_INT_MAX, $section->name), SORT_NATURAL)
                ->values()
                ->map(fn (Section $section) => [
                    'id' => $section->id,
                    'grade_id' => $section->grade_id,
                    'grade_name' => $section->grade?->name,
                    'grade_level' => $section->grade?->level,
                    'section_name' => $section->name,
                    'shift' => $section->shift,
                    'academic_year_id' => $section->academic_year_id,
                    'academic_year_name' => $section->academicYear?->name,
                    'students_count' => $section->students_count,
                    'active_students_count' => $section->active_students_count,
                    'inactive_students_count' => $section->inactive_students_count,
                ])
            : collect();

        $pendingQuery = Student::query()->whereNull('section_id');
        $pending = [
            'students_count' => (clone $pendingQuery)->count(),
            'active_students_count' => (clone $pendingQuery)->where('active', true)->count(),
            'inactive_students_count' => (clone $pendingQuery)->where('active', false)->count(),
        ];

        return response()->json([
            'academic_year' => $year?->only(['id', 'name', 'start_date', 'end_date', 'active']),
            'summary' => [
                'workspaces' => $sections->count(),
                'students' => $sections->sum('students_count') + $pending['students_count'],
                'active_students' => $sections->sum('active_students_count') + $pending['active_students_count'],
                'inactive_students' => $sections->sum('inactive_students_count') + $pending['inactive_students_count'],
            ],
            'workspaces' => $sections,
            'pending' => $pending,
        ]);
    }

    public function show(Student $student): JsonResponse
    {
        $student->load([
            'enrollments' => fn ($query) => $query->latest('enrolled_at'),
            'enrollments.section.grade',
            'enrollments.section.academicYear',
            'periodGrades' => fn ($query) => $query->latest('period_id'),
            'periodGrades.subject:id,name',
            'periodGrades.period:id,academic_year_id,number,name',
            'periodGrades.period.academicYear:id,name',
            'finalGrades.subject:id,name',
            'finalGrades.academicYear:id,name',
            'attendances' => fn ($query) => $query->latest('date'),
            'attendances.section.grade:id,name',
            'observations' => fn ($query) => $query->latest('date'),
            'observations.user:id,name',
            'observations.subject:id,name',
            'observations.period:id,name',
            'alerts' => fn ($query) => $query->latest(),
            'alerts.resolver:id,name',
        ]);

        $scoredGrades = $student->periodGrades->whereNotNull('period_score');
        $attendanceTotal = $student->attendances->count();
        $presentDays = $student->attendances->whereIn('code', ['P', 'T'])->count();
        $student->setAttribute('summary', [
            'average' => $scoredGrades->isNotEmpty() ? round($scoredGrades->avg('period_score'), 2) : null,
            'subjects' => $student->periodGrades->pluck('subject_id')->unique()->count(),
            'official_grades' => $student->periodGrades->where('status', 'official')->count(),
            'attendance_total' => $attendanceTotal,
            'attendance_percentage' => $attendanceTotal > 0 ? round(($presentDays / $attendanceTotal) * 100, 1) : null,
            'unresolved_alerts' => $student->alerts->where('resolved', false)->count(),
            'observations' => $student->observations->count(),
        ]);

        return response()->json($student);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $student = DB::transaction(function () use ($data, $request) {
            $section = isset($data['section_id']) ? $this->sectionWithAcademicYear((int) $data['section_id']) : null;
            if ($section) {
                $this->assertEnrollmentDateWithinAcademicYear($data['enrolled_at'], $section);
            }
            $student = Student::create([
                'name' => $data['name'], 'last_name' => $data['last_name'], 'enrollment_no' => $data['enrollment_no'],
                'section_id' => $section?->id, 'academic_year_id' => $section?->academic_year_id, 'active' => true,
            ]);
            if ($section) {
                StudentEnrollment::create([
                    'student_id' => $student->id, 'section_id' => $section->id, 'status' => 'active',
                    'enrolled_at' => $data['enrolled_at'], 'created_by' => $request->user()->id,
                ]);
            }

            return $student;
        });

        return response()->json($student->load('section.grade'), 201);
    }

    public function previewImport(Request $request, ImportStudentsFromCsv $importer): JsonResponse
    {
        $file = $this->validatedCsv($request);

        return response()->json($importer->analyze($file));
    }

    public function import(Request $request, ImportStudentsFromCsv $importer): JsonResponse
    {
        return response()->json($importer->execute($this->validatedCsv($request)), 201);
    }

    public function previewBulkReplace(Request $request, BulkReplaceStudentEnrollmentNumbers $replacer): JsonResponse
    {
        return response()->json($replacer->preview($this->validatedBulkReplace($request)));
    }

    public function bulkReplace(Request $request, BulkReplaceStudentEnrollmentNumbers $replacer): JsonResponse
    {
        return response()->json($replacer->execute($this->validatedBulkReplace($request)));
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $student->update($request->validate($this->rules($student)));

        return response()->json($student->fresh());
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate(['confirmation' => ['required', 'string']]);

        DB::transaction(function () use ($student, $data) {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);
            if ($data['confirmation'] !== $student->enrollment_no) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Escribe la matrícula exacta del estudiante para confirmar la eliminación.',
                ]);
            }

            // Never cascade-delete an academic record, including historical enrollments.
            $hasHistory = $student->section_id !== null
                || $student->enrollments()->exists()
                || $student->activityScores()->exists()
                || $student->periodGrades()->exists()
                || $student->finalGrades()->exists()
                || $student->attendances()->exists()
                || $student->observations()->exists()
                || $student->alerts()->exists()
                || DB::table('student_promotions')->where('student_id', $student->id)->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'student' => 'No se puede eliminar este estudiante porque tiene una sección asignada o historial académico. Puedes desactivarlo para conservar sus matrículas, calificaciones y demás registros.',
                ]);
            }

            $student->delete();
        });

        return response()->json(['message' => 'Estudiante eliminado definitivamente.']);
    }

    public function enroll(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate(['section_id' => ['required', 'exists:sections,id'], 'enrolled_at' => ['required', 'date']]);
        $enrollment = DB::transaction(function () use ($student, $data, $request) {
            $section = $this->sectionWithAcademicYear((int) $data['section_id']);
            $this->assertEnrollmentDateWithinAcademicYear($data['enrolled_at'], $section);
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

    public function reactivate(Student $student): JsonResponse
    {
        DB::transaction(function () use ($student): void {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);
            if ($student->active) {
                throw ValidationException::withMessages([
                    'student' => 'El estudiante ya se encuentra activo.',
                ]);
            }
            if ($student->enrollments()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages([
                    'student' => 'El estudiante tiene una matrícula activa y debe revisarse antes de reactivarlo.',
                ]);
            }

            // The previous enrollment remains in history. Reactivation returns the
            // student to the placement queue so a new current section is explicit.
            $student->update([
                'active' => true,
                'section_id' => null,
                'academic_year_id' => null,
                'deactivation_date' => null,
                'deactivation_reason' => null,
            ]);
        });

        return response()->json([
            'message' => 'Estudiante reactivado y pendiente de asignación de sección.',
            'student' => $student->fresh()->load(['section.grade', 'section.academicYear']),
        ]);
    }

    private function rules(?Student $student = null): array
    {
        $presence = $student ? ['sometimes', 'required'] : ['required'];
        $rules = [
            'name' => [...$presence, 'string', 'max:60'], 'last_name' => [...$presence, 'string', 'max:60'],
            'enrollment_no' => [...$presence, 'string', 'max:20', Rule::unique('students')->ignore($student?->id)],
        ];
        if (! $student) {
            $rules['section_id'] = ['nullable', 'exists:sections,id', 'required_with:enrolled_at'];
            $rules['enrolled_at'] = ['nullable', 'date', 'required_with:section_id'];
        }

        return $rules;
    }

    private function validatedCsv(Request $request): UploadedFile
    {
        $file = $request->validate(['file' => ['required', 'file', 'max:5120']], [
            'file.required' => 'Selecciona un archivo CSV.',
            'file.file' => 'Selecciona un archivo CSV válido.',
            'file.max' => 'El archivo CSV no puede superar los 5 MB.',
            'file.uploaded' => 'No se pudo subir el archivo. Revisa que no supere los 5 MB.',
        ])['file'];
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            throw ValidationException::withMessages(['file' => 'Selecciona un archivo con extensión CSV.']);
        }

        return $file;
    }

    private function validatedBulkReplace(Request $request): array
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'search' => ['required', 'string', 'max:20'],
            'replace' => ['present', 'nullable', 'string', 'max:20'],
        ], [
            'student_ids.required' => 'Selecciona al menos un estudiante.',
            'student_ids.max' => 'Puedes modificar hasta 1,000 estudiantes por operación.',
            'search.required' => 'Indica el texto que deseas buscar en las matrículas.',
            'replace.present' => 'Indica el texto de reemplazo; puede quedar vacío.',
        ]);

        $data['replace'] ??= '';

        return $data;
    }

    private function sectionWithAcademicYear(int $sectionId): object
    {
        return DB::table('sections')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->where('sections.id', $sectionId)
            ->firstOrFail([
                'sections.id', 'sections.academic_year_id',
                'academic_years.start_date as year_start_date', 'academic_years.end_date as year_end_date',
            ]);
    }

    private function assertEnrollmentDateWithinAcademicYear(string $date, object $section): void
    {
        if ($date < $section->year_start_date || $date > $section->year_end_date) {
            throw ValidationException::withMessages([
                'enrolled_at' => "La fecha de inscripción debe estar entre {$section->year_start_date} y {$section->year_end_date}.",
            ]);
        }
    }
}
