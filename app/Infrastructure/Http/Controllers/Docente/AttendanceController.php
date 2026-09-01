<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Attendance\GetAttendanceBySection;
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
        private readonly GetAttendanceBySection $getAttendanceBySection,
        private readonly RegisterAttendance     $registerAttendance,
        private readonly UpdateToExcuse         $updateToExcuse,
    ) {}

    public function index(int $sectionId, string $date): JsonResponse
    {
        if (! $this->teacherCanManageSection($sectionId)) {
            return response()->json(['message' => 'No tienes permiso para consultar la asistencia de esta sección.'], 403);
        }

        if (! $this->dateBelongsToActivePeriod($sectionId, $date)) {
            return response()->json(['message' => 'La fecha no pertenece a un período actualmente activo.'], 422);
        }

        $records = $this->getAttendanceBySection->execute($sectionId, $date);

        return response()->json(['date' => $date, 'records' => $records]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'date'       => 'required|date_format:Y-m-d',
            'status'     => 'required|in:present,late,absent,excused',
        ]);

        $sectionId = DB::table('students')
            ->where('id', $validated['student_id'])
            ->where('active', true)
            ->value('section_id');

        if (! $sectionId) {
            return response()->json(['message' => 'El estudiante no tiene una sección activa asignada.'], 422);
        }

        if (! $this->teacherCanManageSection((int) $sectionId)) {
            return response()->json(['message' => 'No tienes permiso para registrar asistencia en esta sección.'], 403);
        }

        if (! $this->dateBelongsToActivePeriod((int) $sectionId, $validated['date'])) {
            return response()->json(['message' => 'La fecha no pertenece a un período actualmente activo.'], 422);
        }

        $existingOwner = DB::table('attendances')
            ->where('student_id', $validated['student_id'])
            ->whereDate('date', $validated['date'])
            ->value('user_id');

        if ($existingOwner !== null && (int) $existingOwner !== (int) Auth::id()) {
            return response()->json(['message' => 'La asistencia de este día fue registrada por otro docente.'], 403);
        }

        $record = new AttendanceRecord(
            id:        0,
            studentId: $validated['student_id'],
            date:      new DateTimeImmutable($validated['date']),
            status:    $validated['status'],
        );

        $alerts = $this->registerAttendance->execute($record);

        return response()->json([
            'message' => 'Asistencia registrada.',
            'alerts'  => $alerts,
        ], 201);
    }

    public function updateExcuse(int $id): JsonResponse
    {
        $attendance = DB::table('attendances')->where('id', $id)->first(['section_id', 'user_id', 'date']);

        if (! $attendance) {
            return response()->json(['message' => 'Registro de asistencia no encontrado.'], 404);
        }

        if (! $this->teacherCanManageSection((int) $attendance->section_id)) {
            return response()->json(['message' => 'No tienes permiso para justificar asistencia en esta sección.'], 403);
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

    private function teacherCanManageSection(int $sectionId): bool
    {
        return DB::table('teacher_sections')
            ->where('user_id', Auth::id())
            ->where('section_id', $sectionId)
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
