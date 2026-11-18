<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use App\Services\ExclusiveDealsCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->query('scope') === 'exclusive_deals') {
            return $this->exclusiveDeals($request);
        }

        $user = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $notifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return UserNotificationResource::collection($notifications)->response();
    }

    private function exclusiveDeals(Request $request): JsonResponse
    {
        /** @var ExclusiveDealsCacheService $cacheService */
        $cacheService = app(ExclusiveDealsCacheService::class);

        $limit = $cacheService->sanitizeLimit((int) $request->query('limit', 8));
        $cacheTtlSeconds = 60;
        $cached = $cacheService->readOrWarm($limit, $cacheTtlSeconds);

        $payload = $cached['payload'] ?? ['data' => [], 'meta' => []];
        $etag = (string) ($cached['etag'] ?? '');
        $lastModified = $cached['last_modified'] ?? null;
        if (! is_string($lastModified) || trim($lastModified) === '') {
            $lastModified = null;
        }

        if ($etag === '') {
            $etag = '"' . sha1((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"';
        }

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => sprintf(
                'private, max-age=%d, stale-while-revalidate=%d',
                $cacheTtlSeconds,
                $cacheTtlSeconds * 2
            ),
            'Vary' => 'Authorization, Accept',
        ];
        if ($lastModified !== null) {
            $headers['Last-Modified'] = $lastModified;
        }

        if (
            $this->matchesEtag((string) $request->header('If-None-Match'), $etag)
            || $this->matchesLastModified($request, $lastModified)
        ) {
            return response()->json(null, Response::HTTP_NOT_MODIFIED, $headers);
        }

        return response()->json($payload, Response::HTTP_OK, $headers);
    }

    private function matchesEtag(string $ifNoneMatchHeader, string $etag): bool
    {
        $header = trim($ifNoneMatchHeader);
        if ($header === '') {
            return false;
        }

        $current = $this->normalizeEtagToken($etag);
        $tokens = array_map('trim', explode(',', $header));

        foreach ($tokens as $token) {
            if ($token === '*') {
                return true;
            }

            if ($this->normalizeEtagToken($token) === $current) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEtagToken(string $etag): string
    {
        $value = trim($etag);

        if (str_starts_with($value, 'W/')) {
            $value = substr($value, 2);
        }

        return trim($value, "\"'");
    }

    private function matchesLastModified(Request $request, ?string $lastModified): bool
    {
        if ($lastModified === null) {
            return false;
        }

        $header = trim((string) $request->header('If-Modified-Since'));
        if ($header === '') {
            return false;
        }

        $requestTime = strtotime($header);
        $resourceTime = strtotime($lastModified);
        if ($requestTime === false || $resourceTime === false) {
            return false;
        }

        return $requestTime >= $resourceTime;
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id !== $user->id && $user->role !== UserRole::Admin) {
            abort(403, 'You are not allowed to update this notification.');
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return UserNotificationResource::make($notification)->response();
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
