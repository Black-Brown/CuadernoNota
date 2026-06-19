<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('period_id')->constrained('periods');
            $table->decimal('c1_score', 5, 2)->nullable();
            $table->decimal('c2_score', 5, 2)->nullable();
            $table->decimal('c3_score', 5, 2)->nullable();
            $table->decimal('period_score', 5, 2)->nullable();
            $table->decimal('rp_score', 5, 2)->nullable();
            $table->enum('status', ['draft', 'in_review', 'official'])->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'period_id'], 'unique_period_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_grades');
    }
};
