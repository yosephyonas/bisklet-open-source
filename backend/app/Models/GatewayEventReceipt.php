<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayEventReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_hash',
        'event_id',
        'event',
        'imei',
        'payload',
        'occurred_at',
        'received_at',
        'processed_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
