<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Natural key preserves the existing API contract without duplicate level columns.
        Schema::create('academic_levels', function (Blueprint $table) {
            $table->string('name', 20)->primary();
        });
        $names = collect(['Inicial', 'Primaria', 'Secundaria'])
            ->merge(DB::table('grades')->pluck('level'))
            ->merge(DB::table('users')->whereNotNull('coordinator_level')->pluck('coordinator_level'))
            ->unique();
        foreach ($names as $name) {
            DB::table('academic_levels')->insert(['name' => $name]);
        }
        Schema::table('grades', fn (Blueprint $table) => $table->foreign('level')->references('name')->on('academic_levels'));
        Schema::table('users', fn (Blueprint $table) => $table->foreign('coordinator_level')->references('name')->on('academic_levels'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['coordinator_level']));
        Schema::table('grades', fn (Blueprint $table) => $table->dropForeign(['level']));
        Schema::drop('academic_levels');
    }
};
