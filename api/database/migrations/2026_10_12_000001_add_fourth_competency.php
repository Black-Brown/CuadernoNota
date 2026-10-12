<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fixed IDs are part of the grade-entry API contract. Never overwrite another competency.
        $conflict = DB::table('competencies')->where(function ($query) {
            $query->where('id', 4)->where('code', '!=', 'C4');
        })->orWhere(function ($query) {
            $query->where('code', 'C4')->where('id', '!=', 4);
        })->exists();
        if ($conflict) {
            throw new RuntimeException('El catálogo contiene un conflicto con C4/id 4. Revisar antes de migrar.');
        }

        Schema::table('period_grades', function (Blueprint $table) {
            $table->decimal('c4_score', 5, 2)->nullable();
        });
        DB::table('competencies')->updateOrInsert(['id' => 4], [
            'code' => 'C4',
            'name' => 'Ética y ciudadanía + Desarrollo personal y espiritual',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Existing scores, averages, recoveries and statuses are intentionally unchanged.
    }

    public function down(): void
    {
        // Refuse a destructive rollback once C4 has been used.
        if (DB::table('activity_scores')->where('competency_id', 4)->exists()
            || DB::table('period_grades')->whereNotNull('c4_score')->exists()) {
            throw new RuntimeException('C4 ya contiene notas. No se puede revertir sin perder datos.');
        }
        Schema::table('period_grades', fn (Blueprint $table) => $table->dropColumn('c4_score'));
        DB::table('competencies')->where('id', 4)->where('code', 'C4')->delete();
    }
};
