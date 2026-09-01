<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $table = 'course_activities';

    protected $fillable = [
        'course_offering_id', 'period_id', 'activity_template_id', 'created_by',
        'name', 'description', 'type', 'status', 'due_date', 'weight',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'weight'   => 'float',
    ];

    protected $appends = [
        'is_base', 'active', 'user_id', 'subject_id', 'section_id', 'academic_year_id', 'icon',
    ];

    public function getNameAttribute(?string $value): string
    {
        return $value ?? $this->template?->name ?? '';
    }

    public function getIsBaseAttribute(): bool { return $this->activity_template_id !== null; }
    public function getActiveAttribute(): bool { return $this->status === 'active'; }
    public function getUserIdAttribute(): ?int { return $this->created_by; }
    public function getSubjectIdAttribute(): ?int { return $this->courseOffering?->subject_id; }
    public function getSectionIdAttribute(): ?int { return $this->courseOffering?->section_id; }
    public function getAcademicYearIdAttribute(): ?int { return $this->courseOffering?->section?->academic_year_id; }
    public function getIconAttribute(): string { return $this->template?->icon ?? 'assignment'; }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ActivityTemplate::class, 'activity_template_id');
    }

    public function activityScores(): HasMany
    {
        return $this->hasMany(ActivityScore::class);
    }
}
