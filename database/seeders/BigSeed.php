<?php

namespace Database\Seeders;

use App\Models\User;
use App\Application\Activity\EnsureDefaultCourseActivities;
use App\Infrastructure\Models\CourseOffering;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BigSeed extends Seeder
{
    private array $firstNames = [
        'Carlos', 'María', 'Ana', 'Juan', 'Luis', 'Pedro', 'Rosa', 'Carmen',
        'Roberto', 'Elena', 'Sofía', 'Diego', 'Valentina', 'Miguel', 'Isabella',
        'Andrés', 'Gabriela', 'Mateo', 'Paula', 'Emilio',
    ];

    private array $lastNames = [
        'García', 'Martínez', 'Rodríguez', 'López', 'González', 'Hernández',
        'Díaz', 'Pérez', 'Torres', 'Ramírez', 'Sánchez', 'Vargas',
        'Morales', 'Castro', 'Jiménez', 'Reyes', 'Cruz', 'Medina',
        'Flores', 'Guerrero',
    ];

    private array $obTexts = [
        'academic' => [
            'El estudiante muestra dificultades para comprender los conceptos fundamentales. Se recomienda tutorías adicionales.',
            'Excelente desempeño en las actividades de clase. Demuestra dominio del tema y apoya a sus compañeros.',
            'Ha mejorado notablemente su rendimiento en comparación con el período anterior.',
            'Presenta dificultades en la expresión escrita. Se sugiere refuerzo adicional en redacción.',
        ],
        'disciplinary' => [
            'Se registra llegada tarde en reiteradas ocasiones. Se notificará al representante legal.',
            'Comportamiento inadecuado durante la clase. Se tomaron medidas correctivas oportunas.',
            'Excelente comportamiento y respeto hacia sus compañeros y docentes. Es un ejemplo a seguir.',
        ],
        'incident' => [
            'El estudiante reportó un incidente durante el recreo. Se notificó a la coordinación académica.',
            'El estudiante presentó dificultades de salud durante la clase. Se contactó al representante.',
        ],
    ];

    // Enrollment prefix map (keeps enrollment_no globally unique)
    private array $prefixMap = [
        '7mo Grado|A'    => '7A',
        '7mo Grado|B'    => '7B',
        '8vo Grado|A'    => '8A',
        '8vo Grado|B'    => '8B',
        '9no Grado|A'    => '9A',
        '1ro Primaria|A' => 'P1A',
        '2do Primaria|A' => 'P2A',
        '3ro Primaria|A' => 'P3A',
    ];

    // 20 (c1,c2,c3) score patterns – ~25% below 70
    private array $scorePatterns = [
        [92, 88, 95], [85, 90, 87], [78, 82, 75], [95, 97, 93], [68, 65, 70],
        [55, 60, 58], [88, 84, 91], [73, 70, 76], [80, 83, 78], [91, 89, 94],
        [67, 63, 69], [84, 87, 82], [76, 79, 74], [98, 95, 97], [72, 68, 75],
        [82, 85, 80], [61, 58, 64], [90, 92, 88], [77, 74, 79], [86, 89, 83],
    ];

    public function run(): void
    {
        $yearId     = $this->seedUsersAndYear();
        $gradeIds   = $this->seedGrades();
        $sectionIds = $this->seedSections($gradeIds, $yearId);
        $subjectIds = $this->seedSubjects($gradeIds);
        $periodIds  = $this->seedPeriods($yearId);
        $tIds       = $this->getTeacherIds();

        $this->seedTeacherSections($yearId, $sectionIds, $subjectIds, $tIds);
        $studentMap = $this->seedStudents($yearId, $sectionIds);
        $actIds     = $this->seedActivities($yearId, $subjectIds, $tIds);

        $this->seedPeriodGrades($studentMap, $subjectIds, $periodIds);
        $this->seedActivityScores($studentMap, $subjectIds, $periodIds, $actIds);
        $this->seedFinalGrades($studentMap, $subjectIds, $yearId);
        $this->seedAttendances($studentMap, $sectionIds, $tIds);
        $this->seedObservations($studentMap, $tIds);
        $this->seedAlerts($studentMap);
        $this->seedAuditLogs($tIds);
        $this->seedStudentPromotions($studentMap, $yearId);
    }

    // ─── Users + Academic Year ──────────────────────────────────────────────

    private function seedUsersAndYear(): int
    {
        $users = [
            ['docente@demo.com',     'Prof. María Martínez',  'teacher'],
            ['profe2@demo.com',      'Prof. Juan García',      'teacher'],
            ['profe3@demo.com',      'Prof. Carmen López',     'teacher'],
            ['profe4@demo.com',      'Prof. Roberto Sánchez',  'teacher'],
            ['coordinador@demo.com', 'Lic. Ana Rodríguez',     'coordinator'],
            ['admin@demo.com',       'Administrador Sistema',  'admin'],
        ];

        foreach ($users as [$email, $name, $role]) {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'role' => $role, 'active' => true]
            );
        }

        DB::table('academic_years')->updateOrInsert(
            ['name' => '2025-2026'],
            ['start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'active' => true,
             'created_at' => now(), 'updated_at' => now()]
        );

        return DB::table('academic_years')->where('name', '2025-2026')->value('id');
    }

    // ─── Grades ────────────────────────────────────────────────────────────

    private function seedGrades(): array
    {
        $grades = [
            ['1ro Primaria', 'Primaria',   1],
            ['2do Primaria', 'Primaria',   2],
            ['3ro Primaria', 'Primaria',   3],
            ['7mo Grado',    'Secundaria', 7],
            ['8vo Grado',    'Secundaria', 8],
            ['9no Grado',    'Secundaria', 9],
        ];

        $ids = [];
        foreach ($grades as [$name, $level, $order]) {
            DB::table('grades')->updateOrInsert(
                ['name' => $name],
                ['level' => $level, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]
            );
            $ids[$name] = DB::table('grades')->where('name', $name)->value('id');
        }
        return $ids;
    }

    // ─── Sections ──────────────────────────────────────────────────────────

    private function seedSections(array $gradeIds, int $yearId): array
    {
        $sections = [
            ['7mo Grado',    'A', 'Matutina'],
            ['7mo Grado',    'B', 'Matutina'],
            ['8vo Grado',    'A', 'Matutina'],
            ['8vo Grado',    'B', 'Vespertina'],
            ['9no Grado',    'A', 'Matutina'],
            ['1ro Primaria', 'A', 'Matutina'],
            ['1ro Primaria', 'B', 'Vespertina'],
            ['2do Primaria', 'A', 'Matutina'],
            ['3ro Primaria', 'A', 'Matutina'],
        ];

        $ids = [];
        foreach ($sections as [$gradeName, $sName, $shift]) {
            if (!isset($gradeIds[$gradeName])) continue;
            DB::table('sections')->updateOrInsert(
                ['grade_id' => $gradeIds[$gradeName], 'academic_year_id' => $yearId, 'name' => $sName],
                ['shift' => $shift, 'created_at' => now(), 'updated_at' => now()]
            );
            $key = $gradeName . '|' . $sName;
            $ids[$key] = DB::table('sections')
                ->where('grade_id', $gradeIds[$gradeName])
                ->where('academic_year_id', $yearId)
                ->where('name', $sName)
                ->value('id');
        }
        return $ids;
    }

    // ─── Subjects ──────────────────────────────────────────────────────────

    private function seedSubjects(array $gradeIds): array
    {
        $subjects = [
            ['7mo Grado',    'MAT-7',  'Matemática'],
            ['7mo Grado',    'LEN-7',  'Lengua Española'],
            ['7mo Grado',    'ING-7',  'Inglés'],
            ['7mo Grado',    'CNA-7',  'Ciencias Naturales'],
            ['7mo Grado',    'CSO-7',  'Ciencias Sociales'],
            ['8vo Grado',    'MAT-8',  'Matemática'],
            ['8vo Grado',    'LEN-8',  'Lengua Española'],
            ['8vo Grado',    'ING-8',  'Inglés'],
            ['8vo Grado',    'CNA-8',  'Ciencias Naturales'],
            ['8vo Grado',    'CSO-8',  'Ciencias Sociales'],
            ['9no Grado',    'MAT-9',  'Matemática'],
            ['9no Grado',    'LEN-9',  'Lengua Española'],
            ['9no Grado',    'ING-9',  'Inglés'],
            ['9no Grado',    'CNA-9',  'Ciencias Naturales'],
            ['1ro Primaria', 'LEN',    'Lengua Española'],   // already in DemoSeeder
            ['1ro Primaria', 'MAT-P1', 'Matemática'],
            ['1ro Primaria', 'ING-P1', 'Inglés'],
            ['2do Primaria', 'LEN-2',  'Lengua Española'],
            ['2do Primaria', 'MAT-P2', 'Matemática'],
            ['3ro Primaria', 'LEN-3',  'Lengua Española'],
        ];

        $catalogCodes = [
            'Matemática' => 'MAT',
            'Lengua Española' => 'LEN',
            'Inglés' => 'ING',
            'Ciencias Naturales' => 'CNA',
            'Ciencias Sociales' => 'CSO',
        ];

        $ids = [];
        foreach ($subjects as [$gradeName, $code, $name]) {
            if (!isset($gradeIds[$gradeName])) continue;
            DB::table('subjects')->updateOrInsert(
                ['name' => $name],
                ['code' => $catalogCodes[$name], 'active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $subjectId = DB::table('subjects')->where('name', $name)->value('id');
            DB::table('grade_subjects')->updateOrInsert(
                ['grade_id' => $gradeIds[$gradeName], 'subject_id' => $subjectId],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $ids[$code] = $subjectId;
        }
        return $ids;
    }

    // ─── Periods ───────────────────────────────────────────────────────────

    private function seedPeriods(int $yearId): array
    {
        $periods = [
            [1, 'Primer Período',  'Sep-Nov', '2025-09-01', '2025-11-30', 'open'],
            [2, 'Segundo Período', 'Dic-Feb', '2025-12-01', '2026-02-28', 'closed'],
            [3, 'Tercer Período',  'Mar-Abr', '2026-03-01', '2026-04-30', 'closed'],
            [4, 'Cuarto Período',  'May-Jun', '2026-05-01', '2026-06-30', 'open'],
        ];

        $ids = [];
        foreach ($periods as [$num, $name, $months, $start, $end, $status]) {
            DB::table('periods')->updateOrInsert(
                ['academic_year_id' => $yearId, 'number' => $num],
                ['name' => $name, 'months' => $months, 'start_date' => $start,
                 'end_date' => $end, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]
            );
            $ids[$num] = DB::table('periods')
                ->where('academic_year_id', $yearId)->where('number', $num)->value('id');
        }
        return $ids;
    }

    // ─── Teacher IDs ───────────────────────────────────────────────────────

    private function getTeacherIds(): array
    {
        return [
            'main' => DB::table('users')->where('email', 'docente@demo.com')->value('id'),
            'p2'   => DB::table('users')->where('email', 'profe2@demo.com')->value('id'),
            'p3'   => DB::table('users')->where('email', 'profe3@demo.com')->value('id'),
            'p4'   => DB::table('users')->where('email', 'profe4@demo.com')->value('id'),
        ];
    }

    // ─── Teacher Sections ──────────────────────────────────────────────────

    private function seedTeacherSections(int $yearId, array $sids, array $subIds, array $tIds): void
    {
        // Main teacher gets 3 courses visible in the Courses page
        $assignments = [
            [$tIds['main'], '7mo Grado|A', 'MAT-7'],
            [$tIds['main'], '7mo Grado|B', 'MAT-7'],
            [$tIds['main'], '8vo Grado|A', 'CNA-8'],
            [$tIds['p2'],   '7mo Grado|A', 'LEN-7'],
            [$tIds['p2'],   '7mo Grado|B', 'LEN-7'],
            [$tIds['p2'],   '8vo Grado|A', 'MAT-8'],
            [$tIds['p3'],   '7mo Grado|A', 'ING-7'],
            [$tIds['p3'],   '8vo Grado|A', 'ING-8'],
            [$tIds['p3'],   '9no Grado|A', 'ING-9'],
            [$tIds['p4'],   '8vo Grado|B', 'MAT-8'],
            [$tIds['p4'],   '9no Grado|A', 'MAT-9'],
            [$tIds['p4'],   '2do Primaria|A', 'MAT-P2'],
        ];

        foreach ($assignments as [$uid, $sk, $sc]) {
            if (!isset($sids[$sk], $subIds[$sc])) continue;
            DB::table('course_offerings')->updateOrInsert(
                ['section_id' => $sids[$sk], 'subject_id' => $subIds[$sc]],
                ['active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $offeringId = DB::table('course_offerings')
                ->where('section_id', $sids[$sk])
                ->where('subject_id', $subIds[$sc])
                ->value('id');
            DB::table('teacher_assignments')->updateOrInsert(
                ['teacher_id' => $uid, 'course_offering_id' => $offeringId],
                ['assigned_by' => null, 'assigned_at' => now(), 'active' => true,
                 'created_at' => now(), 'updated_at' => now()]
            );
            app(EnsureDefaultCourseActivities::class)->execute(
                CourseOffering::findOrFail($offeringId),
                $uid,
            );
        }
    }

    // ─── Students ──────────────────────────────────────────────────────────

    private function seedStudents(int $yearId, array $sids): array
    {
        $targetSections = array_keys($this->prefixMap);
        $map = [];

        foreach ($targetSections as $sk) {
            if (!isset($sids[$sk])) continue;
            $sectionId = $sids[$sk];
            $prefix    = $this->prefixMap[$sk];
            $map[$sk]  = [];

            for ($i = 1; $i <= 20; $i++) {
                $fn = $this->firstNames[($i - 1) % count($this->firstNames)];
                $ln = $this->lastNames[($i - 1) % count($this->lastNames)];
                $en = '2025-' . $prefix . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);

                DB::table('students')->updateOrInsert(
                    ['enrollment_no' => $en],
                    ['name' => $fn, 'last_name' => $ln, 'section_id' => $sectionId,
                     'academic_year_id' => $yearId, 'active' => true,
                     'created_at' => now(), 'updated_at' => now()]
                );
                $map[$sk][] = DB::table('students')->where('enrollment_no', $en)->value('id');
            }
        }
        return $map;
    }

    // ─── Activities ────────────────────────────────────────────────────────

    private function seedActivities(int $yearId, array $subIds, array $tIds): array
    {
        $baseNames = ['Proyectos', 'Examen', 'Tareas', 'Ensayo', 'Producción en aula', 'Diagnósticas'];
        $targets   = ['MAT-7', 'CNA-8', 'LEN-7', 'ING-7', 'MAT-8', 'LEN', 'MAT-P1', 'MAT-P2'];

        $actIds = [];
        foreach ($targets as $code) {
            if (!isset($subIds[$code])) continue;
            $sid = $subIds[$code];
            $actIds[$code] = [];
            foreach ($baseNames as $name) {
                $actIds[$code][$name] = DB::table('activities')
                    ->where('name', $name)->where('subject_id', $sid)
                    ->where('academic_year_id', $yearId)->value('id');
            }
        }
        return $actIds;
    }

    // ─── Period Grades ─────────────────────────────────────────────────────

    private function seedPeriodGrades(array $studentMap, array $subIds, array $periodIds): void
    {
        $courseMap = [
            '7mo Grado|A' => 'MAT-7',
            '7mo Grado|B' => 'MAT-7',
            '8vo Grado|A' => 'CNA-8',
        ];

        $p1 = $periodIds[1];

        foreach ($courseMap as $sk => $subCode) {
            if (!isset($studentMap[$sk], $subIds[$subCode])) continue;
            $subId = $subIds[$subCode];

            foreach ($studentMap[$sk] as $idx => $studId) {
                [$c1, $c2, $c3] = $this->scorePatterns[$idx % count($this->scorePatterns)];
                $ps  = round(($c1 + $c2 + $c3) / 3, 2);
                $rp  = $ps < 70 ? min(74.0, round($ps + rand(5, 14), 2)) : null;

                DB::table('period_grades')->updateOrInsert(
                    ['student_id' => $studId, 'subject_id' => $subId, 'period_id' => $p1],
                    ['c1_score' => $c1, 'c2_score' => $c2, 'c3_score' => $c3,
                     'period_score' => $ps, 'rp_score' => $rp, 'status' => 'official',
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    // ─── Activity Scores ───────────────────────────────────────────────────

    private function seedActivityScores(
        array $studentMap, array $subIds, array $periodIds, array $actIds
    ): void {
        $courseMap = [
            '7mo Grado|A' => 'MAT-7',
            '7mo Grado|B' => 'MAT-7',
            '8vo Grado|A' => 'CNA-8',
        ];

        $p1 = $periodIds[1];
        // Seed scores for "Proyectos" and "Examen" activities (competencies 1, 2, 3)
        $targetActivities = ['Proyectos', 'Examen'];

        foreach ($courseMap as $sk => $subCode) {
            if (!isset($studentMap[$sk], $subIds[$subCode], $actIds[$subCode])) continue;
            $subId = $subIds[$subCode];

            foreach ($targetActivities as $actName) {
                $actId = $actIds[$subCode][$actName] ?? null;
                if (!$actId) continue;

                foreach ($studentMap[$sk] as $idx => $studId) {
                    [$c1, $c2, $c3] = $this->scorePatterns[$idx % count($this->scorePatterns)];
                    $scores = [1 => $c1, 2 => $c2, 3 => $c3];

                    foreach ($scores as $compId => $score) {
                        $exists = DB::table('activity_scores')
                            ->where('activity_id', $actId)
                            ->where('student_id', $studId)
                            ->where('competency_id', $compId)
                            ->where('period_id', $p1)
                            ->where('subject_id', $subId)
                            ->exists();

                        if (!$exists) {
                            DB::table('activity_scores')->insert([
                                'activity_id'   => $actId,
                                'student_id'    => $studId,
                                'competency_id' => $compId,
                                'period_id'     => $p1,
                                'subject_id'    => $subId,
                                'score'         => $score,
                                'created_at'    => now(),
                                'updated_at'    => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    // ─── Final Grades ──────────────────────────────────────────────────────

    private function seedFinalGrades(array $studentMap, array $subIds, int $yearId): void
    {
        $courseMap = [
            '7mo Grado|A' => 'MAT-7',
            '7mo Grado|B' => 'MAT-7',
            '8vo Grado|A' => 'CNA-8',
        ];
        $cfValues = [4, 5, 5, 4, 5, 3, 5, 4, 5, 5, 3, 4, 5, 5, 4, 5, 3, 5, 4, 5];

        foreach ($courseMap as $sk => $subCode) {
            if (!isset($studentMap[$sk], $subIds[$subCode])) continue;
            $subId = $subIds[$subCode];

            foreach ($studentMap[$sk] as $idx => $studId) {
                DB::table('final_grades')->updateOrInsert(
                    ['student_id' => $studId, 'subject_id' => $subId, 'academic_year_id' => $yearId],
                    ['cf' => $cfValues[$idx % count($cfValues)],
                     'final_recovery' => null, 'special_recovery' => null,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    // ─── Attendances ───────────────────────────────────────────────────────

    private function seedAttendances(array $studentMap, array $sids, array $tIds): void
    {
        $days = $this->getRecentWorkingDays(20);
        $teacherSections = ['7mo Grado|A', '7mo Grado|B', '8vo Grado|A'];

        // Weighted pool: 85% P, 8% A, 5% T, 2% E
        $pool = array_merge(
            array_fill(0, 85, 'P'),
            array_fill(0, 8,  'A'),
            array_fill(0, 5,  'T'),
            array_fill(0, 2,  'E')
        );

        srand(42);

        foreach ($teacherSections as $sk) {
            if (!isset($studentMap[$sk], $sids[$sk])) continue;
            $sectionId = $sids[$sk];

            foreach ($days as $date) {
                foreach ($studentMap[$sk] as $studId) {
                    $exists = DB::table('attendances')
                        ->where('student_id', $studId)->where('date', $date)->exists();

                    if (!$exists) {
                        DB::table('attendances')->insert([
                            'student_id' => $studId,
                            'section_id' => $sectionId,
                            'user_id'    => $tIds['main'],
                            'date'       => $date,
                            'code'       => $pool[array_rand($pool)],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    // ─── Observations ──────────────────────────────────────────────────────

    private function seedObservations(array $studentMap, array $tIds): void
    {
        $teacherSections = ['7mo Grado|A', '7mo Grado|B', '8vo Grado|A'];
        $types = array_keys($this->obTexts);

        foreach ($teacherSections as $sk) {
            if (!isset($studentMap[$sk])) continue;
            foreach (array_slice($studentMap[$sk], 0, 8) as $studId) {
                $type = $types[array_rand($types)];
                $texts = $this->obTexts[$type];
                $exists = DB::table('observations')
                    ->where('student_id', $studId)->where('user_id', $tIds['main'])->exists();

                if (!$exists) {
                    DB::table('observations')->insert([
                        'student_id'  => $studId,
                        'user_id'     => $tIds['main'],
                        'date'        => now()->subDays(rand(1, 45))->format('Y-m-d'),
                        'type'        => $type,
                        'description' => $texts[array_rand($texts)],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }
    }

    // ─── Alerts ────────────────────────────────────────────────────────────

    private function seedAlerts(array $studentMap): void
    {
        $msgMap = [
            'consecutive_absences' => 'El estudiante ha registrado más de 3 ausencias consecutivas. Requiere atención.',
            'attendance_percentage' => 'El porcentaje de asistencia está por debajo del 85% requerido.',
            'performance'           => 'El rendimiento académico está por debajo del mínimo de aprobación (70 puntos).',
        ];
        $types = array_keys($msgMap);

        foreach (['7mo Grado|A', '7mo Grado|B', '8vo Grado|A'] as $sk) {
            if (!isset($studentMap[$sk])) continue;
            // Indices 4 & 5 have low scores (68/65/70 and 55/60/58)
            foreach (array_slice($studentMap[$sk], 4, 3) as $studId) {
                $type = $types[array_rand($types)];
                $exists = DB::table('alerts')
                    ->where('student_id', $studId)->where('type', $type)->exists();
                if (!$exists) {
                    DB::table('alerts')->insert([
                        'student_id' => $studId,
                        'type'       => $type,
                        'message'    => $msgMap[$type],
                        'resolved'   => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    // ─── Audit Logs ────────────────────────────────────────────────────────

    private function seedAuditLogs(array $tIds): void
    {
        if (DB::table('audit_logs')->count() > 0) return;

        $entries = [
            [$tIds['main'], 'login',               'users',         1],
            [$tIds['main'], 'grade_update',         'period_grades', 5],
            [$tIds['main'], 'grade_update',         'period_grades', 8],
            [$tIds['main'], 'attendance_register',  'attendances',  12],
            [$tIds['main'], 'observation_create',   'observations',  3],
            [$tIds['p2'],   'login',                'users',         2],
            [$tIds['p2'],   'grade_update',         'period_grades', 20],
            [$tIds['p2'],   'attendance_register',  'attendances',  22],
            [$tIds['main'], 'login',                'users',         1],
            [$tIds['main'], 'observation_create',   'observations',  5],
            [$tIds['main'], 'grade_update',         'period_grades', 30],
            [$tIds['p3'],   'login',                'users',         3],
        ];

        foreach ($entries as $i => [$uid, $action, $table, $recId]) {
            DB::table('audit_logs')->insert([
                'user_id'        => $uid,
                'action'         => $action,
                'affected_table' => $table,
                'record_id'      => $recId,
                'detail'         => json_encode(['seed' => true, 'entry' => $i + 1]),
                'ip'             => '192.168.1.' . (100 + $i),
                'created_at'     => now()->subMinutes(($i + 1) * 25),
            ]);
        }
    }

    // ─── Student Promotions ────────────────────────────────────────────────

    private function seedStudentPromotions(array $studentMap, int $yearId): void
    {
        $section = $studentMap['7mo Grado|A'] ?? [];
        foreach (array_slice($section, 0, 8) as $studId) {
            DB::table('student_promotions')->updateOrInsert(
                ['student_id' => $studId, 'academic_year_id' => $yearId],
                ['promoted_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function getRecentWorkingDays(int $count): array
    {
        $days = [];
        $date = now()->copy()->subDay();
        while (count($days) < $count) {
            if (!in_array($date->dayOfWeek, [0, 6])) {
                $days[] = $date->format('Y-m-d');
            }
            $date->subDay();
        }
        return array_reverse($days);
    }
}
