<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceTelemetry extends Model
{
    use HasFactory;

    protected $table = 'device_telemetry';

    public $timestamps = false;

    protected $fillable = [
        'imei',
        'type',
        'raw_payload',
        'parsed_json',
        'device_ts',
        'received_at',
        'op_unix',
        'event_ulid',
        'created_at',
    ];

    protected $casts = [
        'parsed_json' => 'array',
        'created_at' => 'datetime',
        'received_at' => 'datetime',
        'op_unix' => 'integer',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'imei', 'imei');
    }
}
