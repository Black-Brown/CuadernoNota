<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Grade\GetActivitiesBySubject;
use App\Application\Grade\GetActivityGrades;
use App\Application\Grade\GetPeriodGrades;
use App\Application\Grade\GetTeacherCourses;
use App\Application\Grade\RegisterActivityScore;
use App\Application\Grade\RegisterRecovery;
use App\Application\Grade\SubmitGrades;
use App\Domain\Grade\Repositories\FinalGradeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    public function __construct(
        private readonly RegisterActivityScore  $registerActivityScore,
        private readonly GetTeacherCourses      $getTeacherCourses,
        private readonly GetActivitiesBySubject $getActivitiesBySubject,
        private readonly GetActivityGrades      $getActivityGrades,
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

    public function activitiesBySubject(int $subjectId): JsonResponse
    {
        $activities = $this->getActivitiesBySubject->execute($subjectId);

        return response()->json(['activities' => $activities]);
    }

    public function activityGrades(int $activityId, int $periodId): JsonResponse
    {
        $sectionId = request()->integer('section_id') ?: null;
        $students  = $this->getActivityGrades->execute($activityId, $periodId, $sectionId);

        return response()->json(['students' => $students]);
    }

    public function periodGrades(int $subjectId, int $periodId): JsonResponse
    {
        $grades = $this->getPeriodGrades->execute($subjectId, $periodId);

        return response()->json(['grades' => $grades]);
    }

    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
            'period_id'  => 'required|integer|exists:periods,id',
        ]);

        $this->submitGrades->execute($validated['subject_id'], $validated['period_id']);

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
}
