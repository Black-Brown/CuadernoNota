<?php

namespace Database\Seeders;

use App\Models\User;
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
            ['code' => 'LEN', 'grade_id' => $gradeId],
            [
                'name'       => 'Lengua Española',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $subjectId = DB::table('subjects')->where('code', 'LEN')->value('id');

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

        DB::table('teacher_sections')->updateOrInsert(
            [
                'user_id'          => $teacherId,
                'section_id'       => $sectionId,
                'subject_id'       => $subjectId,
                'academic_year_id' => $yearId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
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

        // ── 8. Actividades base ───────────────────────────────────────────────
        $baseActivities = [
            'Proyectos', 'Examen', 'Tareas', 'Ensayo',
            'Producción en aula', 'Diagnósticas',
        ];

        foreach ($baseActivities as $actName) {
            DB::table('activities')->updateOrInsert(
                ['name' => $actName, 'subject_id' => $subjectId, 'academic_year_id' => $yearId],
                [
                    'is_base'    => true,
                    'user_id'    => $teacherId,
                    'active'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
