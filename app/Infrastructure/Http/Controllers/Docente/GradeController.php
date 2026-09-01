<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Grade\GetActivitiesBySubject;
use App\Application\Grade\GetActivityGrades;
use App\Application\Grade\GetGradebookSummary;
use App\Application\Grade\GetPeriodGrades;
use App\Application\Grade\GetTeacherCourses;
use App\Application\Grade\RegisterActivityScore;
use App\Application\Grade\RegisterRecovery;
use App\Application\Grade\SubmitGrades;
use App\Domain\Grade\Repositories\FinalGradeRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Infrastructure\Models\Period;

class GradeController extends Controller
{
    public function __construct(
        private readonly RegisterActivityScore  $registerActivityScore,
        private readonly GetTeacherCourses      $getTeacherCourses,
        private readonly GetActivitiesBySubject $getActivitiesBySubject,
        private readonly GetActivityGrades      $getActivityGrades,
        private readonly GetGradebookSummary    $getGradebookSummary,
        private readonly GetPeriodGrades        $getPeriodGrades,
        private readonly SubmitGrades           $submitGrades,
        private readonly RegisterRecovery       $registerRecovery,
        private readonly FinalGradeRepositoryInterface $finalGradeRepo,
    ) {}

    public function courses(): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $courses   = $this->getTeacherCourses->execute($teacherId);

        return response()->json(['courses' => $courses]);
    }

    public function gradebookSummary(Request $request, int $sectionId, int $subjectId): JsonResponse
    {
        try {
            $summary = $this->getGradebookSummary->execute(
                teacherId: Auth::id() ?? 1,
                sectionId: $sectionId,
                subjectId: $subjectId,
                academicYearId: $request->integer('academic_year_id') ?: null,
            );
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($summary);
    }

    public function activitiesBySubject(Request $request, int $subjectId): JsonResponse
    {
        $sectionId = $request->integer('section_id') ?: null;

        if ($sectionId === null) {
            return response()->json(['message' => 'Debes indicar la sección del curso.'], 422);
        }

        $periodId = $request->integer('period_id') ?: null;
        $isAssignedCourse = $periodId !== null
            ? $this->isAssignedCourseForPeriod($sectionId, $subjectId, $periodId)
            : $this->isAssignedCourse($sectionId, $subjectId);

        if (!$isAssignedCourse) {
            return response()->json([
                'message' => 'No tienes permiso para consultar actividades de este curso.',
            ], 403);
        }

        $activities = $this->getActivitiesBySubject->execute(
            $subjectId,
            $sectionId,
            $periodId
        );

        return response()->json(['activities' => $activities]);
    }

    public function activityGrades(int $activityId, int $periodId): JsonResponse
    {
        $activity = DB::table('activities')->where('id', $activityId)->first();

        if (!$activity || (int) $activity->period_id !== $periodId) {
            return response()->json(['message' => 'La actividad no pertenece al período seleccionado.'], 422);
        }

        $sectionId = request()->integer('section_id') ?: (int) $activity->section_id;

        if (
            $sectionId !== (int) $activity->section_id
            || !$this->isAssignedCourseForPeriod($sectionId, (int) $activity->subject_id, $periodId)
        ) {
            return response()->json(['message' => 'No tienes permiso para consultar notas de este curso.'], 403);
        }

        $students  = $this->getActivityGrades->execute($activityId, $periodId, $sectionId);

        return response()->json(['students' => $students]);
    }

    public function periodGrades(int $subjectId, int $periodId): JsonResponse
    {
        $sectionId = request()->integer('section_id') ?: null;

        if ($sectionId === null) {
            return response()->json(['message' => 'Debes indicar la sección del curso.'], 422);
        }

        if (!$this->isAssignedCourseForPeriod($sectionId, $subjectId, $periodId)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar notas de este curso.',
            ], 403);
        }

        $grades = $this->getPeriodGrades->execute($subjectId, $periodId, $sectionId);

        return response()->json(['grades' => $grades]);
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
            'period_id'  => 'required|integer|exists:periods,id',
            'section_id' => 'required|integer|exists:sections,id',
        ]);

        if (!$this->isAssignedCourseForPeriod(
            (int) $validated['section_id'],
            (int) $validated['subject_id'],
            (int) $validated['period_id']
        )) {
            return response()->json(['message' => 'No tienes permiso para enviar notas de este curso.'], 403);
        }

        if (!$this->isPeriodOpen((int) $validated['period_id'])) {
            return response()->json([
                'message' => 'Este período está cerrado. Solicita permiso al coordinador para modificarlo.',
            ], 423);
        }

        if ($this->hasGradesUnderReviewOrOfficial(
            (int) $validated['subject_id'],
            (int) $validated['section_id'],
            (int) $validated['period_id']
        )) {
            return response()->json([
                'message' => 'Estas calificaciones ya están en revisión u oficiales.',
            ], 423);
        }

        $this->submitGrades->execute($validated['subject_id'], $validated['period_id'], $validated['section_id']);

        return response()->json(['message' => 'Notas enviadas a revisión correctamente.']);
    }

    public function recovery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'             => 'required|in:rp,final,special',
            'student_id'       => 'required|integer|exists:students,id',
            'subject_id'       => 'required|integer|exists:subjects,id',
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'score'            => 'required|numeric|min:0|max:100',
            'period_id'        => 'required_if:type,rp|nullable|integer|exists:periods,id',
        ]);

        $studentSectionId = (int) DB::table('students')
            ->where('id', $validated['student_id'])
            ->value('section_id');

        if (!$this->isAssignedCourseForYear(
            $studentSectionId,
            (int) $validated['subject_id'],
            (int) $validated['academic_year_id']
        )) {
            return response()->json(['message' => 'No tienes permiso para registrar recuperaciones en este curso.'], 403);
        }

        if (
            $validated['type'] === 'rp'
            && !$this->periodBelongsToYear((int) $validated['period_id'], (int) $validated['academic_year_id'])
        ) {
            return response()->json(['message' => 'El período no pertenece al año académico seleccionado.'], 422);
        }

        $this->validateRecoveryEligibility($validated, $studentSectionId);

        return DB::transaction(function () use ($validated): JsonResponse {
            if ($validated['type'] === 'rp') {
                $finalGrade = $this->registerRecovery->execute(
                    studentId:      $validated['student_id'],
                    subjectId:      $validated['subject_id'],
                    periodId:       $validated['period_id'],
                    academicYearId: $validated['academic_year_id'],
                    rpScore:        (float) $validated['score'],
                );

                return response()->json([
                    'message'     => 'Recuperación pedagógica registrada.',
                    'final_grade' => [
                        'student_id'      => $finalGrade->studentId,
                        'subject_id'      => $finalGrade->subjectId,
                        'cf'              => $finalGrade->cf,
                        'effective_cf'    => $finalGrade->effectiveCF(),
                        'final_recovery'  => $finalGrade->finalRecovery,
                        'special_recovery' => $finalGrade->specialRecovery,
                    ],
                ]);
            }

            if ($validated['type'] === 'final') {
                $this->finalGradeRepo->registerFinalRecovery(
                    $validated['student_id'],
                    $validated['subject_id'],
                    $validated['academic_year_id'],
                    (float) $validated['score'],
                );
                return response()->json(['message' => 'Recuperación Final registrada.']);
            }

            $this->finalGradeRepo->registerSpecialRecovery(
                $validated['student_id'],
                $validated['subject_id'],
                $validated['academic_year_id'],
                (float) $validated['score'],
            );

            return response()->json(['message' => 'Recuperación Especial registrada.']);
        });
    }

    /**
     * Registra la nota de una actividad para un estudiante.
     *
     * Después de guardar la nota, recalcula automáticamente:
     *  - La nota de cada competencia (C1, C2, C3) para ese período.
     *  - La nota del período = (C1+C2+C3)/3.
     *
     * Devuelve el PeriodGrade actualizado, o null si alguna competencia
     * aún no tiene actividades con nota registrada.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_id'   => 'required|integer|exists:activities,id',
            'student_id'    => 'required|integer|exists:students,id',
            'competency_id' => 'required|integer|exists:competencies,id',
            'period_id'     => 'required|integer|exists:periods,id',
            'subject_id'    => 'required|integer|exists:subjects,id',
            'score'         => 'nullable|numeric|min:0|max:100',
        ]);

        $activity = DB::table('activities')->where('id', $validated['activity_id'])->first();
        $studentSectionId = DB::table('students')->where('id', $validated['student_id'])->value('section_id');

        if (!$this->isPeriodOpen((int) $validated['period_id'])) {
            return response()->json([
                'message' => 'Este período está cerrado. Solicita permiso al coordinador para modificarlo.',
            ], 423);
        }

        if (
            !$activity ||
            (int) $activity->subject_id !== (int) $validated['subject_id'] ||
            (int) $activity->period_id !== (int) $validated['period_id'] ||
            (int) $activity->section_id !== (int) $studentSectionId
        ) {
            return response()->json([
                'message' => 'La actividad no pertenece al curso o período seleccionado.',
            ], 422);
        }

        if (!$this->isAssignedCourseForPeriod(
            (int) $activity->section_id,
            (int) $validated['subject_id'],
            (int) $validated['period_id']
        )) {
            return response()->json(['message' => 'No tienes permiso para modificar notas de este curso.'], 403);
        }

        $validated['section_id'] = (int) $activity->section_id;

        if ($this->hasGradesUnderReviewOrOfficial(
            (int) $validated['subject_id'],
            (int) $activity->section_id,
            (int) $validated['period_id']
        )) {
            return response()->json([
                'message' => 'Estas calificaciones ya están en revisión u oficiales. No puedes modificar este workspace.',
            ], 423);
        }

        $periodGrade = $this->registerActivityScore->execute($validated);

        if ($periodGrade === null) {
            return response()->json([
                'message' => 'Nota guardada. El período aún no puede calcularse porque falta nota en alguna competencia.',
                'period_grade' => null,
            ], 201);
        }

        return response()->json([
            'message'      => 'Nota guardada y período recalculado.',
            'period_grade' => [
                'student_id'      => $periodGrade->studentId,
                'subject_id'      => $periodGrade->subjectId,
                'period_id'       => $periodGrade->periodId,
                'c1_score'        => $periodGrade->c1Score,
                'c2_score'        => $periodGrade->c2Score,
                'c3_score'        => $periodGrade->c3Score,
                'period_score'    => $periodGrade->periodScore,
                'rp_score'        => $periodGrade->rpScore,
                'effective_score' => $periodGrade->effectiveScore(),
                'status'          => $periodGrade->status,
            ],
        ], 201);
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

    private function isAssignedCourseForPeriod(int $sectionId, int $subjectId, int $periodId): bool
    {
        $academicYearId = DB::table('periods')->where('id', $periodId)->value('academic_year_id');

        return $academicYearId !== null
            && $this->isAssignedCourseForYear($sectionId, $subjectId, (int) $academicYearId);
    }

    private function isAssignedCourseForYear(int $sectionId, int $subjectId, int $academicYearId): bool
    {
        return DB::table('teacher_sections')
            ->where('user_id', Auth::id() ?? 1)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    private function isAssignedCourse(int $sectionId, int $subjectId): bool
    {
        return DB::table('teacher_sections')
            ->where('user_id', Auth::id() ?? 1)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function periodBelongsToYear(int $periodId, int $academicYearId): bool
    {
        return DB::table('periods')
            ->where('id', $periodId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    private function validateRecoveryEligibility(array $data, int $sectionId): void
    {
        if ($data['type'] === 'rp') {
            $periodGrade = DB::table('period_grades')
                ->where('student_id', $data['student_id'])
                ->where('section_id', $sectionId)
                ->where('subject_id', $data['subject_id'])
                ->where('period_id', $data['period_id'])
                ->first();

            if (! $periodGrade) {
                throw ValidationException::withMessages(['period_id' => 'No existe una calificación del período para recuperar.']);
            }
            if (! $this->isPeriodOpen((int) $data['period_id']) || $periodGrade->status !== 'draft') {
                throw ValidationException::withMessages(['period_id' => 'La recuperación pedagógica solo puede registrarse mientras el período y la calificación estén en edición.']);
            }
            if ($periodGrade->period_score === null || (float) $periodGrade->period_score >= 70) {
                throw ValidationException::withMessages(['score' => 'La recuperación pedagógica solo aplica a una calificación de período reprobada.']);
            }

            return;
        }

        $openPeriods = DB::table('periods')
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('status', '!=', 'closed')
            ->count();
        if ($openPeriods > 0) {
            throw ValidationException::withMessages(['academic_year_id' => 'Debes cerrar todos los períodos antes de registrar una recuperación final o especial.']);
        }

        $finalGrade = DB::table('final_grades')
            ->where('student_id', $data['student_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->first();

        if (! $finalGrade) {
            throw ValidationException::withMessages(['student_id' => 'No existe una calificación final para este estudiante y materia.']);
        }

        if ($data['type'] === 'final' && ($finalGrade->cf === null || (float) $finalGrade->cf >= 70)) {
            throw ValidationException::withMessages(['score' => 'La recuperación final solo aplica cuando la calificación final es menor de 70.']);
        }

        if ($data['type'] === 'special' && ($finalGrade->final_recovery === null || (float) $finalGrade->final_recovery >= 70)) {
            throw ValidationException::withMessages(['score' => 'La recuperación especial requiere una recuperación final reprobada.']);
        }
    }
}
