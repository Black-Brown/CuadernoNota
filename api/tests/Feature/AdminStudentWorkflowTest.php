<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStudentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enroll_approve_and_promote_a_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        $yearId = $this->year('2026-2027', '2026-08-01', '2027-06-30');
        $nextYearId = $this->year('2027-2028', '2027-08-01', '2028-06-30');
        $gradeId = DB::table('grades')->insertGetId(['name' => '4to Primaria', 'level' => 'Primaria', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()]);
        $nextGradeId = DB::table('grades')->insertGetId(['name' => '5to Primaria', 'level' => 'Primaria', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()]);
        $sectionId = $this->section($gradeId, $yearId);
        $destinationId = $this->section($nextGradeId, $nextYearId);
        $periodId = DB::table('periods')->insertGetId([
            'academic_year_id' => $yearId, 'number' => 1, 'name' => 'Primer período', 'months' => 'Ago-Oct',
            'start_date' => '2026-08-01', 'end_date' => '2026-10-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $secondPeriodId = DB::table('periods')->insertGetId([
            'academic_year_id' => $yearId, 'number' => 2, 'name' => 'Segundo período', 'months' => 'Nov-Ene',
            'start_date' => '2026-11-01', 'end_date' => '2027-01-31', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Robótica', 'code' => 'ROB', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('grade_subjects')->insert(['grade_id' => $gradeId, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);

        $studentId = $this->postJson('/api/admin/students', [
            'name' => 'Ana', 'last_name' => 'Pérez', 'enrollment_no' => '2026-001',
            'section_id' => $sectionId, 'enrolled_at' => '2026-08-01',
        ])->assertCreated()->json('id');
        $this->getJson('/api/admin/students')->assertOk()
            ->assertJsonPath('data.0.section.academic_year.name', '2026-2027');
        $enrollmentId = DB::table('student_enrollments')->where('student_id', $studentId)->value('id');

        DB::table('period_grades')->insert([
            'student_id' => $studentId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'period_id' => $periodId,
            'c1_score' => 85, 'c2_score' => 90, 'c3_score' => 80, 'period_score' => 85,
            'status' => 'in_review', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/admin/grade-reviews/decision', [
            'section_id' => $sectionId, 'subject_id' => $subjectId,
            'period_id' => $periodId, 'action' => 'approved',
        ])->assertOk()->assertJsonPath('updated_grades', 1);
        $this->assertDatabaseHas('period_grades', ['student_id' => $studentId, 'status' => 'official', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('grade_review_actions', ['section_id' => $sectionId, 'action' => 'approved', 'performed_by' => $admin->id]);

        DB::table('final_grades')->insert([
            'student_id' => $studentId, 'subject_id' => $subjectId, 'academic_year_id' => $yearId,
            'cf' => 85, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('attendances')->insert([
            ['student_id' => $studentId, 'section_id' => $sectionId, 'user_id' => $admin->id,
             'date' => '2026-08-10', 'code' => 'P', 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => $studentId, 'section_id' => $sectionId, 'user_id' => $admin->id,
             'date' => '2026-08-11', 'code' => 'T', 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => $studentId, 'section_id' => $sectionId, 'user_id' => $admin->id,
             'date' => '2026-08-12', 'code' => 'A', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('observations')->insert([
            'student_id' => $studentId, 'user_id' => $admin->id, 'section_id' => $sectionId,
            'subject_id' => $subjectId, 'period_id' => $periodId, 'date' => '2026-08-11',
            'type' => 'academic', 'description' => 'Buen desempeño.', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('alerts')->insert([
            'student_id' => $studentId, 'type' => 'performance', 'message' => 'Seguimiento preventivo.',
            'resolved' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/api/admin/students/{$studentId}")->assertOk()
            ->assertJsonPath('summary.average', 85)
            ->assertJsonPath('summary.attendance_percentage', 66.7)
            ->assertJsonPath('summary.unresolved_alerts', 1)
            ->assertJsonPath('period_grades.0.subject.name', 'Robótica')
            ->assertJsonPath('observations.0.description', 'Buen desempeño.')
            ->assertJsonFragment(['code' => 'P']);
        $this->getJson("/api/admin/reports/attendance?academic_year_id={$yearId}")
            ->assertOk()
            ->assertJsonPath('0.records', 3)
            ->assertJsonPath('0.present', 2)
            ->assertJsonPath('0.late', 1);
        $this->getJson("/api/admin/promotions/candidates?academic_year_id={$yearId}")
            ->assertOk()->assertJsonFragment(['student_id' => $studentId, 'eligible' => true, 'promotion_open' => false]);

        $this->postJson("/api/admin/promotions/{$enrollmentId}/decision", [
            'status' => 'promoted', 'target_grade_id' => $nextGradeId,
        ])->assertUnprocessable()->assertJsonValidationErrors('academic_year');
        $this->assertDatabaseHas('students', ['id' => $studentId, 'section_id' => $sectionId, 'academic_year_id' => $yearId]);

        DB::table('periods')->where('id', $periodId)->update(['status' => 'closed']);

        $this->postJson("/api/admin/promotions/{$enrollmentId}/decision", [
            'status' => 'promoted', 'target_grade_id' => $nextGradeId,
        ])->assertOk()->assertJsonPath('status', 'promoted');
        $this->assertDatabaseHas('students', ['id' => $studentId, 'section_id' => null, 'academic_year_id' => null, 'active' => true]);
        $this->assertDatabaseHas('promotion_decisions', ['student_enrollment_id' => $enrollmentId, 'target_grade_id' => $nextGradeId, 'placement_status' => 'pending']);
        $this->getJson('/api/admin/student-placements/pending')->assertOk()
            ->assertJsonPath('0.id', $studentId)->assertJsonPath('0.placement_reason', 'Promovido')
            ->assertJsonPath('0.target_grade_name', '5to Primaria');
        $this->postJson('/api/admin/student-placements', [
            'student_ids' => [$studentId], 'section_id' => $destinationId, 'enrolled_at' => '2026-07-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('enrolled_at');
        $farYearId = $this->year('2028-2029', '2028-08-01', '2029-06-30');
        $farSectionId = $this->section($nextGradeId, $farYearId);
        $this->postJson('/api/admin/student-placements', [
            'student_ids' => [$studentId], 'section_id' => $farSectionId, 'enrolled_at' => '2028-08-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('section_id');
        $this->postJson('/api/admin/student-placements', [
            'student_ids' => [$studentId], 'section_id' => $destinationId, 'enrolled_at' => '2027-08-01',
        ])->assertCreated()->assertJsonPath('assigned', 1);
        $this->assertDatabaseHas('students', ['id' => $studentId, 'section_id' => $destinationId, 'academic_year_id' => $nextYearId]);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $studentId, 'section_id' => $destinationId, 'status' => 'active']);
        $this->getJson("/api/admin/grade-reviews/{$sectionId}/{$subjectId}/{$periodId}")
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.student_id', $studentId);
        DB::table('period_grades')->insert([
            'student_id' => $studentId, 'section_id' => $sectionId, 'subject_id' => $subjectId, 'period_id' => $secondPeriodId,
            'c1_score' => 60, 'c2_score' => 60, 'c3_score' => 60, 'period_score' => 60,
            'status' => 'official', 'approved_by' => $admin->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson("/api/admin/reports/academic?academic_year_id={$yearId}")
            ->assertOk()
            ->assertJsonPath('summary.evaluated_students', 1)
            ->assertJsonPath('summary.at_risk_students', 1)
            ->assertJsonPath('summary.overall_average', 72.5)
            ->assertJsonPath('rows.0.grade', '4to Primaria')->assertJsonPath('rows.0.section', 'A');
        $this->getJson("/api/admin/promotions/candidates?academic_year_id={$yearId}&section_id={$sectionId}")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.section_id', $sectionId)
            ->assertJsonPath('0.section_name', 'A')
            ->assertJsonPath('0.academic_year_name', '2026-2027')
            ->assertJsonPath('0.decision.status', 'promoted')
            ->assertJsonPath('0.decision.placement_status', 'assigned')
            ->assertJsonPath('0.decision.destination_section.id', $destinationId);
    }

    public function test_admin_can_preview_and_import_students_from_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $yearId = $this->year('2025-2026', '2025-08-01', '2026-06-30');
        $gradeId = DB::table('grades')->insertGetId([
            'name' => '1RO SECUNDARIA', 'level' => 'Secundaria', 'sort_order' => 1,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sectionId = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A',
            'shift' => 'Matutina', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invalidCsv = implode("\n", [
            'MATRICULA,NOMBRES,APELLIDOS,ANO_ESCOLAR,GRADO,SECCION,TANDA,FECHA_INSCRIPCION',
            'FUERA-001,Fecha,Fuera,2025-2026,1RO SECUNDARIA,A,Matutina,2026-07-15',
        ]);
        $this->postJson('/api/admin/students/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('fuera.csv', $invalidCsv),
        ])->assertOk()
            ->assertJsonPath('summary.invalid', 1)
            ->assertJsonPath('rows.0.valid', false);
        $csv = implode("\n", [
            'MATRICULA,NOMBRES,APELLIDOS,ANO_ESCOLAR,GRADO,SECCION,TANDA,FECHA_INSCRIPCION,NOMBRE_TUTOR',
            '2025-1SA-0001,Ana María,Pérez Soto,2025-2026,1RO SECUNDARIA,A,Matutina,2025-08-01,',
            '2025-1SA-0002,Juan,De la Cruz,2025-2026,1RO SECUNDARIA,A,Matutina,2025-08-01,',
        ]);

        $this->postJson('/api/admin/students/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('estudiantes.csv', $csv),
        ])->assertOk()
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.valid', 2)
            ->assertJsonPath('summary.invalid', 0)
            ->assertJsonPath('rows.0.section_id', $sectionId)
            ->assertJsonPath('rows.0.data.name', 'Ana María');

        $this->postJson('/api/admin/students/import', [
            'file' => UploadedFile::fake()->createWithContent('estudiantes.csv', $csv),
        ])->assertCreated()->assertJsonPath('imported', 2);

        $this->assertDatabaseHas('students', [
            'enrollment_no' => '2025-1SA-0001', 'name' => 'Ana María', 'last_name' => 'Pérez Soto',
            'section_id' => $sectionId, 'academic_year_id' => $yearId, 'active' => true,
        ]);
        $this->assertSame(2, DB::table('student_enrollments')->where('section_id', $sectionId)->where('status', 'active')->count());

        $this->postJson('/api/admin/students/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('estudiantes.csv', $csv),
        ])->assertOk()->assertJsonPath('summary.invalid', 2)
            ->assertJsonPath('rows.0.errors.0', 'La matrícula ya existe en el sistema.');
    }

    public function test_admin_can_register_a_student_without_section_and_assign_it_later(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $studentId = $this->postJson('/api/admin/students', [
            'name' => 'Nuevo', 'last_name' => 'Ingreso', 'enrollment_no' => 'NUEVO-001',
        ])->assertCreated()->assertJsonPath('section_id', null)->json('id');
        $this->assertDatabaseCount('student_enrollments', 0);
        $this->getJson('/api/admin/student-placements/pending')->assertOk()
            ->assertJsonPath('0.id', $studentId)->assertJsonPath('0.placement_reason', 'Nuevo ingreso');

        $yearId = $this->year('2030-2031', '2030-08-01', '2031-06-30');
        $gradeId = DB::table('grades')->insertGetId(['name' => 'Ingreso Fecha', 'level' => 'Primaria', 'sort_order' => 30, 'active' => true]);
        $sectionId = $this->section($gradeId, $yearId);
        $this->postJson("/api/admin/students/{$studentId}/enrollments", [
            'section_id' => $sectionId, 'enrolled_at' => '2031-07-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('enrolled_at');
    }

    public function test_admin_can_promote_multiple_students_from_one_course_atomically(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $yearId = $this->year('2031-2032', '2031-08-01', '2032-06-30');
        $this->closedPeriod($yearId, '2031-08-01', '2031-10-31');
        $gradeId = DB::table('grades')->insertGetId(['name' => '5to Beta', 'level' => 'Primaria', 'sort_order' => 5, 'active' => true]);
        $nextGradeId = DB::table('grades')->insertGetId(['name' => '6to Beta', 'level' => 'Primaria', 'sort_order' => 6, 'active' => true]);
        $sectionId = $this->section($gradeId, $yearId);
        $otherSectionId = DB::table('sections')->insertGetId(['grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'B', 'shift' => 'Matutina']);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Ciencias Beta', 'code' => 'CIE-BETA', 'active' => true]);
        DB::table('grade_subjects')->insert(['grade_id' => $gradeId, 'subject_id' => $subjectId]);

        $enrollmentIds = collect([
            ['Ana', 'López', 'BETA-001', $sectionId],
            ['Luis', 'Pérez', 'BETA-002', $sectionId],
            ['Eva', 'Díaz', 'BETA-003', $otherSectionId],
        ])->map(function (array $student) use ($yearId, $subjectId) {
            $studentId = DB::table('students')->insertGetId([
                'name' => $student[0], 'last_name' => $student[1], 'enrollment_no' => $student[2],
                'section_id' => $student[3], 'academic_year_id' => $yearId, 'active' => true,
            ]);
            DB::table('final_grades')->insert([
                'student_id' => $studentId, 'subject_id' => $subjectId, 'academic_year_id' => $yearId, 'cf' => 90,
            ]);

            return DB::table('student_enrollments')->insertGetId([
                'student_id' => $studentId, 'section_id' => $student[3], 'status' => 'active', 'enrolled_at' => '2031-08-01',
            ]);
        });

        $this->postJson('/api/admin/promotions/bulk-decision', [
            'enrollment_ids' => [$enrollmentIds[0], $enrollmentIds[2]], 'section_id' => $sectionId,
            'status' => 'promoted', 'target_grade_id' => $nextGradeId,
        ])->assertUnprocessable()->assertJsonValidationErrors('enrollment_ids');
        $this->assertDatabaseCount('promotion_decisions', 0);

        $this->postJson('/api/admin/promotions/bulk-decision', [
            'enrollment_ids' => [$enrollmentIds[0], $enrollmentIds[1]], 'section_id' => $sectionId,
            'status' => 'promoted', 'target_grade_id' => $nextGradeId,
        ])->assertOk()->assertJsonPath('processed', 2)->assertJsonCount(2, 'decisions');

        $this->assertSame(2, DB::table('promotion_decisions')->where('status', 'promoted')->where('placement_status', 'pending')->count());
        $this->assertSame(2, DB::table('student_enrollments')->whereIn('id', [$enrollmentIds[0], $enrollmentIds[1]])->where('status', 'completed')->count());
    }

    public function test_promotion_requires_consistent_target_and_justification_for_override(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $yearId = $this->year('2032-2033', '2032-08-01', '2033-06-30');
        $this->closedPeriod($yearId, '2032-08-01', '2032-10-31');
        $gradeId = DB::table('grades')->insertGetId(['name' => '7mo Beta', 'level' => 'Secundaria', 'sort_order' => 7, 'active' => true]);
        $nextGradeId = DB::table('grades')->insertGetId(['name' => '8vo Beta', 'level' => 'Secundaria', 'sort_order' => 8, 'active' => true]);
        $sectionId = $this->section($gradeId, $yearId);
        $subjectId = DB::table('subjects')->insertGetId(['name' => 'Lengua Beta', 'code' => 'LEN-BETA', 'active' => true]);
        DB::table('grade_subjects')->insert(['grade_id' => $gradeId, 'subject_id' => $subjectId]);
        $studentId = DB::table('students')->insertGetId(['name' => 'Rosa', 'last_name' => 'Gil', 'enrollment_no' => 'BETA-004', 'section_id' => $sectionId, 'academic_year_id' => $yearId, 'active' => true]);
        DB::table('final_grades')->insert(['student_id' => $studentId, 'subject_id' => $subjectId, 'academic_year_id' => $yearId, 'cf' => 95]);
        $enrollmentId = DB::table('student_enrollments')->insertGetId(['student_id' => $studentId, 'section_id' => $sectionId, 'status' => 'active', 'enrolled_at' => '2032-08-01']);

        $this->postJson("/api/admin/promotions/{$enrollmentId}/decision", [
            'status' => 'promoted', 'target_grade_id' => $gradeId,
        ])->assertUnprocessable()->assertJsonValidationErrors('target_grade_id');
        $this->postJson("/api/admin/promotions/{$enrollmentId}/decision", [
            'status' => 'not_promoted', 'target_grade_id' => $gradeId,
        ])->assertUnprocessable()->assertJsonValidationErrors('justification');
        $this->postJson("/api/admin/promotions/{$enrollmentId}/decision", [
            'status' => 'not_promoted', 'target_grade_id' => $gradeId, 'justification' => 'Decisión extraordinaria documentada.',
        ])->assertOk()->assertJsonPath('status', 'not_promoted');
        $this->assertDatabaseHas('promotion_decisions', ['student_enrollment_id' => $enrollmentId, 'target_grade_id' => $gradeId]);
        $this->assertDatabaseMissing('promotion_decisions', ['student_enrollment_id' => $enrollmentId, 'target_grade_id' => $nextGradeId]);
    }

    public function test_promotion_requires_grades_for_the_exact_subjects_assigned_to_the_grade(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $yearId = $this->year('2033-2034', '2033-08-01', '2034-06-30');
        $this->closedPeriod($yearId, '2033-08-01', '2033-10-31');
        $gradeId = DB::table('grades')->insertGetId(['name' => '8vo Exacto', 'level' => 'Secundaria', 'sort_order' => 8, 'active' => true]);
        DB::table('grades')->insert(['name' => '9no Exacto', 'level' => 'Secundaria', 'sort_order' => 9, 'active' => true]);
        $sectionId = $this->section($gradeId, $yearId);
        $requiredA = DB::table('subjects')->insertGetId(['name' => 'Materia requerida A', 'code' => 'REQ-A', 'active' => true]);
        $requiredB = DB::table('subjects')->insertGetId(['name' => 'Materia requerida B', 'code' => 'REQ-B', 'active' => true]);
        $unrelated = DB::table('subjects')->insertGetId(['name' => 'Materia no asignada', 'code' => 'EXTRA', 'active' => true]);
        DB::table('grade_subjects')->insert([
            ['grade_id' => $gradeId, 'subject_id' => $requiredA],
            ['grade_id' => $gradeId, 'subject_id' => $requiredB],
        ]);
        $studentId = DB::table('students')->insertGetId([
            'name' => 'Alumno', 'last_name' => 'Exacto', 'enrollment_no' => 'EXACTO-001',
            'section_id' => $sectionId, 'academic_year_id' => $yearId, 'active' => true,
        ]);
        DB::table('student_enrollments')->insert([
            'student_id' => $studentId, 'section_id' => $sectionId, 'status' => 'active', 'enrolled_at' => '2033-08-01',
        ]);
        DB::table('final_grades')->insert([
            ['student_id' => $studentId, 'subject_id' => $requiredA, 'academic_year_id' => $yearId, 'cf' => 90],
            ['student_id' => $studentId, 'subject_id' => $unrelated, 'academic_year_id' => $yearId, 'cf' => 95],
        ]);

        $this->getJson("/api/admin/promotions/candidates?academic_year_id={$yearId}")
            ->assertOk()
            ->assertJsonPath('0.expected_subject_count', 2)
            ->assertJsonPath('0.subject_count', 1)
            ->assertJsonPath('0.missing_subject_count', 1)
            ->assertJsonPath('0.eligible', false);

        DB::table('final_grades')->insert([
            'student_id' => $studentId, 'subject_id' => $requiredB, 'academic_year_id' => $yearId, 'cf' => 80,
        ]);

        $this->getJson("/api/admin/promotions/candidates?academic_year_id={$yearId}")
            ->assertOk()
            ->assertJsonPath('0.subject_count', 2)
            ->assertJsonPath('0.missing_subject_count', 0)
            ->assertJsonPath('0.eligible', true);
    }

    public function test_backup_excludes_authentication_credentials_and_google_identity(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'active' => true,
            'password' => 'secret-password',
            'google_id' => 'sensitive-google-identifier',
            'google_avatar' => 'https://example.test/private-avatar',
            'remember_token' => 'sensitive-remember-token',
        ]);
        Sanctum::actingAs($admin);

        $backup = json_decode($this->post('/api/admin/backups')->assertOk()->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $exportedUser = collect($backup['tables']['users'])->firstWhere('id', $admin->id);

        $this->assertSame($admin->email, $exportedUser['email']);
        $this->assertArrayNotHasKey('password', $exportedUser);
        $this->assertArrayNotHasKey('remember_token', $exportedUser);
        $this->assertArrayNotHasKey('google_id', $exportedUser);
        $this->assertArrayNotHasKey('google_avatar', $exportedUser);
    }

    private function year(string $name, string $start, string $end): int
    {
        return DB::table('academic_years')->insertGetId(['name' => $name, 'start_date' => $start, 'end_date' => $end, 'active' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function section(int $gradeId, int $yearId): int
    {
        return DB::table('sections')->insertGetId(['grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A', 'shift' => 'Matutina', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function closedPeriod(int $yearId, string $start, string $end): int
    {
        return DB::table('periods')->insertGetId([
            'academic_year_id' => $yearId, 'number' => 1, 'name' => 'Período cerrado', 'months' => 'Ago-Oct',
            'start_date' => $start, 'end_date' => $end, 'status' => 'closed',
        ]);
    }
}
