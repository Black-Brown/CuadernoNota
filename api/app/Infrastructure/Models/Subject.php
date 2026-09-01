<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = ['name', 'code', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'grade_subjects')->withTimestamps();
    }

    public function teacherSections(): HasMany
    {
        return $this->hasMany(TeacherSection::class);
    }

    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
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
