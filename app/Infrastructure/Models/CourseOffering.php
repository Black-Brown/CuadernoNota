<?php

namespace App\Infrastructure\Models;

use App\Application\Activity\EnsureDefaultCourseActivities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends Model
{
    protected $fillable = ['section_id', 'subject_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    protected static function booted(): void
    {
        static::created(function (CourseOffering $offering): void {
            app(EnsureDefaultCourseActivities::class)->execute($offering);
        });

        static::updated(function (CourseOffering $offering): void {
            if ($offering->active && $offering->wasChanged('active')) {
                app(EnsureDefaultCourseActivities::class)->execute($offering);
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
