<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GradeReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(DB::table('period_grades')
            ->join('students', 'period_grades.student_id', '=', 'students.id')
            ->join('sections', 'students.section_id', '=', 'sections.id')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('subjects', 'period_grades.subject_id', '=', 'subjects.id')
            ->join('periods', 'period_grades.period_id', '=', 'periods.id')
            ->where('period_grades.status', $request->input('status', 'in_review'))
            ->select('sections.id as section_id', 'periods.id as period_id', 'subjects.id as subject_id', 'grades.name as grade_name', 'sections.name as section_name', 'subjects.name as subject_name', 'periods.name as period_name', DB::raw('COUNT(*) as student_count'), DB::raw('AVG(period_grades.period_score) as average'))
            ->groupBy('sections.id', 'periods.id', 'subjects.id', 'grades.name', 'sections.name', 'subjects.name', 'periods.name')
            ->orderBy('periods.id')->get());
    }

    public function show(int $sectionId, int $subjectId, int $periodId): JsonResponse
    {
        return response()->json(DB::table('period_grades')->join('students', 'period_grades.student_id', '=', 'students.id')
            ->where('students.section_id', $sectionId)->where('period_grades.subject_id', $subjectId)->where('period_grades.period_id', $periodId)
            ->select('period_grades.*', 'students.name', 'students.last_name', 'students.enrollment_no')->orderBy('students.last_name')->get());
    }

    public function decide(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:sections,id'], 'subject_id' => ['required', 'exists:subjects,id'], 'period_id' => ['required', 'exists:periods,id'],
            'action' => ['required', Rule::in(['approved', 'rejected', 'reopened'])], 'comment' => ['nullable', 'string', 'max:2000', 'required_if:action,rejected,reopened'],
        ]);
        $updated = DB::transaction(function () use ($data, $request) {
            $query = DB::table('period_grades')->where('subject_id', $data['subject_id'])->where('period_id', $data['period_id'])
                ->whereIn('student_id', DB::table('students')->where('section_id', $data['section_id'])->select('id'));
            $expected = $data['action'] === 'reopened' ? 'official' : 'in_review';
            $values = $data['action'] === 'approved'
                ? ['status' => 'official', 'approved_by' => $request->user()->id, 'approved_at' => now()]
                : ['status' => 'draft', 'approved_by' => null, 'approved_at' => null];
            $count = (clone $query)->where('status', $expected)->update($values + ['updated_at' => now()]);
            if (!$count) throw ValidationException::withMessages(['grades' => 'No hay calificaciones en el estado requerido para esta acción.']);
            DB::table('grade_review_actions')->insert([
                'period_id' => $data['period_id'], 'subject_id' => $data['subject_id'], 'section_id' => $data['section_id'],
                'action' => $data['action'], 'comment' => $data['comment'] ?? null, 'performed_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $count;
        });
        return response()->json(['message' => 'Decisión aplicada correctamente.', 'updated_grades' => $updated]);
    }
}
