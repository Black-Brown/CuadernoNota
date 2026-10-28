<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicLevel extends Model
{
    protected $primaryKey = 'name';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['name'];

    public function grades(): HasMany { return $this->hasMany(Grade::class, 'level', 'name'); }
    public function coordinators(): HasMany { return $this->hasMany(\App\Models\User::class, 'coordinator_level', 'name')->where('role', 'coordinator'); }
}
