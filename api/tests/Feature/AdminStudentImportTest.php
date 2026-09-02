<?php

namespace Tests\Feature;

use App\Application\Student\ImportStudentsFromCsv;
use App\Infrastructure\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStudentImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "MATRICULA,NOMBRES,APELLIDOS\n";

    public function test_preview_does_not_create_students_and_import_needs_no_academic_catalog(): void
    {
        $this->admin();
        $csv = self::HEADER."0001,Ana María,Pérez Soto\n0002,Juan,De la Cruz\n";
        $this->upload($csv)->assertOk()->assertExactJson([
            'summary' => ['total' => 2, 'valid' => 2, 'invalid' => 0],
            'ignored_columns' => [],
            'rows' => [
                $this->previewRow(2, '0001', 'Ana María', 'Pérez Soto'),
                $this->previewRow(3, '0002', 'Juan', 'De la Cruz'),
            ],
        ]);
        $this->assertDatabaseCount('students', 0);
        $this->upload($csv, false)->assertCreated()->assertJsonPath('imported', 2)->assertJsonPath('pending_placement', 2);
        $this->assertDatabaseCount('students', 2);
        $this->assertDatabaseCount('student_enrollments', 0);
        $this->assertDatabaseCount('academic_years', 0);
        $this->assertDatabaseCount('sections', 0);
        $this->assertDatabaseHas('students', [
            'enrollment_no' => '0001', 'name' => 'Ana María', 'last_name' => 'Pérez Soto',
            'section_id' => null, 'academic_year_id' => null, 'active' => true,
        ]);
        $this->getJson('/api/admin/student-placements/pending')->assertOk()->assertJsonCount(2)->assertJsonFragment(['enrollment_no' => '0001', 'placement_reason' => 'Nuevo ingreso']);
        $audit = json_decode(DB::table('audit_logs')->orderByDesc('id')->value('detail'), true);
        $this->assertSame('api/admin/students/import', $audit['path']);
        $this->assertSame(['name', 'type', 'size'], array_keys($audit['input']['file']));
        $this->assertStringNotContainsString('Ana María', json_encode($audit));
    }

    public function test_accepts_bom_semicolon_aliases_reordered_headers_and_quoted_values(): void
    {
        $this->admin();
        $csv = "\xEF\xBB\xBF apellido ; número de matrícula ; nombre\r\n\r\nPérez  Soto;00009;  Ana   María  \r\n\"De la Cruz; López\";00010;\"José, Luis\"\r\n";
        $this->upload($csv, false)->assertCreated()->assertJsonPath('imported', 2);
        $this->assertDatabaseHas('students', ['enrollment_no' => '00009', 'name' => 'Ana María', 'last_name' => 'Pérez Soto']);
        $this->assertDatabaseHas('students', ['enrollment_no' => '00010', 'name' => 'José, Luis', 'last_name' => 'De la Cruz; López']);
    }

    public function test_legacy_and_extra_columns_are_reported_and_never_assign_or_change_status(): void
    {
        $this->admin();
        $csv = "MATRICULA,NOMBRES,APELLIDOS,ANO_ESCOLAR,GRADO,SECCION,TANDA,FECHA_INSCRIPCION,NOMBRE_TUTOR,SECTION_ID,ACTIVE\nLEG-1,Ana,Pérez,2026-2027,1RO SECUNDARIA,A,Matutina,2026-09-01,Tutor,999,0";
        $this->upload($csv)->assertOk()->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('ignored_columns', ['ANO_ESCOLAR', 'GRADO', 'SECCION', 'TANDA', 'FECHA_INSCRIPCION', 'NOMBRE_TUTOR', 'SECTION_ID', 'ACTIVE']);
        $this->upload($csv, false)->assertCreated();
        $this->assertDatabaseHas('students', ['enrollment_no' => 'LEG-1', 'section_id' => null, 'academic_year_id' => null, 'active' => true]);
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    public function test_every_duplicate_in_the_file_is_invalid_and_no_partial_import_is_permitted(): void
    {
        $this->admin();
        $csv = self::HEADER."DUP-1,Ana,Pérez\ndup-1,Luis,López\nUNICO,Eva,Gil";
        $this->upload($csv)->assertOk()->assertJsonPath('summary.invalid', 2)->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('rows.0.errors.0', 'La matrícula está repetida dentro del archivo.')
            ->assertJsonPath('rows.1.valid', false);
        $this->upload($csv, false)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_existing_inactive_students_are_not_overwritten_or_reactivated(): void
    {
        $this->admin();
        $student = Student::create(['enrollment_no' => 'REG-01', 'name' => 'Original', 'last_name' => 'Apellido', 'active' => false]);
        $csv = self::HEADER."reg-01,Modificado,Otro\nNUEVO,Ana,Pérez";
        $this->upload($csv)->assertOk()->assertJsonPath('summary.invalid', 1)->assertJsonPath('rows.0.errors.0', 'La matrícula ya existe en el sistema.');
        $this->upload($csv, false)->assertUnprocessable();
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Original', 'last_name' => 'Apellido', 'active' => false]);
    }

    public function test_confirmation_revalidates_if_someone_registered_a_number_after_preview(): void
    {
        $this->admin();
        $csv = self::HEADER."RACE-1,Ana,Pérez\nRACE-2,Luis,Gil";
        $this->upload($csv)->assertOk()->assertJsonPath('summary.valid', 2);
        Student::create(['enrollment_no' => 'RACE-1', 'name' => 'Registro', 'last_name' => 'Manual', 'active' => true]);
        $this->upload($csv, false)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseMissing('students', ['enrollment_no' => 'RACE-2']);
    }

    public function test_importing_the_same_file_twice_does_not_duplicate_students(): void
    {
        $this->admin();
        $csv = self::HEADER."ONCE-1,Ana,Pérez";
        $this->upload($csv, false)->assertCreated();
        $this->upload($csv, false)->assertUnprocessable();
        $this->assertDatabaseCount('students', 1);
    }

    #[DataProvider('invalidFiles')]
    public function test_rejects_invalid_files_without_writes(string $csv): void
    {
        $this->admin();
        $this->upload($csv)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload($csv, false)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('students', 0);
    }

    public static function invalidFiles(): array
    {
        return [
            'empty' => [''],
            'headers only' => [self::HEADER],
            'blank rows' => [self::HEADER."\n,,\n"],
            'missing surname' => ["MATRICULA,NOMBRES\n1,Ana"],
            'duplicate header' => ["MATRICULA,NOMBRES,APELLIDOS,NOMBRE\n1,Ana,Pérez,Eva"],
            'empty header' => ["MATRICULA,NOMBRES,APELLIDOS,\n1,Ana,Pérez,"],
            'non UTF-8' => [self::HEADER."1,Ana,P\xE9rez"],
        ];
    }

    public function test_preview_identifies_incomplete_long_and_misaligned_rows(): void
    {
        $this->admin();
        $csv = self::HEADER."1,,Pérez\n2,Ana\n3,Luis,Gil,extra\n".str_repeat('X', 21).',Ana,Pérez'."\n5,".str_repeat('á', 61).",Gil\n6,Ana,".str_repeat('Z', 61)."\n7,Ana,Pe\x00rez";
        $response = $this->upload($csv)->assertOk()->assertJsonPath('summary.invalid', 7)->assertJsonPath('summary.valid', 0);
        foreach ($response->json('rows') as $row) $this->assertNotEmpty($row['errors']);
        $this->upload($csv, false)->assertUnprocessable();
        $this->assertDatabaseCount('students', 0);
    }

    public function test_upload_validates_missing_extension_and_server_size_limit(): void
    {
        $this->admin();
        foreach (['/api/admin/students/import/preview', '/api/admin/students/import'] as $url) {
            $this->postJson($url)->assertUnprocessable()->assertJsonPath('errors.file.0', 'Selecciona un archivo CSV.');
            $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('listado.txt', self::HEADER.'1,Ana,Pérez')])->assertUnprocessable()->assertJsonValidationErrors('file');
            $this->postJson($url, ['file' => UploadedFile::fake()->create('listado.csv', 5121)])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('students', 0);
    }

    public function test_supports_a_thousand_students_and_rejects_larger_batches(): void
    {
        $this->admin();
        $csv = self::HEADER.implode("\n", array_map(fn ($i) => "BATCH-{$i},Nombre,Apellido", range(1, 1000)));
        $this->upload($csv."\nBATCH-1001,Nombre,Apellido", false)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('students', 0);
        $this->upload($csv, false)->assertCreated()->assertJsonPath('imported', 1000);
        $this->assertDatabaseCount('students', 1000);
        $this->assertSame(1000, Student::whereNull('section_id')->whereNull('academic_year_id')->whereNotNull('created_at')->count());
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    public function test_a_constraint_failure_in_a_later_batch_rolls_back_all_new_rows(): void
    {
        $this->admin();
        $csv = self::HEADER.implode("\n", array_map(fn ($i) => "TX-{$i},Nombre,Apellido", range(1, 251)));
        $analysis = (new ImportStudentsFromCsv)->analyze(UploadedFile::fake()->createWithContent('estudiantes.csv', $csv));
        // Simulate a conflicting insert after validation, before the second batch is written.
        Student::create(['enrollment_no' => 'TX-251', 'name' => 'Otro', 'last_name' => 'Registro', 'active' => true]);
        $this->partialMock(ImportStudentsFromCsv::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->andReturn($analysis));
        $this->upload($csv, false)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseMissing('students', ['enrollment_no' => 'TX-1']);
        $this->assertDatabaseCount('student_enrollments', 0);
    }

    public function test_only_active_administrators_can_preview_or_import(): void
    {
        foreach ([true, false] as $preview) $this->upload(self::HEADER.'1,Ana,Pérez', $preview)->assertUnauthorized();
        foreach ([['teacher', true], ['coordinator', true], ['admin', false]] as [$role, $active]) {
            Sanctum::actingAs(User::factory()->create(['role' => $role, 'active' => $active]));
            foreach ([true, false] as $preview) $this->upload(self::HEADER.'1,Ana,Pérez', $preview)->assertForbidden();
        }
        $this->assertDatabaseCount('students', 0);
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'active' => true]));
    }

    private function upload(string $csv, bool $preview = true): TestResponse
    {
        return $this->post('/api/admin/students/import'.($preview ? '/preview' : ''), [
            'file' => UploadedFile::fake()->createWithContent('estudiantes.csv', $csv),
        ], ['Accept' => 'application/json']);
    }

    private function previewRow(int $line, string $number, string $name, string $surname): array
    {
        return ['row_number' => $line, 'data' => ['enrollment_no' => $number, 'name' => $name, 'last_name' => $surname],
            'errors' => [], 'section_id' => null, 'academic_year_id' => null, 'section_label' => 'Pendiente de asignación', 'valid' => true];
    }
}
