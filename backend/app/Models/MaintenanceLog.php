<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'bike_id',
        'user_id',
        'type',
        'status',
        'notes',
        'performed_at',
        'cost',
        'metadata',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'cost' => 'float',
        'metadata' => 'array',
    ];

    public function bike()
    {
        return $this->belongsTo(Bike::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
