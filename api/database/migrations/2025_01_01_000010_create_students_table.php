<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('last_name', 60);
            $table->string('enrollment_no', 20)->unique();
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->boolean('active')->default(true);
            $table->date('deactivation_date')->nullable();
            $table->string('deactivation_reason', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
