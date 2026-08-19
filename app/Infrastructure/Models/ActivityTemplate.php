<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityTemplate extends Model
{
    protected $fillable = ['name', 'icon', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function courseActivities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
