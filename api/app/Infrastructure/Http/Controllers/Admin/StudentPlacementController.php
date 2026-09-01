<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\Student;
use App\Infrastructure\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentPlacementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = Student::query()->where('active', true)
            ->whereDoesntHave('enrollments', fn ($query) => $query->where('status', 'active'))
            ->with(['enrollments' => fn ($query) => $query->latest('enrolled_at'), 'enrollments.section.grade',
                'enrollments.section.academicYear', 'enrollments.promotionDecision.targetGrade'])
            ->when($request->search, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('enrollment_no', 'like', "%{$search}%")))
            ->orderBy('last_name')->orderBy('name')->get()
            ->map(function (Student $student): array {
                $decision = $student->enrollments->pluck('promotionDecision')->filter()
                    ->first(fn ($item) => $item->placement_status === 'pending');
                $previous = $student->enrollments->first();

                return [
                    'id' => $student->id, 'name' => $student->name, 'last_name' => $student->last_name,
                    'enrollment_no' => $student->enrollment_no,
                    'origin' => $previous?->section ? $previous->section->grade->name.' '.$previous->section->name.' · '.$previous->section->academicYear->name : null,
                    'placement_reason' => $decision ? ($decision->status === 'promoted' ? 'Promovido' : 'Repite grado') : 'Nuevo ingreso',
                    'target_grade_id' => $decision?->target_grade_id,
                    'target_grade_name' => $decision?->targetGrade?->name,
                ];
            });

        return response()->json($students);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'enrolled_at' => ['required', 'date'],
        ]);
        $count = DB::transaction(function () use ($data, $request): int {
            $section = DB::table('sections')->lockForUpdate()->find($data['section_id']);
            $academicYear = DB::table('academic_years')->find($section->academic_year_id);

            if ($data['enrolled_at'] < $academicYear->start_date || $data['enrolled_at'] > $academicYear->end_date) {
                throw ValidationException::withMessages([
                    'enrolled_at' => "La fecha de inscripción debe estar entre {$academicYear->start_date} y {$academicYear->end_date}.",
                ]);
            }
            $students = Student::whereIn('id', $data['student_ids'])->lockForUpdate()->get();
            if ($students->count() !== count($data['student_ids']) || $students->contains(fn ($student) => ! $student->active)) {
                throw ValidationException::withMessages(['student_ids' => 'Todos los estudiantes deben estar activos.']);
            }
            if (StudentEnrollment::whereIn('student_id', $data['student_ids'])->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['student_ids' => 'Uno de los estudiantes ya tiene una matrícula activa.']);
            }
            $pendingDecisions = DB::table('promotion_decisions')->join('student_enrollments', 'student_enrollments.id', '=', 'promotion_decisions.student_enrollment_id')
                ->join('sections as origin_sections', 'origin_sections.id', '=', 'student_enrollments.section_id')
                ->whereIn('student_enrollments.student_id', $data['student_ids'])->where('promotion_decisions.placement_status', 'pending')
                ->get(['promotion_decisions.id', 'promotion_decisions.target_grade_id', 'origin_sections.academic_year_id as origin_academic_year_id']);
            if ($pendingDecisions->contains(fn ($decision) => $decision->target_grade_id && (int) $decision->target_grade_id !== (int) $section->grade_id)) {
                throw ValidationException::withMessages(['section_id' => 'La sección seleccionada no corresponde al grado de destino de todos los estudiantes.']);
            }
            foreach ($pendingDecisions as $decision) {
                $originYear = DB::table('academic_years')->find($decision->origin_academic_year_id);
                $nextYearId = DB::table('academic_years')->where('start_date', '>', $originYear->start_date)->orderBy('start_date')->value('id');

                if (! $nextYearId || (int) $section->academic_year_id !== (int) $nextYearId) {
                    throw ValidationException::withMessages([
                        'section_id' => 'La sección debe pertenecer al año escolar inmediatamente siguiente al de procedencia.',
                    ]);
                }
            }
            foreach ($students as $student) {
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $student->id, 'section_id' => $section->id],
                    ['status' => 'active', 'enrolled_at' => $data['enrolled_at'], 'ended_at' => null, 'end_reason' => null, 'created_by' => $request->user()->id]
                );
                $student->update(['section_id' => $section->id, 'academic_year_id' => $section->academic_year_id]);
            }
            if ($pendingDecisions->isNotEmpty()) {
                DB::table('promotion_decisions')->whereIn('id', $pendingDecisions->pluck('id'))->update([
                    'destination_section_id' => $section->id, 'placement_status' => 'assigned', 'updated_at' => now(),
                ]);
            }

            return $students->count();
        });

        return response()->json(['message' => "{$count} estudiantes asignados correctamente.", 'assigned' => $count], 201);
    }
}
