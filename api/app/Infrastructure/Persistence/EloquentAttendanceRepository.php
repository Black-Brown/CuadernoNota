<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Attendance\Entities\AttendanceRecord;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Infrastructure\Models\Attendance as AttendanceModel;
use App\Infrastructure\Models\Section as SectionModel;
use App\Infrastructure\Models\Student as StudentModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class EloquentAttendanceRepository implements AttendanceRepositoryInterface
{
    /**
     * Mapeo de código DB → status dominio.
     * T (tarde) conserva su estado y cuenta como asistencia.
     */
    private const CODE_TO_STATUS = [
        'P' => AttendanceRecord::STATUS_PRESENT,
        'A' => AttendanceRecord::STATUS_ABSENT,
        'T' => AttendanceRecord::STATUS_LATE,
        'E' => AttendanceRecord::STATUS_EXCUSED,
    ];

    /** Mapeo de status dominio → código DB. */
    private const STATUS_TO_CODE = [
        AttendanceRecord::STATUS_PRESENT => 'P',
        AttendanceRecord::STATUS_ABSENT => 'A',
        AttendanceRecord::STATUS_LATE => 'T',
        AttendanceRecord::STATUS_EXCUSED => 'E',
    ];

    /**
     * Persiste un registro de asistencia.
     * La materia es obligatoria para todo registro nuevo.
     */
    public function save(AttendanceRecord $record): void
    {
        $student = StudentModel::findOrFail($record->studentId);
        $sectionId = $record->sectionId ?? $student->section_id;

        if ($record->subjectId === null) {
            throw new InvalidArgumentException('La materia es obligatoria para registrar asistencia.');
        }

        AttendanceModel::updateOrCreate(
            [
                'student_id' => $record->studentId,
                'subject_id' => $record->subjectId,
                'date' => $record->date->format('Y-m-d'),
            ],
            [
                'section_id' => $sectionId,
                'user_id' => $record->teacherId ?? Auth::id() ?? 1,
                'code' => self::STATUS_TO_CODE[$record->status] ?? 'A',
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

    public function findByStudentAndCourseAcademicYear(
        int $studentId,
        int $sectionId,
        int $subjectId,
    ): array {
        $section = SectionModel::with('academicYear')->findOrFail($sectionId);

        return AttendanceModel::where('student_id', $studentId)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->whereDate('date', '>=', $section->academicYear->start_date)
            ->whereDate('date', '<=', $section->academicYear->end_date)
            ->orderBy('date')
            ->get()
            ->map(fn ($model) => $this->toEntity($model))
            ->all();
    }

    public function findByCourseAndDate(int $sectionId, int $subjectId, string $date): array
    {
        $section = SectionModel::with(['students' => fn ($q) => $q->where('active', true)->orderBy('last_name')])->findOrFail($sectionId);

        $existing = AttendanceModel::where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        $codeToStatus = self::CODE_TO_STATUS;

        return $section->students->map(function ($student) use ($existing, $codeToStatus): array {
            $attendance = $existing->get($student->id);

            return [
                'attendance_id' => $attendance?->id,
                'student_id' => $student->id,
                'student_name' => $student->last_name.', '.$student->name,
                'status' => $attendance ? ($codeToStatus[$attendance->code] ?? null) : null,
            ];
        })->all();
    }

    /** Convierte el modelo Eloquent a la entidad de dominio AttendanceRecord. */
    private function toEntity(AttendanceModel $model): AttendanceRecord
    {
        return new AttendanceRecord(
            id: $model->id,
            studentId: $model->student_id,
            date: new DateTimeImmutable($model->date->format('Y-m-d')),
            status: self::CODE_TO_STATUS[$model->code] ?? AttendanceRecord::STATUS_ABSENT,
            sectionId: $model->section_id,
            subjectId: $model->subject_id,
            teacherId: $model->user_id,
        );
    }
}
