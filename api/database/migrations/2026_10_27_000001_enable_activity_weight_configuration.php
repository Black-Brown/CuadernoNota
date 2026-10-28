<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_activities', fn (Blueprint $table) => $table->boolean('weight_configured')->default(false));
        // Historical weights were informational; do not activate them or recalculate existing grades.
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('course_activities')->where('weight_configured', true)->exists()) {
            throw new RuntimeException('Hay ponderaciones configuradas; no se pueden desactivar mediante rollback.');
        }
        Schema::table('course_activities', fn (Blueprint $table) => $table->dropColumn('weight_configured'));
    }
};
