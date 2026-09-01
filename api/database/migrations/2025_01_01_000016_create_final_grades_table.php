<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->unsignedTinyInteger('cf')->nullable();
            $table->decimal('final_recovery', 5, 2)->nullable();
            $table->decimal('special_recovery', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id', 'academic_year_id'], 'unique_final_grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_grades');
    }
};
