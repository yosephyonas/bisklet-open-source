<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalAlert extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'bike_id',
        'ride_id',
        'user_id',
        'imei',
        'source',
        'severity',
        'status',
        'title',
        'body',
        'latitude',
        'longitude',
        'metadata',
        'occurred_at',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'acknowledged_at' => 'datetime',
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
}
