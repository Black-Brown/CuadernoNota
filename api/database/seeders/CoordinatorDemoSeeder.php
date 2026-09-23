<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CoordinatorDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection();
        if (! app()->environment('local') || $connection->getDriverName() !== 'pgsql'
            || ! in_array($connection->getConfig('host'), ['db', 'localhost', '127.0.0.1'], true)
            || $connection->getDatabaseName() !== 'cuaderno_nota') {
            throw new RuntimeException('Demo permitida únicamente en PostgreSQL local cuaderno_nota.');
        }
        DB::transaction(function () {
            $year = DB::table('academic_years')->where('name', '2026-2027')->first();
            if (! $year) {
                throw new RuntimeException('Configura primero el ciclo local 2026-2027.');
            }
            $period = DB::table('periods')->where('academic_year_id', $year->id)->orderBy('number')->first();
            if (! $period) {
                throw new RuntimeException('Configura al menos un período del ciclo antes de cargar la demo.');
            }
            $coordinator = User::firstOrCreate(['email' => 'coordinador.prueba@example.test'], [
                'name' => 'Coordinador DEMO', 'password' => Hash::make('CoordDemo2026!'), 'role' => 'coordinator', 'active' => true,
            ]);
            $teacher = User::firstOrCreate(['email' => 'docente.coordinacion@example.test'], [
                'name' => 'Docente DEMO Coordinación', 'password' => Hash::make('DocDemo2026!'), 'role' => 'teacher', 'active' => true,
            ]);
            if ($coordinator->role !== 'coordinator' || $teacher->role !== 'teacher') {
                throw new RuntimeException('Las cuentas demo existentes tienen otro rol; no se modificarán.');
            }
            $grade = $this->ensure('grades', ['name' => '1RO SECUNDARIA · DEMO COORD'], ['level' => 'Secundaria', 'sort_order' => 901, 'active' => true]);
            $subjects = [];
            foreach (['DC-LEN' => 'Lengua · DEMO COORD', 'DC-MAT' => 'Matemática · DEMO COORD'] as $code => $name) {
                $subject = $this->ensure('subjects', ['code' => $code], ['name' => $name, 'active' => true]);
                if (! DB::table('grade_subjects')->where('grade_id', $grade)->where('subject_id', $subject)->exists()) {
                    DB::table('grade_subjects')->insert(['grade_id' => $grade, 'subject_id' => $subject]);
                }
                $subjects[] = $subject;
            }
            foreach (['A', 'B', 'C'] as $sectionName) {
                $section = $this->ensure('sections', ['grade_id' => $grade, 'academic_year_id' => $year->id, 'name' => $sectionName, 'shift' => 'Matutina']);
                if ($sectionName !== 'C' && ! DB::table('coordinator_sections')->where('user_id', $coordinator->id)->where('section_id', $section)->exists()) {
                    DB::table('coordinator_sections')->insert(['user_id' => $coordinator->id, 'section_id' => $section, 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($subjects as $subject) {
                    $offering = $this->ensure('course_offerings', ['section_id' => $section, 'subject_id' => $subject], ['active' => true]);
                    $this->ensure('teacher_assignments', ['teacher_id' => $teacher->id, 'course_offering_id' => $offering], ['active' => true, 'assigned_by' => $coordinator->id, 'assigned_at' => now()]);
                }
                for ($i = 1; $i <= 6; $i++) {
                    $student = $this->ensure('students', ['enrollment_no' => "DEMO-COORD-26-{$sectionName}-{$i}"], [
                        'name' => ['Ana', 'Luis', 'María', 'Carlos', 'Sofía', 'Pedro'][$i - 1], 'last_name' => "Prueba {$sectionName}{$i}",
                        'active' => true, 'section_id' => $section, 'academic_year_id' => $year->id,
                    ]);
                    $this->ensure('student_enrollments', ['student_id' => $student, 'section_id' => $section], ['status' => 'active', 'enrolled_at' => $year->start_date, 'created_by' => $coordinator->id]);
                    foreach ($subjects as $index => $subject) {
                        $score = $i === 1 ? 60 : 72 + $i * 3;
                        $this->ensure('period_grades', ['student_id' => $student, 'subject_id' => $subject, 'period_id' => $period->id], [
                            'section_id' => $section, 'c1_score' => $score, 'c2_score' => $score, 'c3_score' => $score, 'period_score' => $score,
                            'status' => $index === 0 ? 'in_review' : 'official',
                        ]);
                        for ($day = 0; $day < 3; $day++) {
                            $date = \Carbon\Carbon::parse($period->start_date)->addDays($day)->toDateString();
                            if ($date > $period->end_date) { continue; }
                            $this->ensure('attendances', ['student_id' => $student, 'subject_id' => $subject, 'date' => $date], [
                                'section_id' => $section, 'user_id' => $teacher->id, 'code' => ['P', 'T', 'A', 'E'][($i + $day) % 4],
                            ]);
                        }
                    }
                    $this->ensure('observations', ['student_id' => $student, 'section_id' => $section, 'period_id' => $period->id, 'description' => 'DEMO COORD: seguimiento académico de prueba.'], [
                        'subject_id' => $subjects[0], 'user_id' => $teacher->id, 'type' => 'academic', 'date' => $period->start_date,
                    ]);
                }
            }
            $this->command?->info('Demo local lista: A y B asignadas, C fuera del alcance; 6 estudiantes por sección. No se sobrescribieron registros existentes.');
        });
    }

    private function ensure(string $table, array $identity, array $values = []): int
    {
        $existing = DB::table($table)->where($identity)->value('id');
        return $existing ? (int) $existing : DB::table($table)->insertGetId([...$identity, ...$values, 'created_at' => now(), 'updated_at' => now()]);
    }
}
