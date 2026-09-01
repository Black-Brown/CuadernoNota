<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $table = 'competencies';

    protected $fillable = ['code', 'name', 'description', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function activityScores(): HasMany
    {
        return $this->hasMany(ActivityScore::class);
    }
}
