<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompetencySeeder extends Seeder
{
    /**
     * Inserta las 3 competencias fijas del sistema.
     * Usa updateOrCreate por id para que sea idempotente
     * (se puede correr múltiples veces sin duplicar datos).
     */
    public function run(): void
    {
        $competencies = [
            [
                'id'   => 1,
                'code' => 'C1',
                'name' => 'Comunicativa',
            ],
            [
                'id'   => 2,
                'code' => 'C2',
                'name' => 'Pensamiento Lógico, Creativo y Crítico + Resolución de Problemas',
            ],
            [
                'id'   => 3,
                'code' => 'C3',
                'name' => 'Científica y Tecnológica + Ambiental y de la Salud',
            ],
        ];

        foreach ($competencies as $data) {
            DB::table('competencies')->updateOrInsert(
                ['id' => $data['id']],
                array_merge($data, [
                    'active'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
