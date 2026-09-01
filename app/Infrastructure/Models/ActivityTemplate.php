<?php

namespace App\Infrastructure\Models;

use App\Application\Activity\EnsureDefaultCourseActivities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ActivityTemplate extends Model
{
    protected $fillable = ['name', 'icon', 'active'];

    protected $casts = ['active' => 'boolean', 'is_fixed' => 'boolean'];

    public function getIsFixedAttribute(mixed $value): bool
    {
        return (bool) $value || in_array($this->getRawOriginal('name') ?? $this->name, EnsureDefaultCourseActivities::NAMES, true);
    }

    protected static function booted(): void
    {
        static::deleting(function (ActivityTemplate $template): void {
            if ($template->is_fixed) {
                throw ValidationException::withMessages(['activity_template' => 'Las actividades base fijas no se pueden eliminar.']);
            }
        });
    }

    public function courseActivities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
