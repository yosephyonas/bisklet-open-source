<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BleKey extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'ble_keys';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'imei',
        'key_value',
        'version',
        'rotated_at',
        'created_at',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
