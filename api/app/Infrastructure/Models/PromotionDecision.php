<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionDecision extends Model
{
    protected $fillable = [
        'student_enrollment_id', 'status', 'target_grade_id', 'destination_section_id', 'placement_status',
        'justification', 'decided_by', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function enrollment(): BelongsTo { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function destinationSection(): BelongsTo { return $this->belongsTo(Section::class, 'destination_section_id'); }
    public function targetGrade(): BelongsTo { return $this->belongsTo(Grade::class, 'target_grade_id'); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'decided_by'); }
}
