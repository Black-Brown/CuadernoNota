<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $fillable = ['student_id', 'section_id', 'status', 'enrolled_at', 'ended_at', 'end_reason', 'created_by'];
    protected $casts = ['enrolled_at' => 'date', 'ended_at' => 'date'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }
}
