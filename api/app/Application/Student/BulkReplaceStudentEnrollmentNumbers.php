<?php

namespace App\Application\Student;

use App\Infrastructure\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkReplaceStudentEnrollmentNumbers
{
    public function preview(array $data): array
    {
        $students = $this->studentsInRequestOrder($data['student_ids']);

        return $this->analyze($students, $data['search'], $data['replace']);
    }

    public function execute(array $data): array
    {
        try {
            return DB::transaction(function () use ($data): array {
                $locked = Student::query()
                    ->whereIn('id', $data['student_ids'])
                    ->lockForUpdate()
                    ->get();

                if ($locked->count() !== count($data['student_ids'])) {
                    throw ValidationException::withMessages([
                        'student_ids' => 'Uno de los estudiantes seleccionados ya no existe. Actualiza el workspace e intenta nuevamente.',
                    ]);
                }

                $students = collect($data['student_ids'])->map(fn ($id) => $locked->firstWhere('id', (int) $id));
                $analysis = $this->analyze($students, $data['search'], $data['replace']);

                if ($analysis['summary']['invalid'] > 0) {
                    throw ValidationException::withMessages([
                        'students' => 'La sustitución produciría matrículas inválidas o repetidas. No se modificó ningún estudiante.',
                    ]);
                }

                $changes = collect($analysis['rows'])->where('changed', true)->values();

                // A temporary unique value avoids collisions while exchanging prefixes or
                // replacing values that are currently held by another selected student.
                foreach ($changes as $row) {
                    Student::query()->whereKey($row['student_id'])->update([
                        'enrollment_no' => $this->temporaryEnrollmentNumber(),
                    ]);
                }

                foreach ($changes as $row) {
                    Student::query()->whereKey($row['student_id'])->update([
                        'enrollment_no' => $row['proposed_enrollment_no'],
                    ]);
                }

                $updated = $changes->count();

                return [
                    'message' => $updated === 1
                        ? '1 matrícula actualizada correctamente.'
                        : "{$updated} matrículas actualizadas correctamente.",
                    'updated' => $updated,
                    'summary' => $analysis['summary'],
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'students' => 'Una de las matrículas resultantes ya existe. No se modificó ningún estudiante; vuelve a generar la vista previa.',
            ]);
        }
    }

    private function analyze(Collection $students, string $search, string $replace): array
    {
        $selectedIds = $students->pluck('id')->map(fn ($id) => (int) $id)->all();
        $outsideKeys = Student::query()
            ->whereNotIn('id', $selectedIds)
            ->pluck('enrollment_no')
            ->mapWithKeys(fn ($value) => [$this->normalizedKey((string) $value) => true]);

        $rows = $students->map(function (Student $student) use ($search, $replace): array {
            $matches = 0;
            $proposed = str_replace($search, $replace, $student->enrollment_no, $matches);
            $changed = $matches > 0 && $proposed !== $student->enrollment_no;
            $errors = [];

            if ($changed && trim($proposed) === '') {
                $errors[] = 'La matrícula resultante no puede quedar vacía.';
            }
            if ($changed && $proposed !== trim($proposed)) {
                $errors[] = 'La matrícula resultante no puede comenzar ni terminar con espacios.';
            }
            if ($changed && mb_strlen($proposed) > 20) {
                $errors[] = 'La matrícula resultante supera los 20 caracteres.';
            }
            if ($changed && preg_match('/[\x00-\x1F\x7F]/', $proposed)) {
                $errors[] = 'La matrícula resultante contiene caracteres no válidos.';
            }

            return [
                'student_id' => $student->id,
                'student_name' => trim("{$student->name} {$student->last_name}"),
                'current_enrollment_no' => $student->enrollment_no,
                'proposed_enrollment_no' => $proposed,
                'matched' => $matches > 0,
                'changed' => $changed,
                'errors' => $errors,
            ];
        })->values()->all();

        $indexesByFinalKey = [];
        foreach ($rows as $index => $row) {
            $indexesByFinalKey[$this->normalizedKey($row['proposed_enrollment_no'])][] = $index;
        }

        foreach ($rows as $index => &$row) {
            if (! $row['changed']) {
                $row['valid'] = true;

                continue;
            }

            $key = $this->normalizedKey($row['proposed_enrollment_no']);
            if (count($indexesByFinalKey[$key] ?? []) > 1) {
                $row['errors'][] = 'La matrícula resultante quedaría repetida dentro de la selección.';
            }
            if ($outsideKeys->has($key)) {
                $row['errors'][] = 'La matrícula resultante ya pertenece a otro estudiante.';
            }
            $row['errors'] = array_values(array_unique($row['errors']));
            $row['valid'] = $row['errors'] === [];
        }
        unset($row);

        $matched = count(array_filter($rows, fn ($row) => $row['matched']));
        $changed = count(array_filter($rows, fn ($row) => $row['changed']));
        $invalid = count(array_filter($rows, fn ($row) => $row['changed'] && ! $row['valid']));

        return [
            'summary' => [
                'total' => count($rows),
                'matched' => $matched,
                'changed' => $changed,
                'ready' => $changed - $invalid,
                'invalid' => $invalid,
            ],
            'rows' => $rows,
        ];
    }

    private function studentsInRequestOrder(array $ids): Collection
    {
        $students = Student::query()->whereIn('id', $ids)->get()->keyBy('id');

        if ($students->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'student_ids' => 'Uno de los estudiantes seleccionados ya no existe. Actualiza el workspace e intenta nuevamente.',
            ]);
        }

        return collect($ids)->map(fn ($id) => $students->get((int) $id));
    }

    private function normalizedKey(string $value): string
    {
        return Str::upper(Str::ascii(trim($value)));
    }

    private function temporaryEnrollmentNumber(): string
    {
        do {
            $value = '~'.Str::upper(Str::random(19));
        } while (Student::query()->where('enrollment_no', $value)->exists());

        return $value;
    }
}
