<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Observation\CreateObservation;
use App\Application\Observation\GetObservations;
use App\Domain\Observation\Repositories\ObservationRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ObservationController extends Controller
{
    public function __construct(
        private readonly GetObservations    $getObservations,
        private readonly CreateObservation  $createObservation,
        private readonly ObservationRepositoryInterface $observationRepo,
    ) {}

    public function index(int $studentId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;

        $student = DB::table('students')->where('id', $studentId)->first(['id', 'section_id']);

        if (!$student) {
            return response()->json(['message' => 'Estudiante no encontrado.'], 404);
        }

        $isAssigned = DB::table('teacher_sections')
            ->where('user_id', $teacherId)
            ->where('section_id', $student->section_id)
            ->exists();

        if (!$isAssigned) {
            return response()->json(['message' => 'No tienes permiso para consultar este estudiante.'], 403);
        }

        $observations = $this->getObservations->execute($studentId);

        return response()->json(['observations' => $observations]);
    }

    public function course(Request $request, int $sectionId, int $subjectId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $periodId = $this->resolvePeriodId($request);

        if (!$this->isAssignedCourse($teacherId, $sectionId, $subjectId)) {
            return response()->json(['message' => 'No tienes permiso para consultar este curso.'], 403);
        }

        $course = $this->coursePayload($sectionId, $subjectId);
        $observations = $this->observationRepo->findByWorkspace($sectionId, $subjectId, $periodId);
        $students = $this->studentsWithObservationSummary($sectionId, $subjectId, $periodId);

        return response()->json([
            'period_id' => $periodId,
            'course' => $course,
            'summary' => [
                'total_observations' => count($observations),
                'students_with_observations' => collect($observations)->pluck('student_id')->unique()->count(),
                'academic' => collect($observations)->where('type', 'academic')->count(),
                'disciplinary' => collect($observations)->where('type', 'disciplinary')->count(),
                'incident' => collect($observations)->where('type', 'incident')->count(),
                'can_edit' => $this->canEditWorkspace($subjectId, $sectionId, $periodId),
            ],
            'students' => $students,
            'observations' => $observations,
        ]);
    }

    public function student(Request $request, int $sectionId, int $subjectId, int $studentId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $periodId = $this->resolvePeriodId($request);

        if (!$this->isAssignedCourse($teacherId, $sectionId, $subjectId)) {
            return response()->json(['message' => 'No tienes permiso para consultar este curso.'], 403);
        }

        if (!$this->studentBelongsToSection($studentId, $sectionId)) {
            return response()->json(['message' => 'El estudiante no pertenece a esta sección.'], 422);
        }

        return response()->json([
            'period_id' => $periodId,
            'course' => $this->coursePayload($sectionId, $subjectId),
            'student' => $this->studentPayload($studentId),
            'can_edit' => $this->canEditWorkspace($subjectId, $sectionId, $periodId),
            'observations' => $this->observationRepo->findByWorkspaceStudent($sectionId, $subjectId, $periodId, $studentId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'  => 'required|integer|exists:students,id',
            'section_id'  => 'nullable|required_with:subject_id,period_id|integer|exists:sections,id',
            'subject_id'  => 'nullable|required_with:section_id,period_id|integer|exists:subjects,id',
            'period_id'   => 'nullable|required_with:section_id,subject_id|integer|exists:periods,id',
            'date'        => 'required|date_format:Y-m-d',
            'type'        => 'required|in:academic,disciplinary,incident',
            'description' => 'required|string',
        ]);

        $validated['user_id'] = Auth::id() ?? 1;

        if (!$this->validateWorkspaceWrite($validated)) {
            return response()->json([
                'message' => 'No puedes registrar observaciones en este workspace.',
            ], 423);
        }

        $this->createObservation->execute($validated);

        return response()->json(['message' => 'Observación registrada.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $observation = DB::table('observations')->where('id', $id)->first();

        if (!$observation) {
            return response()->json(['message' => 'Observación no encontrada.'], 404);
        }

        if (!$this->canModifyObservation($observation)) {
            return response()->json([
                'message' => 'No puedes modificar esta observación en el estado actual del workspace.',
            ], 423);
        }

        $validated = $request->validate([
            'date'        => 'required|date_format:Y-m-d',
            'type'        => 'required|in:academic,disciplinary,incident',
            'description' => 'required|string',
        ]);

        $this->observationRepo->update($id, $validated);

        return response()->json(['message' => 'Observación actualizada.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $observation = DB::table('observations')->where('id', $id)->first();

        if (!$observation) {
            return response()->json(['message' => 'Observación no encontrada.'], 404);
        }

        if (!$this->canModifyObservation($observation)) {
            return response()->json([
                'message' => 'No puedes eliminar esta observación en el estado actual del workspace.',
            ], 423);
        }

        $this->observationRepo->delete($id);

        return response()->json(['message' => 'Observación eliminada.']);
    }

    private function validateWorkspaceWrite(array $data): bool
    {
        if (empty($data['section_id']) && empty($data['subject_id']) && empty($data['period_id'])) {
            $studentSectionId = DB::table('students')
                ->where('id', $data['student_id'])
                ->where('active', true)
                ->value('section_id');

            return $studentSectionId !== null
                && DB::table('teacher_sections')
                    ->where('user_id', $data['user_id'])
                    ->where('section_id', $studentSectionId)
                    ->exists();
        }

        $sectionId = (int) $data['section_id'];
        $subjectId = (int) $data['subject_id'];
        $periodId = (int) $data['period_id'];

        return $this->isAssignedCourse($data['user_id'], $sectionId, $subjectId)
            && $this->studentBelongsToSection((int) $data['student_id'], $sectionId)
            && $this->canEditWorkspace($subjectId, $sectionId, $periodId);
    }

    private function canModifyObservation(object $observation): bool
    {
        if (!$observation->section_id || !$observation->subject_id || !$observation->period_id) {
            return (int) $observation->user_id === (int) (Auth::id() ?? 1);
        }

        return $this->isAssignedCourse(Auth::id() ?? 1, (int) $observation->section_id, (int) $observation->subject_id)
            && $this->canEditWorkspace((int) $observation->subject_id, (int) $observation->section_id, (int) $observation->period_id);
    }

    private function resolvePeriodId(Request $request): int
    {
        $periodId = $request->integer('period_id');

        if ($periodId > 0) {
            return $periodId;
        }

        return (int) DB::table('periods')
            ->where('status', 'open')
            ->orderByDesc('number')
            ->value('id')
            ?: (int) DB::table('periods')->orderByDesc('id')->value('id');
    }

    private function isAssignedCourse(int $teacherId, int $sectionId, int $subjectId): bool
    {
        return DB::table('teacher_sections')
            ->where('user_id', $teacherId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function studentBelongsToSection(int $studentId, int $sectionId): bool
    {
        return DB::table('students')
            ->where('id', $studentId)
            ->where('section_id', $sectionId)
            ->where('active', true)
            ->exists();
    }

    private function canEditWorkspace(int $subjectId, int $sectionId, int $periodId): bool
    {
        return $this->isPeriodOpen($periodId)
            && !$this->hasGradesUnderReviewOrOfficial($subjectId, $sectionId, $periodId);
    }

    private function isPeriodOpen(int $periodId): bool
    {
        return DB::table('periods')
            ->where('id', $periodId)
            ->where('status', 'open')
            ->exists();
    }

    private function hasGradesUnderReviewOrOfficial(int $subjectId, int $sectionId, int $periodId): bool
    {
        return DB::table('period_grades')
            ->join('students', 'period_grades.student_id', '=', 'students.id')
            ->where('period_grades.subject_id', $subjectId)
            ->where('period_grades.period_id', $periodId)
            ->where('students.section_id', $sectionId)
            ->whereIn('period_grades.status', ['in_review', 'official'])
            ->exists();
    }

    private function coursePayload(int $sectionId, int $subjectId): ?array
    {
        $section = DB::table('sections')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('academic_years', 'sections.academic_year_id', '=', 'academic_years.id')
            ->where('sections.id', $sectionId)
            ->select(
                'sections.id as section_id',
                'sections.academic_year_id',
                'grades.name as grade_name',
                'grades.level as grade_level',
                'sections.name as section_name',
                'academic_years.name as year_label'
            )
            ->first();

        $subject = DB::table('subjects')->where('id', $subjectId)->first(['id', 'name']);

        return $section && $subject ? [
            'section_id' => (int) $section->section_id,
            'subject_id' => (int) $subject->id,
            'academic_year_id' => (int) $section->academic_year_id,
            'grade_name' => $section->grade_name,
            'grade_level' => $section->grade_level,
            'section_name' => $section->section_name,
            'subject_name' => $subject->name,
            'year_label' => $section->year_label,
        ] : null;
    }

    private function studentPayload(int $studentId): ?array
    {
        $student = DB::table('students')->where('id', $studentId)->first();

        return $student ? [
            'student_id' => (int) $student->id,
            'student_name' => "{$student->last_name}, {$student->name}",
            'display_name' => "{$student->name} {$student->last_name}",
            'enrollment_no' => $student->enrollment_no,
            'section_id' => (int) $student->section_id,
        ] : null;
    }

    private function studentsWithObservationSummary(int $sectionId, int $subjectId, int $periodId): array
    {
        $counts = DB::table('observations')
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('period_id', $periodId)
            ->select('student_id', DB::raw('COUNT(*) as observation_count'), DB::raw('MAX(date) as latest_date'))
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        return DB::table('students')
            ->where('section_id', $sectionId)
            ->where('active', true)
            ->orderBy('last_name')
            ->orderBy('name')
            ->get()
            ->map(fn($student) => [
                'student_id' => (int) $student->id,
                'student_name' => "{$student->last_name}, {$student->name}",
                'display_name' => "{$student->name} {$student->last_name}",
                'enrollment_no' => $student->enrollment_no,
                'observation_count' => (int) ($counts[$student->id]->observation_count ?? 0),
                'latest_observation_date' => $counts[$student->id]->latest_date ?? null,
            ])
            ->all();
    }
}
