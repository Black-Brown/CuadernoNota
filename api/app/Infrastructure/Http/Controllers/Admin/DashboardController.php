<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $activeAcademicYear = DB::table('academic_years')->where('active', true)->first();
        $activeAcademicYearId = $activeAcademicYear?->id;

        return response()->json([
            'counts' => [
                'users' => User::count(),
                'active_teachers' => User::where('role', 'teacher')->where('active', true)->count(),
                'active_students' => $activeAcademicYearId
                    ? DB::table('student_enrollments')
                        ->join('students', 'students.id', '=', 'student_enrollments.student_id')
                        ->join('sections', 'sections.id', '=', 'student_enrollments.section_id')
                        ->where('student_enrollments.status', 'active')
                        ->where('students.active', true)
                        ->where('sections.academic_year_id', $activeAcademicYearId)
                        ->distinct('student_enrollments.student_id')
                        ->count('student_enrollments.student_id')
                    : 0,
                'sections' => $activeAcademicYearId
                    ? DB::table('sections')->where('academic_year_id', $activeAcademicYearId)->count()
                    : 0,
                'subjects' => DB::table('subjects')->where('active', true)->count(),
                'active_assignments' => DB::table('teacher_assignments')->where('active', true)->count(),
            ],
            'active_academic_year' => $activeAcademicYear,
            'pending_grades' => DB::table('period_grades')->where('status', 'in_review')->count(),
            'unresolved_alerts' => DB::table('alerts')->where('resolved', false)->count(),
        ]);
    }
}
