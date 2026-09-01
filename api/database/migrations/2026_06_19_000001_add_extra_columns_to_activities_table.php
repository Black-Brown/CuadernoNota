<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('type', 50)->nullable()->after('description');
            $table->string('status', 20)->nullable()->default('active')->after('active');
            $table->date('due_date')->nullable()->after('status');
            $table->decimal('weight', 5, 2)->nullable()->after('due_date');
            $table->string('icon', 50)->nullable()->default('assignment')->after('weight');
            $table->foreignId('period_id')
                ->nullable()
                ->after('academic_year_id')
                ->constrained('periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['period_id']);
            $table->dropColumn(['description', 'type', 'status', 'due_date', 'weight', 'icon', 'period_id']);
        });
    }
};
