<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    public function candidates(Request $request): JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'section_id' => ['nullable', 'exists:sections,id']]);
        $rows = StudentEnrollment::with(['student.finalGrades' => fn ($q) => $q->where('academic_year_id', $data['academic_year_id']), 'section.grade'])
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $data['academic_year_id']))
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))->where('status', 'active')->get()
            ->map(function (StudentEnrollment $enrollment) {
                $scores = $enrollment->student->finalGrades->map(fn ($grade) => max(array_filter([$grade->cf, $grade->final_recovery, $grade->special_recovery], fn ($v) => $v !== null) ?: [0]));
                $expectedSubjects = DB::table('grade_subjects')->where('grade_id', $enrollment->section->grade_id)->count();
                return [
                    'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id,
                    'student_name' => $enrollment->student->name.' '.$enrollment->student->last_name,
                    'grade_name' => $enrollment->section->grade->name, 'subject_count' => $scores->count(),
                    'expected_subject_count' => $expectedSubjects,
                    'failed_subjects' => $scores->filter(fn ($score) => $score < 70)->count(),
                    'eligible' => $expectedSubjects > 0 && $scores->count() >= $expectedSubjects && $scores->every(fn ($score) => $score >= 70),
                ];
            });
        return response()->json($rows);
    }

    public function decide(Request $request, StudentEnrollment $studentEnrollment): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['promoted', 'not_promoted'])],
            'destination_section_id' => ['nullable', 'required_if:status,promoted', 'exists:sections,id'],
            'justification' => ['nullable', 'string', 'max:2000'],
        ]);
        $decision = DB::transaction(function () use ($data, $request, $studentEnrollment) {
            if ($data['status'] === 'promoted') {
                $destination = DB::table('sections')->find($data['destination_section_id']);
                if ((int) $destination->academic_year_id === (int) $studentEnrollment->section->academic_year_id) {
                    throw ValidationException::withMessages(['destination_section_id' => 'La sección de destino debe pertenecer a otro año escolar.']);
                }
            }
            DB::table('promotion_decisions')->updateOrInsert(
                ['student_enrollment_id' => $studentEnrollment->id],
                ['status' => $data['status'], 'destination_section_id' => $data['destination_section_id'] ?? null, 'justification' => $data['justification'] ?? null,
                 'decided_by' => $request->user()->id, 'decided_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );
            $studentEnrollment->update(['status' => 'completed', 'ended_at' => now()->toDateString()]);
            if ($data['status'] === 'promoted') {
                $destination = DB::table('sections')->find($data['destination_section_id']);
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $studentEnrollment->student_id, 'section_id' => $destination->id],
                    ['status' => 'active', 'enrolled_at' => DB::table('academic_years')->where('id', $destination->academic_year_id)->value('start_date'), 'created_by' => $request->user()->id]
                );
                $studentEnrollment->student->update(['section_id' => $destination->id, 'academic_year_id' => $destination->academic_year_id, 'active' => true]);
            }
            return DB::table('promotion_decisions')->where('student_enrollment_id', $studentEnrollment->id)->first();
        });
        return response()->json($decision);
    }
}
