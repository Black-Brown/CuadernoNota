<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Dashboard\GetDashboardBySubject;
use App\Application\Dashboard\GetTeacherDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetTeacherDashboard  $getTeacherDashboard,
        private readonly GetDashboardBySubject $getDashboardBySubject,
    ) {}

    public function index(): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $summary   = $this->getTeacherDashboard->execute($teacherId);

        return response()->json($summary);
    }

    public function bySubject(int $sectionId, int $subjectId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $dashboard = $this->getDashboardBySubject->execute($teacherId, $sectionId, $subjectId);

        return response()->json($dashboard);
    }

    public function currentPeriod(): JsonResponse
    {
        $today = now()->toDateString();

        // First try: period that contains today's date
        $period = DB::table('periods')
            ->join('academic_years', 'periods.academic_year_id', '=', 'academic_years.id')
            ->where('academic_years.active', true)
            ->where('periods.start_date', '<=', $today)
            ->where('periods.end_date', '>=', $today)
            ->select('periods.*')
            ->first();

        // Fallback: most recent open period in the active year
        if (!$period) {
            $period = DB::table('periods')
                ->join('academic_years', 'periods.academic_year_id', '=', 'academic_years.id')
                ->where('academic_years.active', true)
                ->where('periods.status', 'open')
                ->select('periods.*')
                ->orderBy('periods.number', 'desc')
                ->first();
        }

        if (!$period) {
            return response()->json(['period' => null]);
        }

        return response()->json([
            'period' => [
                'id'       => $period->id,
                'number'   => $period->number,
                'name'     => $period->name,
                'months'   => $period->months,
                'status'   => $period->status,
                'end_date' => $period->end_date,
            ],
        ]);
    }

    public function periods(): JsonResponse
    {
        $periods = DB::table('periods')
            ->join('academic_years', 'periods.academic_year_id', '=', 'academic_years.id')
            ->where('academic_years.active', true)
            ->select('periods.id', 'periods.name', 'periods.number', 'periods.status')
            ->orderBy('periods.number')
            ->get();

        return response()->json(['periods' => $periods]);
    }
}
