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
        return response()->json([
            'counts' => [
                'users' => User::count(),
                'active_teachers' => User::where('role', 'teacher')->where('active', true)->count(),
                'active_students' => DB::table('students')->where('active', true)->count(),
                'sections' => DB::table('sections')->count(),
                'subjects' => DB::table('subjects')->where('active', true)->count(),
                'active_assignments' => DB::table('teacher_assignments')->where('active', true)->count(),
            ],
            'active_academic_year' => DB::table('academic_years')->where('active', true)->first(),
            'pending_grades' => DB::table('period_grades')->where('status', 'in_review')->count(),
            'unresolved_alerts' => DB::table('alerts')->where('resolved', false)->count(),
        ]);
    }
}
