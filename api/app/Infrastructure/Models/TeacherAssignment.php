<?php

namespace App\Infrastructure\Models;

use App\Application\Activity\EnsureDefaultCourseActivities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssignment extends Model
{
    protected $fillable = [
        'teacher_id', 'course_offering_id', 'assigned_by', 'assigned_at', 'active',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (TeacherAssignment $assignment): void {
            if ($assignment->active) {
                app(EnsureDefaultCourseActivities::class)->execute(
                    $assignment->courseOffering,
                    $assignment->teacher_id,
                );
            }
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'teacher_id');
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }
}
