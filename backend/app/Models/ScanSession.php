<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanSession extends Model
{
    use HasFactory;

    protected $table = 'scan_sessions';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'imei',
        'scan_token_hash',
        'expires_at',
        'used_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
