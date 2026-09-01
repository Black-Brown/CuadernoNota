<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('user_id')->constrained('users');
            $table->date('date');
            $table->enum('code', ['P', 'A', 'T', 'E']);
            $table->string('excuse_reason', 200)->nullable();
            $table->string('document_url', 500)->nullable();
            $table->string('updated_from', 1)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'date'], 'unique_attendance_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
