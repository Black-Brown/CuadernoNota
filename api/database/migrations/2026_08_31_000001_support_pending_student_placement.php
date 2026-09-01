<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->change();
            $table->foreignId('academic_year_id')->nullable()->change();
        });
        Schema::table('promotion_decisions', function (Blueprint $table) {
            $table->foreignId('target_grade_id')->nullable()->after('status')->constrained('grades');
            $table->enum('placement_status', ['pending', 'assigned'])->default('pending')->after('destination_section_id');
        });
        DB::table('promotion_decisions')->whereNotNull('destination_section_id')->update(['placement_status' => 'assigned']);
    }

    public function down(): void
    {
        Schema::table('promotion_decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_grade_id');
            $table->dropColumn('placement_status');
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable(false)->change();
            $table->foreignId('academic_year_id')->nullable(false)->change();
        });
    }
};
