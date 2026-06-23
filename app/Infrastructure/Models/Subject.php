<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = ['grade_id', 'name', 'code'];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function teacherSections(): HasMany
    {
        return $this->hasMany(TeacherSection::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
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
