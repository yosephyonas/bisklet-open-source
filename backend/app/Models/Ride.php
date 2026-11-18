<?php

namespace App\Models;

use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Ride $ride) {
            $status = $ride->status instanceof RideStatus
                ? $ride->status
                : RideStatus::tryFrom((string) $ride->status);

            if ($status === RideStatus::Active) {
                $ride->active_user_id = $ride->user_id;
                $ride->active_bike_id = $ride->bike_id;
                $ride->active_imei = filled($ride->imei) ? (string) $ride->imei : null;

                return;
            }

            $ride->active_user_id = null;
            $ride->active_bike_id = null;
            $ride->active_imei = null;
        });
    }

    protected $fillable = [
        'user_id',
        'bike_id',
        'start_station_id',
        'end_station_id',
        'imei',
        'start_command_id',
        'end_event_id',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'distance_m',
        'unlock_fee',
        'per_minute_rate',
        'total_amount',
        'currency',
        'minutes',
        'price_total',
        'billing_status',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'pricing_breakdown',
        'telemetry',
        'metadata',
        'active_user_id',
        'active_bike_id',
        'active_imei',
    ];

    protected $casts = [
        'status' => RideStatus::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'distance_m' => 'float',
        'unlock_fee' => 'float',
        'per_minute_rate' => 'float',
        'total_amount' => 'float',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'end_latitude' => 'float',
        'end_longitude' => 'float',
        'pricing_breakdown' => 'array',
        'telemetry' => 'array',
        'metadata' => 'array',
        'minutes' => 'integer',
        'price_total' => 'float',
        'active_user_id' => 'integer',
        'active_bike_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bike()
    {
        return $this->belongsTo(Bike::class);
    }

    public function startStation()
    {
        return $this->belongsTo(Station::class, 'start_station_id');
    }

    public function endStation()
    {
        return $this->belongsTo(Station::class, 'end_station_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'imei', 'imei');
    }
}
