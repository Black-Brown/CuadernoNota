<?php

namespace Database\Seeders;

use App\Models\User;
use App\Application\Activity\EnsureDefaultCourseActivities;
use App\Infrastructure\Models\CourseOffering;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Datos mínimos para probar el sistema.
 * Usa updateOrCreate/updateOrInsert para ser idempotente.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── 0. Usuario docente de prueba ──────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'docente@demo.com'],
            [
                'name'     => 'Prof. María Martínez',
                'password' => Hash::make('password'),
                'role'     => 'teacher',
                'active'   => true,
            ]
        );

        // ── 1. Año escolar ────────────────────────────────────────────────────
        DB::table('academic_years')->updateOrInsert(
            ['name' => '2025-2026'],
            [
                'start_date' => '2025-09-01',
                'end_date'   => '2026-06-30',
                'active'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $yearId = DB::table('academic_years')->where('name', '2025-2026')->value('id');

        // ── 2. Grado ──────────────────────────────────────────────────────────
        DB::table('grades')->updateOrInsert(
            ['name' => '1ro Primaria'],
            [
                'level'      => 'Primaria',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $gradeId = DB::table('grades')->where('name', '1ro Primaria')->value('id');

        // ── 3. Sección ────────────────────────────────────────────────────────
        DB::table('sections')->updateOrInsert(
            ['grade_id' => $gradeId, 'academic_year_id' => $yearId, 'name' => 'A'],
            [
                'shift'      => 'Matutina',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $sectionId = DB::table('sections')
            ->where('grade_id', $gradeId)
            ->where('academic_year_id', $yearId)
            ->where('name', 'A')
            ->value('id');

        // ── 4. Asignatura ─────────────────────────────────────────────────────
        DB::table('subjects')->updateOrInsert(
            ['code' => 'LEN'],
            [
                'name'       => 'Lengua Española',
                'active'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $subjectId = DB::table('subjects')->where('code', 'LEN')->value('id');
        DB::table('grade_subjects')->updateOrInsert(
            ['grade_id' => $gradeId, 'subject_id' => $subjectId],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // ── 5. Períodos (1 a 4) ───────────────────────────────────────────────
        $periods = [
            ['number' => 1, 'name' => 'Primer Período',  'months' => 'Sep-Nov',
             'start_date' => '2025-09-01', 'end_date' => '2025-11-30'],
            ['number' => 2, 'name' => 'Segundo Período', 'months' => 'Dic-Feb',
             'start_date' => '2025-12-01', 'end_date' => '2026-02-28'],
            ['number' => 3, 'name' => 'Tercer Período',  'months' => 'Mar-Abr',
             'start_date' => '2026-03-01', 'end_date' => '2026-04-30'],
            ['number' => 4, 'name' => 'Cuarto Período',  'months' => 'May-Jun',
             'start_date' => '2026-05-01', 'end_date' => '2026-06-30'],
        ];

        foreach ($periods as $p) {
            DB::table('periods')->updateOrInsert(
                ['academic_year_id' => $yearId, 'number' => $p['number']],
                [
                    'name'       => $p['name'],
                    'months'     => $p['months'],
                    'start_date' => $p['start_date'],
                    'end_date'   => $p['end_date'],
                    'status'     => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // ── 6. Docente asignado a la sección ──────────────────────────────────
        $teacherId = User::where('email', 'docente@demo.com')->value('id');

        DB::table('course_offerings')->updateOrInsert(
            ['section_id' => $sectionId, 'subject_id' => $subjectId],
            ['active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        $offeringId = DB::table('course_offerings')
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->value('id');
        DB::table('teacher_assignments')->updateOrInsert(
            ['teacher_id' => $teacherId, 'course_offering_id' => $offeringId],
            ['assigned_by' => null, 'assigned_at' => now(), 'active' => true,
             'created_at' => now(), 'updated_at' => now()]
        );

        // ── 7. Estudiantes ────────────────────────────────────────────────────
        $students = [
            ['name' => 'María',  'last_name' => 'González', 'enrollment_no' => '2025-001'],
            ['name' => 'Carlos', 'last_name' => 'Pérez',    'enrollment_no' => '2025-002'],
            ['name' => 'Luisa',  'last_name' => 'Martínez', 'enrollment_no' => '2025-003'],
        ];

        foreach ($students as $s) {
            DB::table('students')->updateOrInsert(
                ['enrollment_no' => $s['enrollment_no']],
                [
                    'name'             => $s['name'],
                    'last_name'        => $s['last_name'],
                    'section_id'       => $sectionId,
                    'academic_year_id' => $yearId,
                    'active'           => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );
        }

        // ── 8. Actividades base por período ───────────────────────────────────
        app(EnsureDefaultCourseActivities::class)->execute(
            CourseOffering::findOrFail($offeringId),
            $teacherId,
        );
    }
}
