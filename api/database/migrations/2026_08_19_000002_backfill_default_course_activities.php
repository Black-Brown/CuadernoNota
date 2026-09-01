<?php

use App\Infrastructure\Models\CourseOffering;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CourseOffering::query()
            ->with('section')
            ->where('active', true)
            ->orderBy('id')
            ->each(function (CourseOffering $offering): void {
                $teacherId = $offering->teacherAssignments()
                    ->where('active', true)
                    ->orderBy('assigned_at')
                    ->value('teacher_id');

                $periodIds = DB::table('periods')
                    ->where('academic_year_id', $offering->section->academic_year_id)
                    ->pluck('id');

                foreach ($periodIds as $periodId) {
                    foreach (['Proyectos', 'Examen', 'Tareas', 'Ensayo', 'Producción en aula', 'Diagnósticas'] as $name) {
                        DB::table('activities')->updateOrInsert(
                            [
                                'name' => $name,
                                'subject_id' => $offering->subject_id,
                                'section_id' => $offering->section_id,
                                'period_id' => $periodId,
                            ],
                            [
                                'is_base' => true,
                                'user_id' => $teacherId,
                                'academic_year_id' => $offering->section->academic_year_id,
                                'active' => true,
                                'status' => 'active',
                                'icon' => 'assignment',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        // The activities may already contain grades, so rollback keeps them.
    }
};
