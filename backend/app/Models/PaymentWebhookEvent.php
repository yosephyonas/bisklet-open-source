<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_id',
        'provider_reference',
        'payment_id',
        'status',
        'payload',
        'signature',
        'received_at',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
