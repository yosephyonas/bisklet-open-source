<?php

namespace App\Services;

use App\Enums\RideStatus;
use App\Models\Ride;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LockTrackingService
{
    public function handleRideStarted(string $imei): void
    {
        $this->applyTrackInterval($imei, $this->rideInterval(), 'ride_started');
    }

    public function handleRideEnded(string $imei): void
    {
        Cache::put($this->key($imei, 'ride_ended_at'), now()->getTimestamp(), $this->cacheTtl());
        $this->applyTrackInterval($imei, $this->parkedInterval(), 'ride_ended');
    }

    public function handlePhoneLocation(string $imei, ?string $capturedAt = null): void
    {
        $timestamp = $capturedAt ? Carbon::parse($capturedAt)->getTimestamp() : now()->getTimestamp();
        Cache::put($this->key($imei, 'phone_last_at'), $timestamp, $this->cacheTtl());
        $this->applyTrackInterval($imei, $this->rideInterval(), 'phone_location');
    }

    public function handleAlarm(string $imei): void
    {
        $until = now()->addSeconds($this->alarmBoostSeconds());
        Cache::put($this->key($imei, 'alarm_until'), $until->getTimestamp(), $this->cacheTtl());
        $this->applyTrackInterval($imei, $this->alarmInterval(), 'alarm');
    }

    public function handleLockTelemetry(string $imei): void
    {
        $interval = $this->resolveDesiredInterval($imei);
        if ($interval === null) {
            return;
        }
        $this->applyTrackInterval($imei, $interval, 'lock_telemetry');
    }

    private function resolveDesiredInterval(string $imei): ?int
    {
        $alarmUntil = Cache::get($this->key($imei, 'alarm_until'));
        if ($alarmUntil && $this->timestampIsFuture($alarmUntil)) {
            return $this->alarmInterval();
        }

        $activeRide = Ride::query()
            ->where('imei', $imei)
            ->where('status', RideStatus::Active->value)
            ->latest('started_at')
            ->first();

        if ($activeRide) {
            $phoneLast = $this->resolvePhoneLastAt($imei, $activeRide);
            if ($phoneLast !== null && $this->timestampDiffSeconds($phoneLast) <= $this->missingPhoneAfter()) {
                return $this->rideInterval();
            }
            if ($activeRide->started_at && $activeRide->started_at->diffInSeconds(now()) <= $this->missingPhoneAfter()) {
                return $this->rideInterval();
            }
            return $this->missingPhoneInterval();
        }

        $lastEndedAt = Cache::get($this->key($imei, 'ride_ended_at'));
        if (!$lastEndedAt) {
            $lastEndedAt = $this->resolveLastRideEndedAt($imei);
        }

        if ($lastEndedAt && $this->timestampDiffSeconds($lastEndedAt) <= $this->idleAfter()) {
            return $this->parkedInterval();
        }

        return $this->idleInterval();
    }

    private function resolvePhoneLastAt(string $imei, Ride $ride): ?int
    {
        $cached = Cache::get($this->key($imei, 'phone_last_at'));
        if ($cached) {
            return (int) $cached;
        }

        $telemetry = $ride->telemetry;
        $updatedAt = is_array($telemetry) ? Arr::get($telemetry, 'user_location_updated_at') : null;
        if ($updatedAt) {
            $timestamp = Carbon::parse($updatedAt)->getTimestamp();
            Cache::put($this->key($imei, 'phone_last_at'), $timestamp, $this->cacheTtl());
            return $timestamp;
        }

        return null;
    }

    private function resolveLastRideEndedAt(string $imei): ?int
    {
        $lastRide = Ride::query()
            ->where('imei', $imei)
            ->where('status', RideStatus::Completed->value)
            ->latest('ended_at')
            ->first();

        if (!$lastRide || !$lastRide->ended_at) {
            return null;
        }

        $timestamp = $lastRide->ended_at->getTimestamp();
        Cache::put($this->key($imei, 'ride_ended_at'), $timestamp, $this->cacheTtl());

        return $timestamp;
    }

    private function applyTrackInterval(string $imei, int $intervalSeconds, string $reason): void
    {
        $intervalSeconds = max(0, $intervalSeconds);
        $lastInterval = Cache::get($this->key($imei, 'track_interval'));
        $lastSentAt = Cache::get($this->key($imei, 'track_last_sent'));
        $minGap = $this->commandMinInterval();

        if ($lastInterval !== null && (int) $lastInterval === $intervalSeconds && $lastSentAt) {
            if ($this->timestampDiffSeconds($lastSentAt) < $minGap) {
                return;
            }
        }

        $gatewayUrl = rtrim(config('services.go_gateway.base_url', 'http://127.0.0.1:9001'), '/');
        $requestId = (string) Str::ulid();

        try {
            Http::timeout(5)->post($gatewayUrl . '/commands/track', [
                'imei' => $imei,
                'interval_seconds' => $intervalSeconds,
                'request_id' => $requestId,
            ]);

            Cache::put($this->key($imei, 'track_interval'), $intervalSeconds, $this->cacheTtl());
            Cache::put($this->key($imei, 'track_last_sent'), now()->getTimestamp(), $this->cacheTtl());

            Log::info('lock.track.command', [
                'imei' => $imei,
                'interval_seconds' => $intervalSeconds,
                'reason' => $reason,
                'request_id' => $requestId,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('lock.track.command_failed', [
                'imei' => $imei,
                'interval_seconds' => $intervalSeconds,
                'reason' => $reason,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function timestampDiffSeconds(int $timestamp): int
    {
        return (int) abs(now()->getTimestamp() - $timestamp);
    }

    private function timestampIsFuture(int $timestamp): bool
    {
        return $timestamp > now()->getTimestamp();
    }

    private function key(string $imei, string $suffix): string
    {
        return "lock.track.{$suffix}.{$imei}";
    }

    private function cacheTtl(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    private function rideInterval(): int
    {
        return (int) config('services.go_gateway.track_interval_ride', 120);
    }

    private function missingPhoneInterval(): int
    {
        return (int) config('services.go_gateway.track_interval_missing_phone', 25);
    }

    private function missingPhoneAfter(): int
    {
        return (int) config('services.go_gateway.track_missing_phone_after', 60);
    }

    private function parkedInterval(): int
    {
        return (int) config('services.go_gateway.track_interval_parked', 600);
    }

    private function idleInterval(): int
    {
        return (int) config('services.go_gateway.track_interval_idle', 1800);
    }

    private function idleAfter(): int
    {
        return (int) config('services.go_gateway.track_idle_after', 1800);
    }

    private function alarmInterval(): int
    {
        return (int) config('services.go_gateway.track_interval_alarm', 20);
    }

    private function alarmBoostSeconds(): int
    {
        return (int) config('services.go_gateway.track_alarm_boost_seconds', 120);
    }

    private function commandMinInterval(): int
    {
        return (int) config('services.go_gateway.track_command_min_interval', 30);
    }
}
