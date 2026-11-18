<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'latitude',
        'longitude',
        'capacity',
        'available_docks',
        'status',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'capacity' => 'integer',
        'available_docks' => 'integer',
        'metadata' => 'array',
    ];

    public function bikes()
    {
        return $this->hasMany(Bike::class);
    }

    public function startingRides()
    {
        return $this->hasMany(Ride::class, 'start_station_id');
    }

    public function endingRides()
    {
        return $this->hasMany(Ride::class, 'end_station_id');
    }
}
