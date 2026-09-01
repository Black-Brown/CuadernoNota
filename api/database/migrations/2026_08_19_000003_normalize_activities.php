<?php

use App\Application\Activity\EnsureDefaultCourseActivities;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('icon', 50)->nullable()->default('assignment');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        foreach (EnsureDefaultCourseActivities::NAMES as $name) {
            DB::table('activity_templates')->insert([
                'name' => $name,
                'icon' => 'assignment',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('course_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained('course_offerings');
            $table->foreignId('period_id')->constrained('periods');
            $table->foreignId('activity_template_id')->nullable()->constrained('activity_templates');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->date('due_date')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(
                ['course_offering_id', 'period_id', 'activity_template_id'],
                'unique_course_period_template'
            );
        });

        Schema::create('legacy_activities_unresolved', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_activity_id')->unique();
            $table->longText('payload');
            $table->string('reason');
            $table->timestamp('created_at')->useCurrent();
        });

        $templates = DB::table('activity_templates')->pluck('id', 'name');
        $legacyActivities = DB::table('activities')->orderBy('id')->get()->keyBy('id');
        $mapped = [];

        Schema::table('activity_scores', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
        });

        $resolveActivity = function (object $legacy, int $offeringId, int $periodId) use ($templates, &$mapped): int {
            $templateId = $legacy->is_base ? ($templates[$legacy->name] ?? null) : null;
            $identity = [
                'course_offering_id' => $offeringId,
                'period_id' => $periodId,
                'activity_template_id' => $templateId,
            ];

            if ($templateId === null) {
                $identity['name'] = $legacy->name;
            }

            $existing = DB::table('course_activities')->where($identity)->first();
            if ($existing) {
                $id = (int) $existing->id;
            } else {
                $id = DB::table('course_activities')->insertGetId($identity + [
                    'created_by' => $legacy->user_id,
                    'description' => $legacy->description,
                    'type' => $legacy->type,
                    'status' => $legacy->active ? ($legacy->status ?: 'active') : 'inactive',
                    'due_date' => $legacy->due_date,
                    'weight' => $legacy->weight,
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ]);
            }

            $mapped[$legacy->id]["{$offeringId}:{$periodId}"] = $id;
            return $id;
        };

        // Scores provide the most reliable section and period for historical rows.
        DB::table('activity_scores')
            ->join('students', 'activity_scores.student_id', '=', 'students.id')
            ->select('activity_scores.*', 'students.section_id as student_section_id')
            ->orderBy('activity_scores.id')
            ->each(function (object $score) use ($legacyActivities, $resolveActivity): void {
                $legacy = $legacyActivities->get($score->activity_id);
                if (!$legacy) {
                    return;
                }

                $offeringId = DB::table('course_offerings')
                    ->where('section_id', $score->student_section_id)
                    ->where('subject_id', $legacy->subject_id ?? $score->subject_id)
                    ->value('id');

                if (!$offeringId) {
                    $offeringId = DB::table('course_offerings')->insertGetId([
                        'section_id' => $score->student_section_id,
                        'subject_id' => $legacy->subject_id ?? $score->subject_id,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $newActivityId = $resolveActivity($legacy, (int) $offeringId, (int) $score->period_id);
                DB::table('activity_scores')->where('id', $score->id)->update([
                    'activity_id' => $newActivityId,
                ]);
            });

        foreach ($legacyActivities as $legacy) {
            $sectionIds = $legacy->section_id
                ? collect([(int) $legacy->section_id])
                : DB::table('course_offerings')
                    ->join('sections', 'course_offerings.section_id', '=', 'sections.id')
                    ->where('course_offerings.subject_id', $legacy->subject_id)
                    ->where('sections.academic_year_id', $legacy->academic_year_id)
                    ->pluck('course_offerings.section_id');

            $periodIds = $legacy->period_id
                ? collect([(int) $legacy->period_id])
                : DB::table('periods')
                    ->where('academic_year_id', $legacy->academic_year_id)
                    ->pluck('id');

            foreach ($sectionIds as $sectionId) {
                $offeringId = DB::table('course_offerings')
                    ->where('section_id', $sectionId)
                    ->where('subject_id', $legacy->subject_id)
                    ->value('id');

                if (!$offeringId) {
                    continue;
                }

                foreach ($periodIds as $periodId) {
                    $resolveActivity($legacy, (int) $offeringId, (int) $periodId);
                }
            }

            if (empty($mapped[$legacy->id])) {
                DB::table('legacy_activities_unresolved')->insert([
                    'legacy_activity_id' => $legacy->id,
                    'payload' => json_encode($legacy, JSON_UNESCAPED_UNICODE),
                    'reason' => 'No fue posible determinar curso y período.',
                    'created_at' => now(),
                ]);
            }
        }

        Schema::drop('activities');
        Schema::table('activity_scores', function (Blueprint $table) {
            $table->foreign('activity_id')->references('id')->on('course_activities');
        });

        $this->createCompatibilityView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS activities');

        Schema::table('activity_scores', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->string('type', 50)->nullable();
            $table->boolean('is_base')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->string('status', 20)->nullable()->default('active');
            $table->date('due_date')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('icon', 50)->nullable()->default('assignment');
            $table->timestamps();
        });

        DB::table('course_activities')
            ->join('course_offerings', 'course_activities.course_offering_id', '=', 'course_offerings.id')
            ->join('sections', 'course_offerings.section_id', '=', 'sections.id')
            ->leftJoin('activity_templates', 'course_activities.activity_template_id', '=', 'activity_templates.id')
            ->select(
                'course_activities.*',
                'course_offerings.subject_id',
                'course_offerings.section_id',
                'sections.academic_year_id',
                'activity_templates.name as template_name',
                'activity_templates.icon as template_icon'
            )
            ->orderBy('course_activities.id')
            ->each(function (object $row): void {
                DB::table('activities')->insert([
                    'id' => $row->id,
                    'name' => $row->template_name ?? $row->name,
                    'description' => $row->description,
                    'type' => $row->type,
                    'is_base' => $row->activity_template_id !== null,
                    'user_id' => $row->created_by,
                    'subject_id' => $row->subject_id,
                    'section_id' => $row->section_id,
                    'academic_year_id' => $row->academic_year_id,
                    'period_id' => $row->period_id,
                    'active' => $row->status === 'active',
                    'status' => $row->status,
                    'due_date' => $row->due_date,
                    'weight' => $row->weight,
                    'icon' => $row->template_icon ?? 'assignment',
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

        Schema::table('activity_scores', function (Blueprint $table) {
            $table->foreign('activity_id')->references('id')->on('activities');
        });

        Schema::dropIfExists('course_activities');
        Schema::dropIfExists('activity_templates');
        Schema::dropIfExists('legacy_activities_unresolved');
    }

    private function createCompatibilityView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW activities AS
            SELECT
                ca.id,
                COALESCE(ca.name, at.name) AS name,
                ca.description,
                ca.type,
                CASE WHEN ca.activity_template_id IS NULL THEN 0 ELSE 1 END AS is_base,
                ca.created_by AS user_id,
                co.subject_id,
                co.section_id,
                s.academic_year_id,
                ca.period_id,
                CASE WHEN ca.status = 'active' THEN 1 ELSE 0 END AS active,
                ca.status,
                ca.due_date,
                ca.weight,
                COALESCE(at.icon, 'assignment') AS icon,
                ca.created_at,
                ca.updated_at
            FROM course_activities ca
            INNER JOIN course_offerings co ON co.id = ca.course_offering_id
            INNER JOIN sections s ON s.id = co.section_id
            LEFT JOIN activity_templates at ON at.id = ca.activity_template_id
        SQL);
    }
};
