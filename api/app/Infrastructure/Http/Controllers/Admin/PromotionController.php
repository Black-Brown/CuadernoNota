<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    public function candidates(Request $request): JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'section_id' => ['nullable', 'exists:sections,id']]);
        $promotionState = $this->promotionState((int) $data['academic_year_id']);
        $rows = StudentEnrollment::with([
            'student.finalGrades' => fn ($q) => $q->where('academic_year_id', $data['academic_year_id']),
            'section.grade', 'section.academicYear',
            'promotionDecision.targetGrade', 'promotionDecision.destinationSection.grade', 'promotionDecision.destinationSection.academicYear',
            'promotionDecision.decidedBy:id,name',
        ])
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $data['academic_year_id']))
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->where(fn ($query) => $query->where('status', 'active')->orWhereHas('promotionDecision'))
            ->get()
            ->map(function (StudentEnrollment $enrollment) use ($promotionState) {
                $eligibility = $this->promotionEligibility($enrollment);

                return [
                    'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id,
                    'enrollment_no' => $enrollment->student->enrollment_no,
                    'student_name' => $enrollment->student->name.' '.$enrollment->student->last_name,
                    'section_id' => $enrollment->section_id,
                    'section_name' => $enrollment->section->name,
                    'shift' => $enrollment->section->shift,
                    'academic_year_id' => $enrollment->section->academic_year_id,
                    'academic_year_name' => $enrollment->section->academicYear->name,
                    'grade_id' => $enrollment->section->grade_id,
                    'grade_name' => $enrollment->section->grade->name,
                    'grade_level' => $enrollment->section->grade->level,
                    'grade_sort_order' => $enrollment->section->grade->sort_order,
                    'subject_count' => $eligibility['completed_subjects'],
                    'expected_subject_count' => $eligibility['expected_subjects'],
                    'missing_subject_count' => $eligibility['missing_subjects'],
                    'failed_subjects' => $eligibility['failed_subjects'],
                    'eligible' => $eligibility['eligible'],
                    'promotion_open' => $promotionState['open'],
                    'promotion_block_reason' => $promotionState['reason'],
                    'decision' => $enrollment->promotionDecision,
                ];
            });
        return response()->json($rows);
    }

    public function decide(Request $request, StudentEnrollment $studentEnrollment): JsonResponse
    {
        $data = $request->validate($this->decisionRules());
        $decision = DB::transaction(fn () => $this->recordDecision($studentEnrollment->id, $data, $request->user()->id));

        return response()->json($decision);
    }

    public function decideMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'enrollment_ids.*' => ['required', 'integer', 'distinct', 'exists:student_enrollments,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            ...$this->decisionRules(),
        ]);

        $decisions = DB::transaction(function () use ($data, $request): Collection {
            $enrollments = StudentEnrollment::query()
                ->whereIn('id', $data['enrollment_ids'])
                ->where('section_id', $data['section_id'])
                ->lockForUpdate()
                ->get();

            if ($enrollments->count() !== count($data['enrollment_ids'])) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'Todos los estudiantes seleccionados deben pertenecer al mismo curso.',
                ]);
            }

            return $enrollments->map(fn (StudentEnrollment $enrollment) =>
                $this->recordDecision($enrollment->id, $data, $request->user()->id)
            );
        });

        return response()->json([
            'message' => $decisions->count().' decisiones de promoción registradas.',
            'processed' => $decisions->count(),
            'decisions' => $decisions,
        ]);
    }

    private function decisionRules(): array
    {
        return [
            'status' => ['required', Rule::in(['promoted', 'not_promoted'])],
            'target_grade_id' => ['required', 'integer', 'exists:grades,id'],
            'justification' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function recordDecision(int $enrollmentId, array $data, int $adminId): object
    {
        $enrollment = StudentEnrollment::with(['student.finalGrades', 'section.grade'])
            ->lockForUpdate()
            ->findOrFail($enrollmentId);
        $existing = DB::table('promotion_decisions')->where('student_enrollment_id', $enrollment->id)->first();

        if ($existing?->placement_status === 'assigned') {
            throw ValidationException::withMessages([
                'student_enrollment' => 'Este estudiante ya fue colocado en una sección y su decisión no puede modificarse desde promoción.',
            ]);
        }

        $currentGrade = $enrollment->section->grade;
        $promotionState = $this->promotionState((int) $enrollment->section->academic_year_id);
        if (! $promotionState['open']) {
            throw ValidationException::withMessages(['academic_year' => $promotionState['reason']]);
        }
        $targetGrade = DB::table('grades')->where('id', $data['target_grade_id'])->first();
        $nextGrade = DB::table('grades')->where('level', $currentGrade->level)
            ->where('sort_order', '>', $currentGrade->sort_order)
            ->where('active', true)
            ->orderBy('sort_order')
            ->first();

        if ($data['status'] === 'promoted' && (! $nextGrade || (int) $targetGrade->id !== (int) $nextGrade->id)) {
            throw ValidationException::withMessages([
                'target_grade_id' => $nextGrade
                    ? 'El grado de destino debe ser el siguiente grado académico: '.$nextGrade->name.'.'
                    : 'No existe un siguiente grado activo configurado para este estudiante.',
            ]);
        }
        if ($data['status'] === 'not_promoted' && (int) $targetGrade->id !== (int) $currentGrade->id) {
            throw ValidationException::withMessages([
                'target_grade_id' => 'Un estudiante no promovido debe permanecer en su grado actual.',
            ]);
        }

        $eligible = $this->promotionEligibility($enrollment)['eligible'];
        $contradictsCriterion = ($data['status'] === 'promoted' && ! $eligible) || ($data['status'] === 'not_promoted' && $eligible);

        if ($contradictsCriterion && blank($data['justification'] ?? null)) {
            throw ValidationException::withMessages([
                'justification' => 'Debes justificar una decisión que difiere del criterio automático.',
            ]);
        }

        DB::table('promotion_decisions')->updateOrInsert(
            ['student_enrollment_id' => $enrollment->id],
            ['status' => $data['status'], 'target_grade_id' => $data['target_grade_id'], 'destination_section_id' => null,
             'placement_status' => 'pending', 'justification' => $data['justification'] ?? null,
             'decided_by' => $adminId, 'decided_at' => now(), 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]
        );
        $enrollment->update(['status' => 'completed', 'ended_at' => now()->toDateString(), 'end_reason' => 'Decisión de promoción registrada']);
        $enrollment->student->update(['section_id' => null, 'academic_year_id' => null, 'active' => true]);

        return DB::table('promotion_decisions')->where('student_enrollment_id', $enrollment->id)->first();
    }

    private function promotionEligibility(StudentEnrollment $enrollment): array
    {
        $requiredSubjectIds = DB::table('grade_subjects')
            ->where('grade_id', $enrollment->section->grade_id)
            ->pluck('subject_id')
            ->map(fn ($subjectId) => (int) $subjectId)
            ->unique()
            ->values();

        $gradesBySubject = $enrollment->student->finalGrades
            ->where('academic_year_id', $enrollment->section->academic_year_id)
            ->whereIn('subject_id', $requiredSubjectIds->all())
            ->keyBy(fn ($grade) => (int) $grade->subject_id);

        $scores = $requiredSubjectIds->mapWithKeys(function (int $subjectId) use ($gradesBySubject): array {
            $grade = $gradesBySubject->get($subjectId);

            if (! $grade) {
                return [];
            }

            return [$subjectId => max(array_filter([
                $grade->cf,
                $grade->final_recovery,
                $grade->special_recovery,
            ], fn ($value) => $value !== null) ?: [0])];
        });

        $missingSubjects = $requiredSubjectIds->diff($scores->keys());

        return [
            'expected_subjects' => $requiredSubjectIds->count(),
            'completed_subjects' => $scores->count(),
            'missing_subjects' => $missingSubjects->count(),
            'failed_subjects' => $scores->filter(fn ($score) => $score < 70)->count(),
            'eligible' => $requiredSubjectIds->isNotEmpty()
                && $missingSubjects->isEmpty()
                && $scores->every(fn ($score) => $score >= 70),
        ];
    }

    private function promotionState(int $academicYearId): array
    {
        $periods = DB::table('periods')->where('academic_year_id', $academicYearId)->get(['name', 'status']);
        if ($periods->isEmpty()) {
            return ['open' => false, 'reason' => 'El año escolar no tiene períodos configurados.'];
        }

        $notClosed = $periods->where('status', '!=', 'closed')->pluck('name')->values();
        if ($notClosed->isNotEmpty()) {
            return [
                'open' => false,
                'reason' => 'Debes cerrar todos los períodos antes de procesar promociones. Pendientes: '.$notClosed->implode(', ').'.',
            ];
        }

        return ['open' => true, 'reason' => null];
    }
}
