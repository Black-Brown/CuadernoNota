<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('user_id')->constrained('sections')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->after('section_id')->constrained('subjects')->nullOnDelete();
            $table->foreignId('period_id')->nullable()->after('subject_id')->constrained('periods')->nullOnDelete();

            $table->index(['section_id', 'subject_id', 'period_id'], 'observations_workspace_index');
        });
    }

    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->dropIndex('observations_workspace_index');
            $table->dropConstrainedForeignId('period_id');
            $table->dropConstrainedForeignId('subject_id');
            $table->dropConstrainedForeignId('section_id');
        });
    }
};
