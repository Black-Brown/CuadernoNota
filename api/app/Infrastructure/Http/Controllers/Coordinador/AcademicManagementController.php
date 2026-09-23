<?php

namespace App\Infrastructure\Http\Controllers\Coordinador;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AcademicManagementController extends Controller
{
    public function updateStudent(Request $request, int $studentId)
    {
        return DB::transaction(function () use ($request, $studentId) {
            $student = \App\Infrastructure\Models\Student::where('id', $studentId)->lockForUpdate()->firstOrFail();
            abort_unless(DB::table('coordinator_sections')->where('user_id', $request->user()->id)->where('section_id', $student->section_id)->exists(), 403);
            // Only personal fields: changing section/status requires a separate enrollment workflow.
            $data = $request->validate([
                'name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
                'enrollment_no' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique('students', 'enrollment_no')->ignore($student->id)],
            ]);
            $student->update($data);
            return response()->json(['message' => 'Datos del estudiante actualizados.']);
        });
    }

    private function sectionIds(Request $request)
    {
        return DB::table('coordinator_sections')->where('user_id', $request->user()->id)->select('section_id');
    }

    public function catalog(Request $request)
    {
        $sections = DB::table('sections')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('academic_years', 'sections.academic_year_id', '=', 'academic_years.id')
            ->whereIn('sections.id', $this->sectionIds($request))->orderByDesc('academic_years.start_date')->orderBy('grades.sort_order')->orderBy('sections.name')
            ->get(['sections.id', 'sections.name', 'sections.shift', 'sections.grade_id', 'sections.academic_year_id', 'grades.name as grade_name', 'academic_years.name as year_name']);
        $periods = DB::table('periods')->whereIn('academic_year_id', $sections->pluck('academic_year_id'))->orderBy('number')->get();
        $subjects = DB::table('course_offerings')->join('subjects', 'course_offerings.subject_id', '=', 'subjects.id')
            ->whereIn('section_id', $sections->pluck('id'))->get(['course_offerings.id', 'section_id', 'subject_id', 'subjects.name', 'course_offerings.active']);
        return response()->json(compact('sections', 'periods', 'subjects'));
    }

    public function promotions(Request $request, int $sectionId)
    {
        $section = DB::table('sections')->where('id', $sectionId)->whereIn('id', $this->sectionIds($request))->first();
        abort_unless($section, 403);
        $request->merge(['section_id' => $sectionId, 'academic_year_id' => $section->academic_year_id]);
        return app(\App\Infrastructure\Http\Controllers\Admin\PromotionController::class)->candidates($request);
    }

    public function placements(Request $request)
    {
        // Pending students without any assigned origin remain exclusively with the administrator.
        return response()->json(DB::table('students as s')->join('student_enrollments as e', 'e.student_id', '=', 's.id')
            ->join('sections', 'e.section_id', '=', 'sections.id')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->where('s.active', true)->whereIn('e.section_id', $this->sectionIds($request))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('student_enrollments as current')->whereColumn('current.student_id', 's.id')->where('current.status', 'active'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('student_enrollments as newer')->whereColumn('newer.student_id', 's.id')
                ->where(fn ($q) => $q->whereColumn('newer.enrolled_at', '>', 'e.enrolled_at')->orWhere(fn ($q) => $q->whereColumn('newer.enrolled_at', 'e.enrolled_at')->whereColumn('newer.id', '>', 'e.id'))))
            ->orderBy('s.last_name')->get(['s.id', 's.name', 's.last_name', 's.enrollment_no', 'grades.name as grade_name', 'sections.name as section_name']));
    }

    public function students(Request $request)
    {
        return response()->json(DB::table('students')->join('sections', 'students.section_id', '=', 'sections.id')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')->join('academic_years', 'sections.academic_year_id', '=', 'academic_years.id')
            ->whereIn('sections.id', $this->sectionIds($request))->orderBy('students.last_name')->orderBy('students.name')
            ->get(['students.id', 'students.name', 'students.last_name', 'students.enrollment_no', 'students.active', 'students.section_id',
                'grades.name as grade_name', 'sections.name as section_name', 'sections.shift', 'academic_years.name as year_name']));
    }

    public function assignments(Request $request)
    {
        return response()->json(DB::table('teacher_assignments as ta')->join('course_offerings as co', 'ta.course_offering_id', '=', 'co.id')
            ->join('sections', 'co.section_id', '=', 'sections.id')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('subjects', 'co.subject_id', '=', 'subjects.id')->join('users', 'ta.teacher_id', '=', 'users.id')
            ->join('academic_years', 'sections.academic_year_id', '=', 'academic_years.id')
            ->whereIn('sections.id', $this->sectionIds($request))->orderBy('users.name')
            ->get(['ta.id', 'ta.active', 'users.name as teacher_name', 'subjects.name as subject_name', 'grades.name as grade_name', 'sections.name as section_name', 'sections.shift', 'academic_years.name as year_name']));
    }

    public function reports(Request $request, int $sectionId)
    {
        abort_unless(DB::table('sections')->where('id', $sectionId)->whereIn('id', $this->sectionIds($request))->exists(), 403);
        $year = DB::table('sections')->where('id', $sectionId)->value('academic_year_id');
        $data = $request->validate(['period_id' => 'nullable|integer', 'date' => 'nullable|date_format:Y-m-d']);
        $periods = DB::table('periods')->where('academic_year_id', $year)->orderBy('number')->get();
        $period = isset($data['period_id']) ? $periods->firstWhere('id', $data['period_id']) : null;
        abort_if(isset($data['period_id']) && ! $period, 422, 'El período no pertenece al curso.');
        $grades = DB::table('period_grades as pg')->join('students', 'pg.student_id', '=', 'students.id')
            ->join('subjects', 'pg.subject_id', '=', 'subjects.id')->join('periods', 'pg.period_id', '=', 'periods.id')
            ->where('pg.section_id', $sectionId)->where('periods.academic_year_id', $year)->where('pg.status', 'official')
            ->when($period, fn ($q) => $q->where('pg.period_id', $period->id))
            ->orderBy('students.last_name')->get(['pg.id', 'students.name', 'students.last_name', 'students.enrollment_no', 'subjects.name as subject', 'periods.name as period', 'pg.period_score', 'pg.rp_score']);
        $attendanceQuery = DB::table('attendances')->where('attendances.section_id', $sectionId);
        $dates = (clone $attendanceQuery)->select('date')->distinct()->orderByDesc('date')->pluck('date');
        $date = $data['date'] ?? $dates->first();
        $attendance = $date === null ? collect() : $attendanceQuery->join('students', 'attendances.student_id', '=', 'students.id')
            ->leftJoin('subjects', 'attendances.subject_id', '=', 'subjects.id')->whereDate('date', $date)->orderBy('students.last_name')
            ->get(['attendances.id', 'students.name', 'students.last_name', 'students.enrollment_no', 'subjects.name as subject', 'attendances.date', 'attendances.code']);
        return response()->json(compact('grades', 'attendance', 'periods', 'dates', 'date'));
    }
}
