<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Attendance\Entities\AttendanceRecord;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Infrastructure\Models\Attendance as AttendanceModel;
use App\Infrastructure\Models\Section as SectionModel;
use App\Infrastructure\Models\Student as StudentModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;

class EloquentAttendanceRepository implements AttendanceRepositoryInterface
{
    /**
     * Mapeo de código DB → status dominio.
     * T (tarde) se trata como ausencia para efectos de alertas.
     */
    private const CODE_TO_STATUS = [
        'P' => AttendanceRecord::STATUS_PRESENT,
        'A' => AttendanceRecord::STATUS_ABSENT,
        'T' => AttendanceRecord::STATUS_ABSENT,
        'E' => AttendanceRecord::STATUS_EXCUSED,
    ];

    /** Mapeo de status dominio → código DB. */
    private const STATUS_TO_CODE = [
        AttendanceRecord::STATUS_PRESENT => 'P',
        AttendanceRecord::STATUS_ABSENT  => 'A',
        AttendanceRecord::STATUS_EXCUSED => 'E',
    ];

    /**
     * Persiste un registro de asistencia.
     * Obtiene section_id del estudiante para cumplir el FK requerido.
     */
    public function save(AttendanceRecord $record): void
    {
        $student = StudentModel::findOrFail($record->studentId);

        AttendanceModel::updateOrCreate(
            [
                'student_id' => $record->studentId,
                'date'       => $record->date->format('Y-m-d'),
            ],
            [
                'section_id' => $student->section_id,
                'user_id'    => Auth::id() ?? 1,
                'code'       => self::STATUS_TO_CODE[$record->status] ?? 'A',
            ]
        );
    }

    /** Actualiza un registro existente (p.ej. de absent a excused). */
    public function update(AttendanceRecord $record): void
    {
        AttendanceModel::where('id', $record->id)
            ->update(['code' => self::STATUS_TO_CODE[$record->status] ?? 'A']);
    }

    public function findById(int $id): ?AttendanceRecord
    {
        $model = AttendanceModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /** Registros de un estudiante en un mes, ordenados por fecha asc. */
    public function findByStudentAndMonth(int $studentId, int $year, int $month): array
    {
        return AttendanceModel::where('student_id', $studentId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    /** Todos los registros del año para calcular el porcentaje anual. */
    public function findByStudentAndYear(int $studentId, int $year): array
    {
        return AttendanceModel::where('student_id', $studentId)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function findBySectionAndDate(int $sectionId, string $date): array
    {
        $section = SectionModel::with(['students' => fn($q) => $q->where('active', true)->orderBy('last_name')])->findOrFail($sectionId);

        $existing = AttendanceModel::where('section_id', $sectionId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        $codeToStatus = self::CODE_TO_STATUS;

        return $section->students->map(fn($student) => [
            'attendance_id' => $existing[$student->id]?->id,
            'student_id'    => $student->id,
            'student_name'  => $student->last_name . ', ' . $student->name,
            'status'        => isset($existing[$student->id])
                ? ($codeToStatus[$existing[$student->id]->code] ?? null)
                : null,
        ])->all();
    }

    /** Convierte el modelo Eloquent a la entidad de dominio AttendanceRecord. */
    private function toEntity(AttendanceModel $model): AttendanceRecord
    {
        return new AttendanceRecord(
            id:        $model->id,
            studentId: $model->student_id,
            date:      new DateTimeImmutable($model->date->format('Y-m-d')),
            status:    self::CODE_TO_STATUS[$model->code] ?? AttendanceRecord::STATUS_ABSENT,
        );
    }
}
