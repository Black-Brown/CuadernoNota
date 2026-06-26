<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $activities = DB::table('activities')
            ->whereNull('period_id')
            ->whereNotNull('section_id')
            ->orderBy('id')
            ->get();

        foreach ($activities as $activity) {
            $periods = DB::table('periods')
                ->where('academic_year_id', $activity->academic_year_id)
                ->orderBy('number')
                ->get();

            if ($periods->isEmpty()) {
                continue;
            }

            $scorePeriodIds = DB::table('activity_scores')
                ->where('activity_id', $activity->id)
                ->distinct()
                ->pluck('period_id')
                ->all();

            $primaryPeriodId = $scorePeriodIds[0] ?? $periods->first()->id;

            DB::table('activities')
                ->where('id', $activity->id)
                ->update(['period_id' => $primaryPeriodId]);

            foreach ($periods as $period) {
                if ((int) $period->id === (int) $primaryPeriodId) {
                    continue;
                }

                $copy = (array) $activity;
                unset($copy['id']);
                $copy['period_id'] = $period->id;
                $copy['created_at'] = now();
                $copy['updated_at'] = now();

                $newActivityId = DB::table('activities')->insertGetId($copy);

                if (in_array($period->id, $scorePeriodIds, false)) {
                    DB::table('activity_scores')
                        ->where('activity_id', $activity->id)
                        ->where('period_id', $period->id)
                        ->update(['activity_id' => $newActivityId]);
                }
            }
        }
    }

    public function down(): void
    {
        // Data migration only. Keeping duplicated activities is safer than
        // merging rows and risking score collisions.
    }
};
