<?php

namespace App\Infrastructure\Http\Controllers\Coordinador;

use Illuminate\Support\Facades\DB;

class GradeReviewController extends \App\Infrastructure\Http\Controllers\Admin\GradeReviewController
{
    protected function scopeReviews($query)
    {
        return $query->whereIn('period_grades.section_id', DB::table('coordinator_sections')
            ->where('user_id', auth()->id())->select('section_id'));
    }

    protected function authorizeWorkspace(int $sectionId, int $subjectId, int $periodId): void
    {
        abort_unless(DB::table('coordinator_sections')->where('user_id', auth()->id())
            ->where('section_id', $sectionId)->exists(), 403, 'Esta sección no está bajo tu supervisión.');
        abort_unless(DB::table('sections')->join('periods', 'periods.academic_year_id', '=', 'sections.academic_year_id')
            ->where('sections.id', $sectionId)->where('periods.id', $periodId)->exists(), 422, 'El período no pertenece al año de la sección.');
        abort_unless(DB::table('course_offerings')->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)->exists(), 422, 'La materia no pertenece a la sección.');
    }

    protected function allowedActions(): array
    {
        return ['approved', 'rejected'];
    }
}
