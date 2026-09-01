<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities');
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('competency_id')->constrained('competencies');
            $table->foreignId('period_id')->constrained('periods');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['activity_id', 'student_id', 'competency_id', 'period_id', 'subject_id'],
                'unique_activity_score'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_scores');
    }
};
