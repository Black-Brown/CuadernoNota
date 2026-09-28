<?php

namespace App\Infrastructure\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CoordinatorScope
{
    public const LEVELS = ['Primaria', 'Secundaria'];

    public static function sections(int $userId): Builder
    {
        // Read fresh permissions, not a token payload or a cached user model.
        $user = DB::table('users')->where('id', $userId)->where('role', 'coordinator')->where('active', true)->first();
        $query = DB::table('sections as supervised_sections')->select('supervised_sections.id');
        if (! $user) return $query->whereRaw('1 = 0');
        if (in_array($user->coordinator_level, self::LEVELS, true)) {
            return $query->join('grades as supervised_grades', 'supervised_grades.id', '=', 'supervised_sections.grade_id')
                ->whereRaw('LOWER(TRIM(supervised_grades.level)) = ?', [mb_strtolower($user->coordinator_level)]);
        }
        if ($user->coordinator_level !== null) return $query->whereRaw('1 = 0');
        // Existing section grants are preserved until an administrator explicitly migrates them.
        return $query->whereIn('supervised_sections.id', DB::table('coordinator_sections')->where('user_id', $userId)->select('section_id'));
    }

    public static function allows(int $userId, ?int $sectionId): bool
    {
        return $sectionId !== null && self::sections($userId)->where('supervised_sections.id', $sectionId)->exists();
    }
}
