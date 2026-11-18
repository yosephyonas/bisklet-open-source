<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamageReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'bike_id',
        'ride_id',
        'user_id',
        'maintenance_log_id',
        'category',
        'severity',
        'description',
        'affects_safety',
        'latitude',
        'longitude',
        'photo_urls',
        'status',
        'metadata',
        'resolved_at',
    ];

    protected $casts = [
        'affects_safety' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'photo_urls' => 'array',
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function bike()
    {
        return $this->belongsTo(Bike::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function maintenanceLog()
    {
        return $this->belongsTo(MaintenanceLog::class);
    }
}
