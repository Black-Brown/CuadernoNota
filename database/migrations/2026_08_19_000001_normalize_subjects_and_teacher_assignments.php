<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained('grades');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->timestamps();
            $table->unique(['grade_id', 'subject_id']);
        });

        $subjects = DB::table('subjects')->orderBy('id')->get();
        $canonicalByName = [];

        foreach ($subjects as $subject) {
            $key = mb_strtolower(trim($subject->name));
            $canonicalId = $canonicalByName[$key] ??= $subject->id;

            DB::table('grade_subjects')->insertOrIgnore([
                'grade_id' => $subject->grade_id,
                'subject_id' => $canonicalId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($subject->id === $canonicalId) {
                continue;
            }

            foreach (['teacher_sections', 'activities', 'activity_scores', 'period_grades', 'final_grades', 'observations'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'subject_id')) {
                    DB::table($table)->where('subject_id', $subject->id)->update(['subject_id' => $canonicalId]);
                }
            }

            DB::table('subjects')->where('id', $subject->id)->delete();
        }

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropColumn('grade_id');
            $table->boolean('active')->default(true)->after('code');
            $table->unique('name');
            $table->unique('code');
        });

        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['section_id', 'subject_id']);
        });

        DB::table('sections')
            ->join('grade_subjects', 'grade_subjects.grade_id', '=', 'sections.grade_id')
            ->select('sections.id as section_id', 'grade_subjects.subject_id')
            ->orderBy('sections.id')
            ->each(function (object $row): void {
                DB::table('course_offerings')->insertOrIgnore([
                    'section_id' => $row->section_id,
                    'subject_id' => $row->subject_id,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users');
            $table->foreignId('course_offering_id')->constrained('course_offerings');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['teacher_id', 'course_offering_id']);
        });

        DB::table('teacher_sections')
            ->orderBy('id')
            ->each(function (object $assignment): void {
                $offeringId = DB::table('course_offerings')
                    ->where('section_id', $assignment->section_id)
                    ->where('subject_id', $assignment->subject_id)
                    ->value('id');

                if ($offeringId) {
                    DB::table('teacher_assignments')->insertOrIgnore([
                        'teacher_id' => $assignment->user_id,
                        'course_offering_id' => $offeringId,
                        'assigned_by' => null,
                        'assigned_at' => $assignment->created_at ?? now(),
                        'active' => true,
                        'created_at' => $assignment->created_at ?? now(),
                        'updated_at' => $assignment->updated_at ?? now(),
                    ]);
                }
            });

        Schema::drop('teacher_sections');
        $this->createCompatibilityView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS teacher_sections');

        Schema::create('teacher_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->timestamps();
        });

        DB::table('teacher_assignments')
            ->join('course_offerings', 'teacher_assignments.course_offering_id', '=', 'course_offerings.id')
            ->join('sections', 'course_offerings.section_id', '=', 'sections.id')
            ->select(
                'teacher_assignments.teacher_id',
                'course_offerings.section_id',
                'course_offerings.subject_id',
                'sections.academic_year_id',
                'teacher_assignments.created_at',
                'teacher_assignments.updated_at'
            )
            ->orderBy('teacher_assignments.id')
            ->each(fn (object $row) => DB::table('teacher_sections')->insert((array) $row));

        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('course_offerings');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
            $table->dropColumn('active');
            $table->foreignId('grade_id')->nullable()->constrained('grades');
        });

        foreach (DB::table('grade_subjects')->get() as $relation) {
            DB::table('subjects')->where('id', $relation->subject_id)->whereNull('grade_id')->update([
                'grade_id' => $relation->grade_id,
            ]);
        }

        Schema::dropIfExists('grade_subjects');
    }

    private function createCompatibilityView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW teacher_sections AS
            SELECT
                ta.id,
                ta.teacher_id AS user_id,
                co.section_id,
                co.subject_id,
                s.academic_year_id,
                ta.created_at,
                ta.updated_at
            FROM teacher_assignments ta
            INNER JOIN course_offerings co ON co.id = ta.course_offering_id
            INNER JOIN sections s ON s.id = co.section_id
            WHERE ta.active = 1 AND co.active = 1
        SQL);
    }
};
