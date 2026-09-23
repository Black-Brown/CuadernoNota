<?php

namespace App\Infrastructure\Http\Controllers\Coordinador;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $year = DB::table('academic_years')->where('active', true)->first();
        $sections = DB::table('sections')->whereIn('sections.id', DB::table('coordinator_sections')
            ->where('user_id', auth()->id())->select('section_id'))
            ->where('sections.academic_year_id', $year?->id ?? 0)
            ->join('grades', 'grades.id', '=', 'sections.grade_id')
            ->orderBy('grades.sort_order')->orderBy('sections.name')
            ->get(['sections.id', 'sections.name', 'sections.shift', 'grades.name as grade_name']);
        $ids = $sections->pluck('id');
        $students = DB::table('students')->where('active', true)->whereIn('section_id', $ids);
        $counts = (clone $students)->select('section_id', DB::raw('COUNT(*) as total'))->groupBy('section_id')->pluck('total', 'section_id');
        $sections->each(fn ($section) => $section->students_count = (int) ($counts[$section->id] ?? 0));
        $pending = DB::table('period_grades')->whereIn('section_id', $ids)->where('status', 'in_review')
            ->select('section_id', 'subject_id', 'period_id')->distinct()->get()->count();
        return response()->json([
            'active_academic_year' => $year,
            'sections' => $sections,
            'counts' => ['sections' => $sections->count(), 'students' => $students->count(), 'pending_reviews' => $pending],
        ]);
    }
}
