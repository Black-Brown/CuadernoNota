<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preflight before altering schema: never guess which conflicting decision is correct.
        $conflict = DB::table('student_promotions as old')
            ->join('student_enrollments as e', 'e.student_id', '=', 'old.student_id')
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('promotion_decisions as d', 'd.student_enrollment_id', '=', 'e.id')
            ->whereColumn('s.academic_year_id', 'old.academic_year_id')
            ->where('d.status', '!=', 'promoted')->exists();
        if ($conflict) throw new RuntimeException('Hay promociones históricas que contradicen decisiones administrativas. Resolver antes de migrar.');

        Schema::table('promotion_decisions', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->constrained('students');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years');
            $table->foreignId('student_enrollment_id')->nullable()->change();
            $table->foreignId('decided_by')->nullable()->change();
            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('placement_status', ['pending', 'assigned'])->nullable()->default('pending')->change();
            }
            $table->string('source', 20)->default('administrative');
            $table->json('legacy_record')->nullable();
            $table->index(['student_id', 'academic_year_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL represents Laravel enums as varchar + CHECK; keep the existing CHECK.
            DB::statement('ALTER TABLE promotion_decisions ALTER COLUMN placement_status DROP NOT NULL');
        }
        DB::table('promotion_decisions')->orderBy('id')->eachById(function ($decision) {
            $origin = DB::table('student_enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')
                ->where('e.id', $decision->student_enrollment_id)->first(['e.student_id', 's.academic_year_id']);
            DB::table('promotion_decisions')->where('id', $decision->id)->update((array) $origin);
        });
        DB::table('student_promotions')->orderBy('id')->eachById(function ($old) {
            $existing = DB::table('promotion_decisions')->where('student_id', $old->student_id)
                ->where('academic_year_id', $old->academic_year_id)->orderByDesc('decided_at')->orderByDesc('id')->first();
            if ($existing) {
                DB::table('promotion_decisions')->where('id', $existing->id)->update(['legacy_record' => json_encode($old, JSON_THROW_ON_ERROR)]);
            } else {
                // No invented enrollment, destination, author or pending placement for legacy data.
                DB::table('promotion_decisions')->insert([
                    'student_id' => $old->student_id, 'academic_year_id' => $old->academic_year_id,
                    'status' => 'promoted', 'source' => 'legacy', 'placement_status' => null,
                    'decided_at' => $old->promoted_at, 'created_at' => $old->created_at, 'updated_at' => $old->updated_at,
                    'legacy_record' => json_encode($old, JSON_THROW_ON_ERROR),
                ]);
            }
        });
        Schema::table('promotion_decisions', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable(false)->change();
            $table->foreignId('academic_year_id')->nullable(false)->change();
        });
        Schema::drop('student_promotions');
    }

    public function down(): void
    {
        throw new RuntimeException('La unificación conserva historial en promotion_decisions. Revertir requiere una migración de datos explícita.');
    }
};
