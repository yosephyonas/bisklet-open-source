<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LockCommand extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'lock_commands';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'request_id',
        'imei',
        'user_id',
        'command',
        'status',
        'requested_at',
        'sent_at',
        'completed_at',
        'error_code',
        'result_json',
        'reset_flag',
        'cycle_min',
        'op_unix',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'result_json' => 'array',
        'op_unix' => 'integer',
        'reset_flag' => 'integer',
        'cycle_min' => 'integer',
    ];
}
