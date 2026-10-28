<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\EloquentPromotionRepository;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicSchemaNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_level_is_rejected_by_database(): void
    {
        $this->assertDatabaseHas('academic_levels', ['name' => 'Primaria']);
        $this->expectException(QueryException::class);
        DB::table('grades')->insert(['name' => 'Grado', 'level' => 'Inexistente', 'sort_order' => 1]);
    }

    public function test_coordinator_level_has_a_real_foreign_key(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'coordinator', 'coordinator_level' => 'Secundaria']);
        $this->assertSame('Secundaria', $user->coordinatorAcademicLevel->name);
        $this->expectException(QueryException::class);
        $user->update(['coordinator_level' => 'Inexistente']);
    }

    public function test_repository_uses_single_history_and_never_overwrites_negative_decision(): void
    {
        [$student, $year] = $this->studentYear();
        $repo = app(EloquentPromotionRepository::class);
        $repo->markAsPromoted($student, $year);
        $repo->markAsPromoted($student, $year);
        $this->assertDatabaseCount('promotion_decisions', 1);
        $this->assertFalse(Schema::hasTable('student_promotions'));
        $this->assertTrue($repo->isPromoted($student, $year));
        DB::table('promotion_decisions')->update(['status' => 'not_promoted']);
        $this->assertFalse($repo->isPromoted($student, $year));
        try {
            $repo->markAsPromoted($student, $year);
            $this->fail('Must not overwrite a decision');
        } catch (\DomainException) {
            $this->assertDatabaseHas('promotion_decisions', ['status' => 'not_promoted']);
        }
    }

    public function test_migration_preserves_legacy_history_without_inventing_enrollment_or_author(): void
    {
        [$student, $year] = $this->studentYear();
        $this->oldPromotionSchema();
        DB::table('student_promotions')->insert([
            'id' => 73, 'student_id' => $student, 'academic_year_id' => $year,
            'promoted_at' => '2025-06-30 10:00:00', 'created_at' => '2025-06-30 10:00:00',
        ]);
        (require database_path('migrations/2026_10_28_000002_unify_promotion_records.php'))->up();
        $row = DB::table('promotion_decisions')->first();
        $this->assertSame('legacy', $row->source);
        $this->assertSame($student, $row->student_id);
        $this->assertSame($year, $row->academic_year_id);
        $this->assertNull($row->student_enrollment_id);
        $this->assertNull($row->decided_by);
        $this->assertNull($row->placement_status);
        $this->assertSame(73, json_decode($row->legacy_record, true)['id']);
        $this->assertSame('2025-06-30 10:00:00', $row->decided_at);
        $this->assertFalse(Schema::hasTable('student_promotions'));
    }

    private function studentYear(): array
    {
        $year = DB::table('academic_years')->insertGetId(['name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-06-30']);
        $student = DB::table('students')->insertGetId(['name' => 'Ana', 'last_name' => 'Prueba', 'enrollment_no' => 'SCHEMA-1']);
        return [$student, $year];
    }

    public function test_migration_merges_compatible_history_and_backfills_existing_decision(): void
    {
        [$student, $year] = $this->studentYear();
        $this->oldPromotionSchema();
        $decision = $this->oldDecision($student, $year, 'promoted');
        DB::table('student_promotions')->insert(['student_id' => $student, 'academic_year_id' => $year]);
        (require database_path('migrations/2026_10_28_000002_unify_promotion_records.php'))->up();
        $this->assertDatabaseCount('promotion_decisions', 1);
        $row = DB::table('promotion_decisions')->find($decision);
        $this->assertSame($student, $row->student_id);
        $this->assertSame($year, $row->academic_year_id);
        $this->assertSame('administrative', $row->source);
        $this->assertNotNull($row->student_enrollment_id);
        $this->assertNotNull($row->decided_by);
        $this->assertNotNull($row->legacy_record);
    }

    public function test_conflicting_history_stops_migration_before_schema_changes(): void
    {
        [$student, $year] = $this->studentYear();
        $this->oldPromotionSchema();
        $this->oldDecision($student, $year, 'not_promoted');
        DB::table('student_promotions')->insert(['student_id' => $student, 'academic_year_id' => $year]);
        try {
            (require database_path('migrations/2026_10_28_000002_unify_promotion_records.php'))->up();
            $this->fail('Expected conflicting promotion to block migration');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('contradicen', $error->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('promotion_decisions', 'student_id'));
        $this->assertDatabaseCount('student_promotions', 1);
        $this->assertDatabaseHas('promotion_decisions', ['status' => 'not_promoted']);
    }

    public function test_level_migration_keeps_existing_values_and_permissions(): void
    {
        $migration = require database_path('migrations/2026_10_28_000001_normalize_academic_levels.php');
        $migration->down();
        DB::table('grades')->insert(['name' => 'Histórico', 'level' => ' secundaria ', 'sort_order' => 1]);
        $coordinator = \App\Models\User::factory()->create(['role' => 'coordinator', 'coordinator_level' => 'Primaria']);
        $migration->up();
        $this->assertDatabaseHas('academic_levels', ['name' => ' secundaria ']);
        $this->assertDatabaseHas('grades', ['level' => ' secundaria ']);
        $this->assertSame('Primaria', $coordinator->fresh()->coordinator_level);
    }

    private function oldDecision(int $student, int $year, string $status): int
    {
        $grade = DB::table('grades')->insertGetId(['name' => 'Primero', 'level' => 'Primaria', 'sort_order' => 1]);
        $section = DB::table('sections')->insertGetId(['grade_id' => $grade, 'academic_year_id' => $year, 'name' => 'A', 'shift' => 'Matutina']);
        $enrollment = DB::table('student_enrollments')->insertGetId(['student_id' => $student, 'section_id' => $section, 'enrolled_at' => '2026-08-01']);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        return DB::table('promotion_decisions')->insertGetId(['student_enrollment_id' => $enrollment, 'status' => $status, 'decided_by' => $admin->id]);
    }

    private function oldPromotionSchema(): void
    {
        Schema::drop('promotion_decisions');
        Schema::create('promotion_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->unique()->constrained('student_enrollments');
            $table->enum('status', ['promoted', 'not_promoted']);
            $table->foreignId('destination_section_id')->nullable()->constrained('sections');
            $table->foreignId('target_grade_id')->nullable()->constrained('grades');
            $table->enum('placement_status', ['pending', 'assigned'])->default('pending');
            $table->text('justification')->nullable();
            $table->foreignId('decided_by')->constrained('users');
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
        });
        (require database_path('migrations/2025_01_01_000021_create_student_promotions_table.php'))->up();
    }
}
