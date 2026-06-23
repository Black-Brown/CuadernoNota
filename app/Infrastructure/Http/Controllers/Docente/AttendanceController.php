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

class AttendanceController extends Controller
{
    public function __construct(
        private readonly GetAttendanceBySection $getAttendanceBySection,
        private readonly RegisterAttendance     $registerAttendance,
        private readonly UpdateToExcuse         $updateToExcuse,
    ) {}

    public function index(int $sectionId, string $date): JsonResponse
    {
        $records = $this->getAttendanceBySection->execute($sectionId, $date);

        return response()->json(['date' => $date, 'records' => $records]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'date'       => 'required|date_format:Y-m-d',
            'status'     => 'required|in:present,absent,excused',
        ]);

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
        $this->updateToExcuse->execute($id);

        return response()->json(['message' => 'Falta justificada correctamente.']);
    }
}
