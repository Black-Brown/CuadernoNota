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
        Schema::table('activity_templates', function (Blueprint $table) {
            $table->boolean('is_fixed')->default(false)->after('active');
        });
        DB::table('activity_templates')->whereIn('name', EnsureDefaultCourseActivities::NAMES)->update(['is_fixed' => true]);
    }

    public function down(): void
    {
        Schema::table('activity_templates', fn (Blueprint $table) => $table->dropColumn('is_fixed'));
    }
};
