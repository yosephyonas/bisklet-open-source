<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockLocation extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'lock_locations';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'imei',
        'lat',
        'lng',
        'hdop',
        'sat',
        'is_valid',
        'captured_at',
        'source',
        'raw_event_id',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'is_valid' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'hdop' => 'float',
        'sat' => 'integer',
    ];
}
