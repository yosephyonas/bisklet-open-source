<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockStatus extends Model
{
    use HasFactory;

    protected $table = 'lock_statuses';

    public $timestamps = false;

    protected $primaryKey = 'imei';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'imei',
        'is_locked',
        'voltage',
        'battery_percent',
        'signal_strength',
        'last_seen_at',
        'updated_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'voltage' => 'integer',
        'battery_percent' => 'integer',
        'signal_strength' => 'integer',
        'last_seen_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
