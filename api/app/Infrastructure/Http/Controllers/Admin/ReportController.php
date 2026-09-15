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
    public function attendanceCourses(Request $request): JsonResponse
    {
        $data = $request->validate(['academic_year_id' => 'required|integer|exists:academic_years,id']);
        return response()->json(DB::table('sections')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->where('sections.academic_year_id', $data['academic_year_id'])
            ->select('sections.id', 'grades.name as grade', 'sections.name as section', 'sections.shift')
            ->orderBy('grades.sort_order')->orderBy('sections.name')->orderBy('sections.shift')->get());
    }

    public function attendanceRecords(Request $request, int $sectionId): JsonResponse
    {
        $data = $request->validate(['academic_year_id' => 'required|integer|exists:academic_years,id', 'date' => 'nullable|date_format:Y-m-d']);
        abort_unless(DB::table('sections')->where('id', $sectionId)->where('academic_year_id', $data['academic_year_id'])->exists(), 404);
        $base = DB::table('attendances')->where('attendances.section_id', $sectionId);
        $dates = (clone $base)->select('date')->distinct()->orderByDesc('date')->pluck('date');
        $date = $data['date'] ?? $dates->first();
        $rows = $date === null ? collect() : (clone $base)
            ->join('students', 'attendances.student_id', '=', 'students.id')
            ->leftJoin('subjects', 'attendances.subject_id', '=', 'subjects.id')
            ->leftJoin('users', 'attendances.user_id', '=', 'users.id')
            ->whereDate('attendances.date', $date)
            ->select('attendances.id', 'attendances.date', 'attendances.code', 'attendances.subject_id',
                'students.name', 'students.last_name', 'students.enrollment_no', 'users.name as teacher',
                DB::raw("COALESCE(subjects.name, 'Sin materia (histórico)') as subject"))
            ->orderBy('students.last_name')->orderBy('students.name')->orderBy('subjects.name')->get();
        return response()->json(['date' => $date, 'dates' => $dates, 'rows' => $rows]);
    }

    public function academic(Request $request): JsonResponse
    {
        $yearId = $request->integer('academic_year_id') ?: DB::table('academic_years')->where('active', true)->value('id');
        $rows = DB::table('period_grades')->join('students', 'period_grades.student_id', '=', 'students.id')
            ->join('sections', 'period_grades.section_id', '=', 'sections.id')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->join('subjects', 'period_grades.subject_id', '=', 'subjects.id')->join('periods', 'period_grades.period_id', '=', 'periods.id')
            ->where('periods.academic_year_id', $yearId)->where('period_grades.status', 'official')
            ->select('grades.name as grade', 'sections.name as section', 'subjects.name as subject', 'periods.name as period',
                DB::raw('COUNT(*) as students'), DB::raw('ROUND(AVG(COALESCE(period_grades.rp_score, period_grades.period_score)), 2) as average'),
                DB::raw('SUM(CASE WHEN COALESCE(period_grades.rp_score, period_grades.period_score) < 70 THEN 1 ELSE 0 END) as at_risk'))
            ->groupBy('grades.name', 'sections.name', 'subjects.name', 'periods.name')->orderBy('grades.name')->get();
        // AVG/ROUND on a numeric column comes back from PDO as a string on MySQL/PostgreSQL (unlike SQLite), so cast explicitly.
        $rows->each(fn ($row) => $row->average = $row->average === null ? null : (float) $row->average);
        $summary = DB::table('period_grades')
            ->join('periods', 'period_grades.period_id', '=', 'periods.id')
            ->where('periods.academic_year_id', $yearId)
            ->where('period_grades.status', 'official')
            ->selectRaw('COUNT(DISTINCT period_grades.student_id) as evaluated_students')
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(period_grades.rp_score, period_grades.period_score) < 70 THEN period_grades.student_id END) as at_risk_students')
            ->selectRaw('ROUND(AVG(COALESCE(period_grades.rp_score, period_grades.period_score)), 2) as overall_average')
            ->first();
        if ($summary !== null) {
            $summary->overall_average = $summary->overall_average === null ? null : (float) $summary->overall_average;
        }

        return response()->json(['academic_year_id' => $yearId, 'summary' => $summary, 'rows' => $rows]);
    }

    public function academicRecords(Request $request, int $sectionId): JsonResponse
    {
        $data = $request->validate(['academic_year_id' => 'required|integer|exists:academic_years,id']);
        abort_unless(DB::table('sections')->where('id', $sectionId)->where('academic_year_id', $data['academic_year_id'])->exists(), 404);
        $rows = DB::table('period_grades as pg')
            ->join('students as s', 'pg.student_id', '=', 's.id')
            ->join('subjects', 'pg.subject_id', '=', 'subjects.id')
            ->join('periods', 'pg.period_id', '=', 'periods.id')
            ->where('pg.section_id', $sectionId)->where('periods.academic_year_id', $data['academic_year_id'])
            ->where('pg.status', 'official')
            ->select('pg.id', 'pg.student_id', 'pg.subject_id', 'pg.period_id', 's.name', 's.last_name', 's.enrollment_no',
                'subjects.name as subject', 'periods.name as period', 'pg.c1_score', 'pg.c2_score', 'pg.c3_score',
                'pg.period_score', 'pg.rp_score', DB::raw('COALESCE(pg.rp_score, pg.period_score) as effective_score'))
            ->orderBy('s.last_name')->orderBy('s.name')->orderBy('subjects.name')->orderBy('periods.number')->get();
        foreach ($rows as $row) {
            foreach (['c1_score', 'c2_score', 'c3_score', 'period_score', 'rp_score', 'effective_score'] as $field) {
                $row->$field = $row->$field === null ? null : (float) $row->$field;
            }
        }
        return response()->json(['rows' => $rows, 'periods' => DB::table('periods')->where('academic_year_id', $data['academic_year_id'])->orderBy('number')->get(['id', 'name'])]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $yearId = $request->integer('academic_year_id') ?: DB::table('academic_years')->where('active', true)->value('id');

        return response()->json(DB::table('attendances')->join('sections', 'attendances.section_id', '=', 'sections.id')->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->leftJoin('subjects', 'attendances.subject_id', '=', 'subjects.id')
            ->where('sections.academic_year_id', $yearId)->select('sections.id as section_id', 'sections.shift', 'grades.name as grade', 'sections.name as section',
                DB::raw("COALESCE(subjects.name, 'Sin materia (histórico)') as subject"),
                DB::raw('COUNT(*) as records'), DB::raw("SUM(CASE WHEN attendances.code IN ('P', 'T') THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendances.code = 'A' THEN 1 ELSE 0 END) as absent"), DB::raw("SUM(CASE WHEN attendances.code = 'T' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN attendances.code = 'E' THEN 1 ELSE 0 END) as excused"))
            ->groupBy('sections.id', 'sections.shift', 'grades.name', 'sections.name', 'subjects.id', 'subjects.name')->orderBy('grades.name')->orderBy('sections.name')->orderBy('subjects.name')->get());
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
                if ($index) {
                    echo ',';
                }
                $query = DB::table($table);

                if ($table === 'users') {
                    $query->select([
                        'id', 'name', 'email', 'email_verified_at', 'role', 'active',
                        'last_login', 'created_at', 'updated_at',
                    ]);
                }

                echo "\n".json_encode($table).':'.json_encode($query->get(), JSON_UNESCAPED_UNICODE);
            }
            echo "\n}}";
        }, 'cuaderno-nota-backup-'.now()->format('Ymd-His').'.json', ['Content-Type' => 'application/json']);
    }
}
