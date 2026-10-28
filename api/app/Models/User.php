<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'google_avatar',
        'email_verified_at',
        'password',
        'role',
        'coordinator_level',
        'active',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function coordinatorAcademicLevel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Infrastructure\Models\AcademicLevel::class, 'coordinator_level', 'name');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login'        => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
        ];
    }
}
