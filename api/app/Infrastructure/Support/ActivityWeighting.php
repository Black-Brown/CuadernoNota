<?php

namespace App\Infrastructure\Support;

use Illuminate\Support\Facades\DB;

class ActivityWeighting
{
    public static function summary(int $sectionId, int $subjectId, int $periodId): array
    {
        $query = DB::table('course_activities as a')->join('course_offerings as o', 'o.id', '=', 'a.course_offering_id')
            ->where('o.section_id', $sectionId)->where('o.subject_id', $subjectId)->where('a.period_id', $periodId);
        // Includes inactive activities so deactivating the last weighted activity cannot silently change the grading mode.
        $weighted = (clone $query)->where('a.weight_configured', true)->exists();
        $active = (clone $query)->where('a.status', 'active')->get(['a.id', 'a.weight', 'a.weight_configured']);
        $weights = $active->mapWithKeys(fn ($a) => [(int) $a->id => $a->weight_configured ? (float) $a->weight : null])->all();
        $units = array_sum(array_map(fn ($w) => (int) round(($w ?? 0) * 100), $weights));
        $valid = !$weighted || ($active->isNotEmpty() && $units === 10000 && !in_array(null, $weights, true)
            && count(array_filter($weights, fn ($w) => $w > 0 && $w <= 100)) === count($weights));
        return ['mode' => $weighted ? 'weighted' : 'equal', 'valid' => $valid, 'total' => $units / 100,
            'active_count' => $active->count(), 'weights' => $weights];
    }

    public static function recalculate(int $sectionId, int $subjectId, int $periodId): void
    {
        $students = DB::table('students')->where('section_id', $sectionId)->pluck('id');
        $historical = DB::table('period_grades')->where('section_id', $sectionId)->where('subject_id', $subjectId)
            ->where('period_id', $periodId)->where('status', 'draft')->pluck('student_id');
        foreach ($students->merge($historical)->unique()->sort()->values() as $id) {
            DB::table('students')->where('id', $id)->lockForUpdate()->first();
            $recovery = DB::table('period_grades')->where('student_id', $id)->where('subject_id', $subjectId)->where('period_id', $periodId)->value('rp_score');
            $result = app(\App\Application\Grade\RecalculatePeriodGrade::class)->execute((int) $id, $subjectId, $periodId, $sectionId);
            if ($result === null && $recovery !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['weights' => 'El cambio dejaría incompleta una nota con recuperación. Guarda un reparto completo sin eliminar esa recuperación.']);
            }
        }
    }
}
