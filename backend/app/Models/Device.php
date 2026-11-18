<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'imei',
        'model',
        'hardware_version',
        'is_active',
        'last_ip',
        'lock_status',
        'device_code',
        'lock_code',
        'status',
        'target_host',
        'target_port',
        'provision_status',
        'last_provisioned_at',
        'last_provisioned_by_user_id',
        'last_lat',
        'last_lng',
        'last_gps_at',
        'battery_voltage',
        'battery_percent',
        'signal_strength',
        'gps_sat',
        'lock_state',
        'firmware_version',
        'firmware_build_date',
        'ble_mac',
        'sim_iccid',
        'last_seen_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_gps_at' => 'datetime',
        'is_active' => 'boolean',
        'last_lat' => 'float',
        'last_lng' => 'float',
        'battery_voltage' => 'float',
        'battery_percent' => 'integer',
        'signal_strength' => 'integer',
        'gps_sat' => 'integer',
        'target_port' => 'integer',
        'last_provisioned_at' => 'datetime',
    ];

    public function telemetry()
    {
        return $this->hasMany(DeviceTelemetry::class, 'imei', 'imei');
    }

    public function bike()
    {
        return $this->hasOne(Bike::class, 'imei', 'imei');
    }
}
