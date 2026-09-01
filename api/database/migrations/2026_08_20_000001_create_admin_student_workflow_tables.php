<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('section_id')->constrained('sections');
            $table->enum('status', ['active', 'withdrawn', 'completed'])->default('active');
            $table->date('enrolled_at');
            $table->date('ended_at')->nullable();
            $table->string('end_reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'section_id']);
        });

        DB::table('students')->orderBy('id')->each(function (object $student): void {
            $year = DB::table('academic_years')->where('id', $student->academic_year_id)->first();
            DB::table('student_enrollments')->insert([
                'student_id' => $student->id, 'section_id' => $student->section_id,
                'status' => $student->active ? 'active' : 'withdrawn',
                'enrolled_at' => $year->start_date ?? now()->toDateString(),
                'ended_at' => $student->deactivation_date,
                'end_reason' => $student->deactivation_reason,
                'created_at' => $student->created_at ?? now(), 'updated_at' => now(),
            ]);
        });

        Schema::create('grade_review_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('periods');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('section_id')->constrained('sections');
            $table->enum('action', ['approved', 'rejected', 'reopened']);
            $table->text('comment')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('promotion_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments');
            $table->enum('status', ['promoted', 'not_promoted']);
            $table->foreignId('destination_section_id')->nullable()->constrained('sections');
            $table->text('justification')->nullable();
            $table->foreignId('decided_by')->constrained('users');
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
            $table->unique('student_enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_decisions');
        Schema::dropIfExists('grade_review_actions');
        Schema::dropIfExists('student_enrollments');
    }
};
