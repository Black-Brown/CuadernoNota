<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Infrastructure\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function academic(Request $request): JsonResponse
    {
        $yearId = $request->integer('academic_year_id') ?: DB::table('academic_years')->where('active', true)->value('id');
        $rows = DB::table('period_grades')->join('students', 'period_grades.student_id', '=', 'students.id')
            ->join('sections', 'students.section_id', '=', 'sections.id')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('subjects', 'period_grades.subject_id', '=', 'subjects.id')->join('periods', 'period_grades.period_id', '=', 'periods.id')
            ->where('periods.academic_year_id', $yearId)->where('period_grades.status', 'official')
            ->select('grades.name as grade', 'sections.name as section', 'subjects.name as subject', 'periods.name as period',
                DB::raw('COUNT(*) as students'), DB::raw('ROUND(AVG(COALESCE(period_grades.rp_score, period_grades.period_score)), 2) as average'),
                DB::raw('SUM(CASE WHEN COALESCE(period_grades.rp_score, period_grades.period_score) < 70 THEN 1 ELSE 0 END) as at_risk'))
            ->groupBy('grades.name', 'sections.name', 'subjects.name', 'periods.name')->orderBy('grades.name')->get();
        return response()->json(['academic_year_id' => $yearId, 'rows' => $rows]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $yearId = $request->integer('academic_year_id') ?: DB::table('academic_years')->where('active', true)->value('id');
        return response()->json(DB::table('attendances')->join('sections', 'attendances.section_id', '=', 'sections.id')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->where('sections.academic_year_id', $yearId)->select('grades.name as grade', 'sections.name as section',
                DB::raw('COUNT(*) as records'), DB::raw("SUM(CASE WHEN code = 'P' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN code = 'A' THEN 1 ELSE 0 END) as absent"), DB::raw("SUM(CASE WHEN code = 'T' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN code = 'E' THEN 1 ELSE 0 END) as excused"))
            ->groupBy('grades.name', 'sections.name')->orderBy('grades.name')->get());
    }

    public function audits(Request $request): JsonResponse
    {
        return response()->json(AuditLog::with('user:id,name,email')->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->action, fn ($q, $action) => $q->where('action', $action))->latest('id')->paginate($request->integer('per_page', 30)));
    }

    public function backup(Request $request): StreamedResponse
    {
        $tables = ['academic_years', 'grades', 'sections', 'subjects', 'grade_subjects', 'periods', 'users', 'students', 'student_enrollments',
            'course_offerings', 'teacher_assignments', 'activity_templates', 'course_activities', 'competencies', 'activity_scores', 'period_grades',
            'final_grades', 'attendances', 'observations', 'alerts', 'promotion_decisions', 'grade_review_actions', 'audit_logs'];
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'backup', 'affected_table' => 'database', 'record_id' => 0, 'detail' => ['tables' => $tables], 'ip' => $request->ip()]);
        return response()->streamDownload(function () use ($tables) {
            echo "{\n\"generated_at\":".json_encode(now()->toIso8601String()).",\n\"tables\":{";
            foreach ($tables as $index => $table) {
                if ($index) echo ',';
                echo "\n".json_encode($table).':'.json_encode(DB::table($table)->get(), JSON_UNESCAPED_UNICODE);
            }
            echo "\n}}";
        }, 'cuaderno-nota-backup-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
    }
}
