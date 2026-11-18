<?php

namespace App\Services;

use App\Models\ExclusiveDeal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ExclusiveDealsCacheService
{
    public function sanitizeLimit(int $limit): int
    {
        return min(20, max(1, $limit));
    }

    public function readOrWarm(int $limit, int $cacheTtlSeconds = 60): array
    {
        $limit = $this->sanitizeLimit($limit);
        $keys = $this->keys($limit);

        $cached = Cache::get($keys['cache']);
        if (! is_array($cached)) {
            $lock = Cache::lock($keys['lock'], 8);
            $acquired = false;

            try {
                $acquired = $lock->get();
                if ($acquired) {
                    $cached = Cache::get($keys['cache']);
                    if (! is_array($cached)) {
                        $cached = $this->buildExclusiveDealsCachePayload($limit, $cacheTtlSeconds);
                        Cache::put($keys['cache'], $cached, now()->addSeconds($cacheTtlSeconds));
                    }
                } else {
                    $cached = Cache::get($keys['stale']);
                }
            } finally {
                if ($acquired) {
                    $lock->release();
                }
            }
        }

        if (! is_array($cached)) {
            $cached = $this->buildExclusiveDealsCachePayload($limit, $cacheTtlSeconds);
            Cache::put($keys['cache'], $cached, now()->addSeconds($cacheTtlSeconds));
        }

        // Keep a longer-lived stale copy so clients can still get fast responses during lock contention.
        Cache::put($keys['stale'], $cached, now()->addMinutes(10));

        return $cached;
    }

    /**
     * @param  array<int|string>  $limits
     * @return array<int>
     */
    public function warmMany(array $limits, int $cacheTtlSeconds = 60): array
    {
        $normalized = [];
        foreach ($limits as $raw) {
            if (! is_numeric($raw)) {
                continue;
            }

            $limit = $this->sanitizeLimit((int) $raw);
            $normalized[$limit] = true;
        }

        if ($normalized === []) {
            $normalized[8] = true;
        }

        $warmed = [];
        foreach (array_keys($normalized) as $limit) {
            $this->warm($limit, $cacheTtlSeconds);
            $warmed[] = $limit;
        }

        sort($warmed);

        return $warmed;
    }

    public function warm(int $limit, int $cacheTtlSeconds = 60): array
    {
        $limit = $this->sanitizeLimit($limit);
        $keys = $this->keys($limit);

        $cached = $this->buildExclusiveDealsCachePayload($limit, $cacheTtlSeconds);
        Cache::put($keys['cache'], $cached, now()->addSeconds($cacheTtlSeconds));
        Cache::put($keys['stale'], $cached, now()->addMinutes(10));

        return $cached;
    }

    /**
     * @return array{cache:string,stale:string,lock:string}
     */
    private function keys(int $limit): array
    {
        $version = ExclusiveDeal::cacheVersion();
        $cacheKey = sprintf('exclusive_deals:v2:version:%s:limit:%d', $version, $limit);

        return [
            'cache' => $cacheKey,
            'stale' => $cacheKey . ':stale',
            'lock' => $cacheKey . ':lock',
        ];
    }

    private function buildExclusiveDealsCachePayload(int $limit, int $cacheTtlSeconds): array
    {
        $now = now();

        $deals = ExclusiveDeal::query()
            ->select([
                'id',
                'title',
                'subtitle',
                'badge',
                'cta_label',
                'deeplink',
                'image_url',
                'starts_at',
                'ends_at',
                'priority',
                'updated_at',
            ])
            ->where('is_exclusive', true)
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();

        $latestUnix = (int) $deals
            ->map(fn (ExclusiveDeal $deal): int => $deal->updated_at?->getTimestamp() ?? 0)
            ->max();
        $latestModified = $latestUnix > 0
            ? Carbon::createFromTimestampUTC($latestUnix)->toRfc7231String()
            : null;

        $serializedDeals = $deals
            ->map(function (ExclusiveDeal $deal): array {
                return [
                    'id' => $deal->id,
                    'title' => $deal->title,
                    'subtitle' => $deal->subtitle,
                    'badge' => $deal->badge,
                    'cta_label' => $deal->cta_label,
                    'deeplink' => $deal->deeplink,
                    'image_url' => $deal->image_url,
                    'starts_at' => optional($deal->starts_at)->toIso8601String(),
                    'ends_at' => optional($deal->ends_at)->toIso8601String(),
                    'priority' => $deal->priority,
                ];
            })
            ->values()
            ->all();

        $payload = [
            'data' => $serializedDeals,
            'meta' => [
                'count' => count($serializedDeals),
                'cache_ttl_seconds' => $cacheTtlSeconds,
                'fetched_at' => now()->toIso8601String(),
            ],
        ];

        return [
            'payload' => $payload,
            'etag' => '"' . sha1((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"',
            'last_modified' => $latestModified,
        ];
    }
}
