<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExclusiveDeal extends Model
{
    use HasFactory;

    public const CACHE_VERSION_KEY = 'exclusive_deals:version';

    protected $fillable = [
        'title',
        'subtitle',
        'badge',
        'cta_label',
        'deeplink',
        'image_url',
        'is_exclusive',
        'is_active',
        'starts_at',
        'ends_at',
        'priority',
    ];

    protected $casts = [
        'is_exclusive' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::touchCacheVersion();
        });

        static::deleted(function (): void {
            self::touchCacheVersion();
        });
    }

    public static function cacheVersion(): string
    {
        $version = Cache::get(self::CACHE_VERSION_KEY);
        if ($version === null || $version === '') {
            $version = self::newCacheVersionToken();
            Cache::forever(self::CACHE_VERSION_KEY, $version);
        }

        return (string) $version;
    }

    public static function touchCacheVersion(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, self::newCacheVersionToken());
    }

    private static function newCacheVersionToken(): string
    {
        return now()->format('YmdHisv');
    }
}
