<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmwareUpdate extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'firmware_updates';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'imei',
        'target_version',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'progress_percent',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_percent' => 'integer',
    ];
}
