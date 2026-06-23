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
