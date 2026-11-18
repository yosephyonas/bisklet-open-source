<?php

namespace App\Models;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Device;

class Bike extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id',
        'code',
        'imei',
        'model',
        'type',
        'status',
        'is_active',
        'battery_level',
        'odometer_km',
        'price_per_minute',
        'unlock_fee',
        'last_service_at',
        'hardware',
        'metadata',
        'last_latitude',
        'last_longitude',
        'location_accuracy',
        'location_updated_at',
    ];

    protected $casts = [
        'status' => BikeStatus::class,
        'is_active' => 'boolean',
        'battery_level' => 'float',
        'odometer_km' => 'float',
        'price_per_minute' => 'float',
        'unlock_fee' => 'float',
        'last_service_at' => 'datetime',
        'hardware' => 'array',
        'metadata' => 'array',
        'last_latitude' => 'float',
        'last_longitude' => 'float',
        'location_accuracy' => 'float',
        'location_updated_at' => 'datetime',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class);
    }

    public function operationalAlerts()
    {
        return $this->hasMany(OperationalAlert::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function activeRide()
    {
        return $this->hasOne(Ride::class)
            ->where('status', RideStatus::Active->value)
            ->latestOfMany('started_at');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'imei', 'imei');
    }

    public function scopeWithoutActiveRide($query)
    {
        return $query->whereNotExists(function ($subquery) {
            $subquery
                ->selectRaw('1')
                ->from('rides')
                ->where('rides.status', RideStatus::Active->value)
                ->where(function ($rideQuery) {
                    $rideQuery
                        ->whereColumn('rides.bike_id', 'bikes.id')
                        ->orWhereColumn('rides.active_bike_id', 'bikes.id')
                        ->orWhereColumn('rides.imei', 'bikes.imei')
                        ->orWhereColumn('rides.active_imei', 'bikes.imei');
                });
        });
    }
}
