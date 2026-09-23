<?php

namespace App\Infrastructure\Http\Controllers\Coordinador;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class StudentWorkspaceController extends Controller
{
    private function context(Request $request, int $sectionId): array
    {
        abort_unless(DB::table('coordinator_sections')->where('user_id', $request->user()->id)
            ->where('section_id', $sectionId)->exists(), 403, 'Esta sección no está bajo tu supervisión.');
        $section = DB::table('sections')->join('grades', 'grades.id', '=', 'sections.grade_id')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->where('sections.id', $sectionId)->first(['sections.*', 'grades.name as grade_name', 'academic_years.name as year_name']);
        abort_unless($section, 404);
        $data = $request->validate(['period_id' => ['nullable', 'integer']]);
        $periods = DB::table('periods')->where('academic_year_id', $section->academic_year_id)->orderBy('number')->get();
        $period = isset($data['period_id'])
            ? $periods->firstWhere('id', (int) $data['period_id'])
            : $periods->first(fn ($p) => $p->start_date <= now()->toDateString() && $p->end_date >= now()->toDateString()) ?? $periods->first();
        abort_if(isset($data['period_id']) && ! $period, 422, 'El período no pertenece al año de esta sección.');
        return [$section, $periods, $period];
    }

    public function show(Request $request, int $sectionId)
    {
        [$section, $periods, $period] = $this->context($request, $sectionId);
        $students = DB::table('students')->where('section_id', $sectionId)->where('active', true)
            ->orderBy('last_name')->orderBy('name')->get(['id', 'name', 'last_name', 'enrollment_no']);
        $grades = DB::table('period_grades')->where('section_id', $sectionId)->where('period_id', $period?->id ?? 0)
            ->whereIn('student_id', $students->pluck('id'))->get()->groupBy('student_id');
        $students->each(function ($student) use ($grades) {
            $rows = $grades->get($student->id, collect());
            $scores = $rows->map(fn ($row) => $row->rp_score ?? $row->period_score)->filter(fn ($score) => $score !== null);
            $student->average = $scores->isEmpty() ? null : round($scores->avg(), 2);
            $student->at_risk = $scores->contains(fn ($score) => (float) $score < 70);
            $student->evaluated_subjects = $scores->count();
        });
        return response()->json(compact('section', 'periods', 'period', 'students'));
    }

    public function student(Request $request, int $sectionId, int $studentId)
    {
        [$section, $periods, $period] = $this->context($request, $sectionId);
        $student = DB::table('students')->where('id', $studentId)->where('section_id', $sectionId)->where('active', true)
            ->first(['id', 'name', 'last_name', 'enrollment_no']);
        abort_unless($student, 404, 'El estudiante no está activo en esta sección.');
        $grades = DB::table('period_grades')->join('subjects', 'subjects.id', '=', 'period_grades.subject_id')
            ->where('period_grades.section_id', $sectionId)->where('student_id', $studentId)->where('period_id', $period?->id ?? 0)
            ->orderBy('subjects.name')->get(['period_grades.*', 'subjects.name as subject_name']);
        $observations = DB::table('observations')->join('users', 'users.id', '=', 'observations.user_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'observations.subject_id')
            ->where('observations.section_id', $sectionId)->where('student_id', $studentId)->where('period_id', $period?->id ?? 0)
            ->orderByDesc('date')->get(['observations.id', 'date', 'type', 'description', 'users.name as author_name', 'subjects.name as subject_name']);
        $attendance = DB::table('attendances')->leftJoin('subjects', 'subjects.id', '=', 'attendances.subject_id')
            ->where('attendances.section_id', $sectionId)->where('student_id', $studentId)
            ->whereBetween('date', [$period?->start_date ?? '0001-01-01', $period?->end_date ?? '0001-01-01'])
            ->orderByDesc('date')->get(['attendances.id', 'attendances.date', 'attendances.code', 'subjects.name as subject_name']);
        $summary = ['attendance_records' => $attendance->count(), 'attendance_percentage' => $attendance->isEmpty()
            ? null : round(100 * $attendance->whereIn('code', ['P', 'T'])->count() / $attendance->count(), 1)];
        return response()->json(compact('student', 'period', 'grades', 'observations', 'attendance', 'summary'));
    }
}
