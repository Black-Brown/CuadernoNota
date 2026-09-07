<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Attendance\GetAttendanceByCourse;
use App\Application\Attendance\RegisterAttendance;
use App\Application\Attendance\UpdateToExcuse;
use App\Domain\Attendance\Entities\AttendanceRecord;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly GetAttendanceByCourse $getAttendanceByCourse,
        private readonly RegisterAttendance $registerAttendance,
        private readonly UpdateToExcuse $updateToExcuse,
    ) {}

    public function index(int $sectionId, int $subjectId, string $date): JsonResponse
    {
        if (! $this->teacherCanManageCourse($sectionId, $subjectId)) {
            return response()->json(['message' => 'No tienes permiso para consultar la asistencia de este curso.'], 403);
        }

        if (! $this->dateBelongsToActivePeriod($sectionId, $date)) {
            return response()->json(['message' => 'La fecha no pertenece a un período actualmente activo.'], 422);
        }

        $records = $this->getAttendanceByCourse->execute($sectionId, $subjectId, $date);

        return response()->json([
            'date' => $date,
            'section_id' => $sectionId,
            'subject_id' => $subjectId,
            'records' => $records,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'required|in:present,late,absent,excused',
        ]);

        $sectionId = DB::table('students')
            ->where('id', $validated['student_id'])
            ->where('active', true)
            ->value('section_id');

        if (! $sectionId) {
            return response()->json(['message' => 'El estudiante no tiene una sección activa asignada.'], 422);
        }

        if (! $this->teacherCanManageCourse((int) $sectionId, (int) $validated['subject_id'])) {
            return response()->json(['message' => 'No tienes permiso para registrar asistencia en este curso.'], 403);
        }

        if (! $this->dateBelongsToActivePeriod((int) $sectionId, $validated['date'])) {
            return response()->json(['message' => 'La fecha no pertenece a un período actualmente activo.'], 422);
        }

        $existingOwner = DB::table('attendances')
            ->where('student_id', $validated['student_id'])
            ->where('subject_id', $validated['subject_id'])
            ->whereDate('date', $validated['date'])
            ->value('user_id');

        if ($existingOwner !== null && (int) $existingOwner !== (int) Auth::id()) {
            return response()->json(['message' => 'La asistencia de este día fue registrada por otro docente.'], 403);
        }

        $record = new AttendanceRecord(
            id: 0,
            studentId: $validated['student_id'],
            date: new DateTimeImmutable($validated['date']),
            status: $validated['status'],
            sectionId: (int) $sectionId,
            subjectId: (int) $validated['subject_id'],
            teacherId: (int) Auth::id(),
        );

        $alerts = $this->registerAttendance->execute($record);

        return response()->json([
            'message' => 'Asistencia registrada.',
            'alerts' => $alerts,
        ], 201);
    }

    public function updateExcuse(int $id): JsonResponse
    {
        $attendance = DB::table('attendances')->where('id', $id)->first(['section_id', 'subject_id', 'user_id', 'date']);

        if (! $attendance) {
            return response()->json(['message' => 'Registro de asistencia no encontrado.'], 404);
        }

        if ($attendance->subject_id === null || ! $this->teacherCanManageCourse((int) $attendance->section_id, (int) $attendance->subject_id)) {
            return response()->json(['message' => 'No tienes permiso para justificar asistencia en este curso.'], 403);
        }

        if ((int) $attendance->user_id !== (int) Auth::id()) {
            return response()->json(['message' => 'No puedes modificar una asistencia registrada por otro docente.'], 403);
        }

        if (! $this->dateBelongsToActivePeriod((int) $attendance->section_id, (string) $attendance->date)) {
            return response()->json(['message' => 'El registro no pertenece a un período actualmente activo.'], 422);
        }

        $this->updateToExcuse->execute($id);

        return response()->json(['message' => 'Falta justificada correctamente.']);
    }

    private function teacherCanManageCourse(int $sectionId, int $subjectId): bool
    {
        return DB::table('teacher_sections')
            ->where('user_id', Auth::id())
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function dateBelongsToActivePeriod(int $sectionId, string $date): bool
    {
        return DB::table('sections')
            ->join('periods', 'periods.academic_year_id', '=', 'sections.academic_year_id')
            ->where('sections.id', $sectionId)
            ->where('periods.status', 'open')
            ->whereDate('periods.start_date', '<=', $date)
            ->whereDate('periods.end_date', '>=', $date)
            ->whereDate('periods.start_date', '<=', now()->toDateString())
            ->whereDate('periods.end_date', '>=', now()->toDateString())
            ->exists();
    }
}
