<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    // La tabla solo tiene created_at, sin updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'affected_table',
        'record_id', 'detail', 'ip',
    ];

    protected $casts = [
        'detail' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
