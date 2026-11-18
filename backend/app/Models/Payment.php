<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'user_id',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'payload',
        'metadata',
        'idempotency_key',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
