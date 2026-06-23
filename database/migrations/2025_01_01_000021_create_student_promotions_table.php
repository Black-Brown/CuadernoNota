<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->timestamp('promoted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id'], 'unique_student_promotion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
