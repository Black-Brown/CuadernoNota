<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\Student;
use App\Infrastructure\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    public function previewImport(Request $request): JsonResponse
    {
        $file = $this->validatedCsv($request);

        return response()->json($this->analyzeCsv($file));
    }

    public function import(Request $request): JsonResponse
    {
        $analysis = $this->analyzeCsv($this->validatedCsv($request));
        if ($analysis['summary']['invalid'] > 0) {
            throw ValidationException::withMessages([
                'file' => "El archivo contiene {$analysis['summary']['invalid']} filas con errores. Revísalo antes de importar.",
            ]);
        }

        $created = DB::transaction(function () use ($analysis, $request): int {
            foreach ($analysis['rows'] as $row) {
                $data = $row['data'];
                $student = Student::create([
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'enrollment_no' => $data['enrollment_no'],
                    'section_id' => $row['section_id'],
                    'academic_year_id' => $row['academic_year_id'],
                    'active' => true,
                ]);
                StudentEnrollment::create([
                    'student_id' => $student->id,
                    'section_id' => $row['section_id'],
                    'status' => 'active',
                    'enrolled_at' => $data['enrolled_at'],
                    'created_by' => $request->user()->id,
                ]);
            }

            return count($analysis['rows']);
        });

        return response()->json([
            'message' => "{$created} estudiantes importados correctamente.",
            'imported' => $created,
        ], 201);
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

    private function rules(?Student $student = null): array
    {
        $mode = $student ? 'sometimes' : 'required';
        $rules = [
            'name' => [$mode, 'string', 'max:60'], 'last_name' => [$mode, 'string', 'max:60'],
            'enrollment_no' => [$mode, 'string', 'max:20', Rule::unique('students')->ignore($student?->id)],
        ];
        if (! $student) {
            $rules['section_id'] = ['nullable', 'exists:sections,id', 'required_with:enrolled_at'];
            $rules['enrolled_at'] = ['nullable', 'date', 'required_with:section_id'];
        }
        return $rules;
    }

    private function validatedCsv(Request $request): UploadedFile
    {
        $file = $request->validate(['file' => ['required', 'file', 'max:5120']])['file'];
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            throw ValidationException::withMessages(['file' => 'Selecciona un archivo con extensión CSV.']);
        }

        return $file;
    }

    private function analyzeCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'No fue posible leer el archivo CSV.']);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'El archivo CSV está vacío.']);
        }
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '');
        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers ?: []);
        $required = ['MATRICULA', 'NOMBRES', 'APELLIDOS', 'ANO_ESCOLAR', 'GRADO', 'SECCION', 'TANDA', 'FECHA_INSCRIPCION'];
        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Faltan columnas obligatorias: '.implode(', ', $missing).'.']);
        }

        $sections = DB::table('sections')
            ->join('grades', 'grades.id', '=', 'sections.grade_id')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->get([
                'sections.id', 'sections.academic_year_id', 'sections.name as section', 'sections.shift',
                'grades.name as grade', 'academic_years.name as year',
                'academic_years.start_date as year_start_date', 'academic_years.end_date as year_end_date',
            ])
            ->keyBy(fn ($section) => $this->sectionKey($section->year, $section->grade, $section->section, $section->shift));
        $existingEnrollments = DB::table('students')->pluck('enrollment_no')->mapWithKeys(fn ($value) => [$this->normalizeKey($value) => true]);
        $seenEnrollments = [];
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $values = array_pad($values, count($headers), '');
            $raw = array_combine($headers, array_slice($values, 0, count($headers)));
            $data = [
                'enrollment_no' => $this->clean($raw['MATRICULA'] ?? ''),
                'name' => $this->clean($raw['NOMBRES'] ?? ''),
                'last_name' => $this->clean($raw['APELLIDOS'] ?? ''),
                'academic_year' => $this->clean($raw['ANO_ESCOLAR'] ?? ''),
                'grade' => $this->clean($raw['GRADO'] ?? ''),
                'section' => $this->clean($raw['SECCION'] ?? ''),
                'shift' => $this->clean($raw['TANDA'] ?? ''),
                'enrolled_at' => $this->clean($raw['FECHA_INSCRIPCION'] ?? ''),
                'guardian_name' => $this->clean($raw['NOMBRE_TUTOR'] ?? ''),
            ];
            $errors = [];
            foreach (['enrollment_no' => 'Matrícula', 'name' => 'Nombres', 'last_name' => 'Apellidos'] as $field => $label) {
                if ($data[$field] === '') $errors[] = "{$label} es obligatorio.";
            }
            if (mb_strlen($data['enrollment_no']) > 20) $errors[] = 'La matrícula supera los 20 caracteres.';
            if (mb_strlen($data['name']) > 60 || mb_strlen($data['last_name']) > 60) $errors[] = 'El nombre o apellido supera los 60 caracteres.';
            $enrollmentKey = $this->normalizeKey($data['enrollment_no']);
            if (isset($existingEnrollments[$enrollmentKey])) $errors[] = 'La matrícula ya existe en el sistema.';
            if (isset($seenEnrollments[$enrollmentKey])) $errors[] = 'La matrícula está repetida dentro del archivo.';
            $seenEnrollments[$enrollmentKey] = true;
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['enrolled_at']);
            if (! $date || $date->format('Y-m-d') !== $data['enrolled_at']) $errors[] = 'La fecha debe usar el formato AAAA-MM-DD.';
            $section = $sections->get($this->sectionKey($data['academic_year'], $data['grade'], $data['section'], $data['shift']));
            if (! $section) $errors[] = 'No existe una sección que coincida con año, grado, sección y tanda.';
            if ($date && $section && ($data['enrolled_at'] < $section->year_start_date || $data['enrolled_at'] > $section->year_end_date)) {
                $errors[] = "La fecha de inscripción debe estar entre {$section->year_start_date} y {$section->year_end_date}.";
            }

            $rows[] = [
                'row_number' => $line,
                'valid' => $errors === [],
                'errors' => $errors,
                'warnings' => $data['guardian_name'] !== '' ? ['El tutor no se importará porque el módulo de tutores aún no existe.'] : [],
                'data' => $data,
                'section_id' => $section?->id,
                'academic_year_id' => $section?->academic_year_id,
                'section_label' => $section ? "{$section->grade} · Sección {$section->section} · {$section->shift} · {$section->year}" : null,
            ];
        }
        fclose($handle);

        if ($rows === []) throw ValidationException::withMessages(['file' => 'El archivo no contiene estudiantes.']);
        $valid = count(array_filter($rows, fn ($row) => $row['valid']));

        return ['summary' => ['total' => count($rows), 'valid' => $valid, 'invalid' => count($rows) - $valid], 'rows' => $rows];
    }

    private function normalizeHeader(string $value): string
    {
        $header = strtoupper(Str::ascii(trim($value, "\xEF\xBB\xBF \t\n\r\0\x0B")));

        return trim(preg_replace('/[^A-Z0-9]+/', '_', $header) ?? $header, '_');
    }

    private function clean(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function normalizeKey(string $value): string
    {
        return strtoupper(Str::ascii($this->clean($value)));
    }

    private function sectionKey(string $year, string $grade, string $section, string $shift): string
    {
        return implode('|', array_map(fn ($value) => $this->normalizeKey($value), [$year, $grade, $section, $shift]));
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
