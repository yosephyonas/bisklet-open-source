<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockEvent extends Model
{
    use HasFactory;

    protected $table = 'lock_events';

    public $timestamps = false;

    protected $fillable = [
        'imei',
        'type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];
}
