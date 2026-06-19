<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodGrade extends Model
{
    protected $table = 'period_grades';

    protected $fillable = [
        'student_id', 'subject_id', 'period_id',
        'c1_score', 'c2_score', 'c3_score',
        'period_score', 'rp_score',
        'status', 'approved_at', 'approved_by',
    ];

    protected $casts = [
        'c1_score'     => 'float',
        'c2_score'     => 'float',
        'c3_score'     => 'float',
        'period_score' => 'float',
        'rp_score'     => 'float',
        'approved_at'  => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }
}
