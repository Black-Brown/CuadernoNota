<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    protected $table = 'periods';

    protected $fillable = [
        'academic_year_id', 'number', 'name', 'months',
        'start_date', 'end_date', 'status',
    ];

    protected $casts = [
        'number'     => 'integer',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function effectiveStatus(): string
    {
        if ($this->status !== 'open') {
            return $this->status;
        }

        $today = now()->startOfDay();
        if ($today->lt($this->start_date->startOfDay())) {
            return 'upcoming';
        }
        if ($today->gt($this->end_date->endOfDay())) {
            return 'ended';
        }

        return 'open';
    }

    public function isOpenForTeacher(): bool
    {
        return $this->effectiveStatus() === 'open';
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function activityScores(): HasMany
    {
        return $this->hasMany(ActivityScore::class);
    }

    public function periodGrades(): HasMany
    {
        return $this->hasMany(PeriodGrade::class);
    }
}
