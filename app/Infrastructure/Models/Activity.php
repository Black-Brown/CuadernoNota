<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $table = 'activities';

    protected $fillable = [
        'name', 'description', 'type', 'is_base',
        'user_id', 'subject_id', 'academic_year_id', 'period_id',
        'active', 'status', 'due_date', 'weight', 'icon',
    ];

    protected $casts = [
        'is_base'  => 'boolean',
        'active'   => 'boolean',
        'due_date' => 'date:Y-m-d',
        'weight'   => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function activityScores(): HasMany
    {
        return $this->hasMany(ActivityScore::class);
    }
}
