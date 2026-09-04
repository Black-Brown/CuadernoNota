<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garantiza las 3 competencias fijas del sistema (C1, C2, C3) sin depender
 * de que `php artisan db:seed` se ejecute en el deploy.
 *
 * Sin estas filas, cualquier intento de guardar una nota de actividad falla
 * con 422 "The selected competency id is invalid.", porque el backend
 * valida competency_id contra esta tabla (ver GradeController::store()).
 */
return new class extends Migration
{
    public function up(): void
    {
        $competencies = [
            ['id' => 1, 'code' => 'C1', 'name' => 'Comunicativa'],
            ['id' => 2, 'code' => 'C2', 'name' => 'Pensamiento Lógico, Creativo y Crítico + Resolución de Problemas'],
            ['id' => 3, 'code' => 'C3', 'name' => 'Científica y Tecnológica + Ambiental y de la Salud'],
        ];

        foreach ($competencies as $data) {
            DB::table('competencies')->updateOrInsert(
                ['id' => $data['id']],
                array_merge($data, [
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        // No-op: eliminar estas filas rompería cualquier nota ya registrada.
    }
};
