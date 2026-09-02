<?php

namespace App\Application\Student;

use App\Infrastructure\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportStudentsFromCsv
{
    public const MAX_ROWS = 1000;
    private const REQUIRED_HEADERS = ['MATRICULA', 'NOMBRES', 'APELLIDOS'];

    public function execute(UploadedFile $file): array
    {
        // Re-read and validate on confirmation; the preview may be out of date.
        $analysis = $this->analyze($file);
        if ($analysis['summary']['invalid'] > 0) {
            throw ValidationException::withMessages([
                'file' => "El archivo contiene {$analysis['summary']['invalid']} filas con errores. No se guardó ningún estudiante. Vuelve a validar el archivo.",
            ]);
        }

        try {
            DB::transaction(function () use ($analysis): void {
                $timestamp = now();
                foreach (array_chunk($analysis['rows'], 250) as $rows) {
                    Student::query()->insert(array_map(fn ($row) => [
                        ...$row['data'],
                        'section_id' => null,
                        'academic_year_id' => null,
                        'active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ], $rows));
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'file' => 'Una matrícula fue registrada mientras se procesaba el archivo. No se guardó ningún estudiante de esta carga. Vuelve a validar antes de importar.',
            ]);
        }

        $count = $analysis['summary']['total'];
        return [
            'message' => $count === 1
                ? '1 estudiante registrado y pendiente de asignación de sección.'
                : "{$count} estudiantes registrados y pendientes de asignación de sección.",
            'imported' => $count,
            'pending_placement' => $count,
        ];
    }

    public function analyze(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || trim($contents, "\xEF\xBB\xBF \t\n\r") === '') {
            throw ValidationException::withMessages(['file' => 'El archivo CSV está vacío o no se pudo leer.']);
        }
        if (!mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'Guarda el archivo como CSV UTF-8 para conservar correctamente los nombres y acentos.']);
        }
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'No fue posible leer el archivo CSV.']);
        }

        try {
            $firstLine = fgets($handle);
            $delimiter = count(str_getcsv($firstLine, ';', '"', '')) > count(str_getcsv($firstLine, ',', '"', '')) ? ';' : ',';
            rewind($handle);
            $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), fgetcsv($handle, 0, $delimiter, '"', '') ?: []);
            if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
                throw ValidationException::withMessages(['file' => 'El CSV contiene encabezados vacíos o repetidos. Usa una sola columna para matrícula, nombre y apellido.']);
            }
            $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
            if ($missing !== []) {
                throw ValidationException::withMessages(['file' => 'Faltan columnas obligatorias: '.implode(', ', $missing).'.']);
            }

            $ignoredColumns = array_values(array_diff($headers, self::REQUIRED_HEADERS));
            $existing = Student::query()->pluck('enrollment_no')->mapWithKeys(fn ($value) => [$this->normalizeKey($value) => true]);
            $occurrences = [];
            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if (count(array_filter($values, fn ($value) => $this->clean((string) $value) !== '')) === 0) continue;
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'El archivo supera los '.self::MAX_ROWS.' estudiantes por carga. Divídelo en varios archivos.']);
                }
                $errors = [];
                if (count($values) !== count($headers)) {
                    $errors[] = 'La cantidad de columnas no coincide con el encabezado. Revisa los separadores y las comillas.';
                }
                $values = array_slice(array_pad($values, count($headers), ''), 0, count($headers));
                $raw = array_combine($headers, $values);
                $data = [
                    'enrollment_no' => $this->clean((string) $raw['MATRICULA']),
                    'name' => $this->clean((string) $raw['NOMBRES']),
                    'last_name' => $this->clean((string) $raw['APELLIDOS']),
                ];
                foreach (['enrollment_no' => ['Matrícula', 20], 'name' => ['Nombres', 60], 'last_name' => ['Apellidos', 60]] as $field => [$label, $max]) {
                    if ($data[$field] === '') $errors[] = "{$label} es obligatorio.";
                    if (mb_strlen($data[$field]) > $max) $errors[] = "{$label} supera los {$max} caracteres.";
                    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $data[$field])) $errors[] = "{$label} contiene caracteres no válidos.";
                }
                $key = $this->normalizeKey($data['enrollment_no']);
                if ($key !== '') {
                    if (isset($existing[$key])) $errors[] = 'La matrícula ya existe en el sistema.';
                    $occurrences[$key][] = count($rows);
                }
                $rows[] = [
                    'row_number' => $line,
                    'data' => $data,
                    'errors' => $errors,
                    'section_id' => null,
                    'academic_year_id' => null,
                    'section_label' => 'Pendiente de asignación',
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene estudiantes. Agrega los datos debajo del encabezado.']);
        }
        foreach ($occurrences as $indexes) {
            if (count($indexes) < 2) continue;
            foreach ($indexes as $index) $rows[$index]['errors'][] = 'La matrícula está repetida dentro del archivo.';
        }
        foreach ($rows as &$row) $row['valid'] = $row['errors'] === [];
        unset($row);
        $valid = count(array_filter($rows, fn ($row) => $row['valid']));

        return [
            'summary' => ['total' => count($rows), 'valid' => $valid, 'invalid' => count($rows) - $valid],
            'ignored_columns' => $ignoredColumns,
            'rows' => $rows,
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtoupper(Str::ascii(trim($value, "\xEF\xBB\xBF \t\n\r\0\x0B")));
        $value = trim(preg_replace('/[^A-Z0-9]+/', '_', $value) ?? $value, '_');
        return match ($value) {
            'NOMBRE' => 'NOMBRES',
            'APELLIDO' => 'APELLIDOS',
            'NUMERO_MATRICULA', 'NUMERO_DE_MATRICULA' => 'MATRICULA',
            default => $value,
        };
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value);
    }

    private function normalizeKey(string $value): string
    {
        return strtoupper(Str::ascii($this->clean($value)));
    }
}
