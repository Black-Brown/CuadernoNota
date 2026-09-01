<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalGrade extends Model
{
    protected $table = 'final_grades';

    protected $fillable = [
        'student_id', 'subject_id', 'academic_year_id',
        'cf', 'final_recovery', 'special_recovery',
    ];

    protected $casts = [
        'cf'               => 'integer',
        'final_recovery'   => 'float',
        'special_recovery' => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
