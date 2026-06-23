<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $table = 'academic_years';

    protected $fillable = ['name', 'start_date', 'end_date', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function finalGrades(): HasMany
    {
        return $this->hasMany(FinalGrade::class);
    }
}
