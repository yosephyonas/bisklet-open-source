<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FydaVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'national_id',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'verified_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
