<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'route_name',
        'method',
        'path',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(array $attributes): void
    {
        try {
            if (! Schema::hasTable('admin_audit_logs')) {
                return;
            }

            static::query()->create($attributes);
        } catch (\Throwable) {
            // Audit logging must never block an operator action.
        }
    }
}
