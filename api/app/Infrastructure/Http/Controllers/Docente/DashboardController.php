<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Dashboard\GetDashboardBySubject;
use App\Application\Dashboard\GetTeacherDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Infrastructure\Models\Period;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetTeacherDashboard  $getTeacherDashboard,
        private readonly GetDashboardBySubject $getDashboardBySubject,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $summary   = $this->getTeacherDashboard->execute($teacherId, $request->integer('period_id') ?: null);

        return response()->json($summary);
    }

    public function bySubject(Request $request, int $sectionId, int $subjectId): JsonResponse
    {
        $teacherId = Auth::id() ?? 1;
        $dashboard = $this->getDashboardBySubject->execute(
            $teacherId,
            $sectionId,
            $subjectId,
            $request->integer('period_id') ?: null,
        );

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

        if (!$period) {
            return response()->json(['period' => null]);
        }

        $periodModel = Period::find($period->id);

        return response()->json([
            'period' => [
                'id'       => $period->id,
                'number'   => $period->number,
                'name'     => $period->name,
                'months'   => $period->months,
                'status'   => $periodModel->effectiveStatus(),
                'configured_status' => $period->status,
                'end_date' => $period->end_date,
                'start_date' => $period->start_date,
                'academic_year_id' => $period->academic_year_id,
            ],
        ]);
    }

    public function periods(): JsonResponse
    {
        $periods = Period::query()
            ->whereHas('academicYear', fn ($query) => $query->where('active', true))
            ->orderBy('number')
            ->get()
            ->map(fn (Period $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'number' => $period->number,
                'months' => $period->months,
                'status' => $period->effectiveStatus(),
                'configured_status' => $period->status,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'academic_year_id' => $period->academic_year_id,
            ]);

        return response()->json(['periods' => $periods]);
    }
}
