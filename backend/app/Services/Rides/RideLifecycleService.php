<?php

namespace App\Services\Rides;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Models\Bike;
use App\Models\Device;
use App\Models\DeviceTelemetry;
use App\Models\LockCommand;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RideLifecycleService
{
    public function findUserActiveRide(int $userId): ?Ride
    {
        return Ride::query()
            ->with(['bike', 'startStation', 'endStation', 'payments', 'device', 'user'])
            ->where('user_id', $userId)
            ->where('status', RideStatus::Active->value)
            ->latest('started_at')
            ->first();
    }

    public function findActiveRideForLock(string $imei, ?int $bikeId): ?Ride
    {
        return Ride::query()
            ->where('status', RideStatus::Active->value)
            ->where(function ($query) use ($imei, $bikeId) {
                $query
                    ->where('imei', $imei)
                    ->orWhere('active_imei', $imei);

                if ($bikeId !== null) {
                    $query
                        ->orWhere('bike_id', $bikeId)
                        ->orWhere('active_bike_id', $bikeId);
                }
            })
            ->latest('started_at')
            ->first();
    }

    public function startRideFromUnlock(
        string $imei,
        int $userId,
        ?LockCommand $command,
        Carbon $startedAt,
    ): ?Ride {
        return DB::transaction(function () use ($imei, $userId, $command, $startedAt) {
            User::query()
                ->whereKey($userId)
                ->lockForUpdate()
                ->first();

            $device = Device::query()
                ->where('imei', $imei)
                ->lockForUpdate()
                ->first();

            if ($device === null) {
                return null;
            }

            $bike = Bike::query()
                ->where('imei', $imei)
                ->lockForUpdate()
                ->first();

            $startStationId = $bike?->station_id;

            $existingRide = Ride::query()
                ->where('user_id', $userId)
                ->where('status', RideStatus::Active->value)
                ->orderByDesc('started_at')
                ->lockForUpdate()
                ->first();

            if ($existingRide !== null && $existingRide->imei !== null && $existingRide->imei !== $imei) {
                Log::warning('ride.start.conflict_active_ride_exists', [
                    'user_id' => $userId,
                    'requested_imei' => $imei,
                    'active_ride_id' => $existingRide->id,
                    'active_ride_imei' => $existingRide->imei,
                ]);

                return $existingRide->load(['bike', 'startStation', 'endStation', 'payments', 'device', 'user']);
            }

            $device->lock_status = 0;
            $device->last_seen_at = $startedAt;
            $device->save();

            if ($bike) {
                $bike->status = BikeStatus::InRide->value;
                $bike->station_id = null;
                $bike->save();
            }

            $ride = $existingRide;

            if ($ride === null) {
                $ride = new Ride;
                $ride->user_id = $userId;
                $ride->imei = $imei;
                $ride->status = RideStatus::Active->value;
                $ride->started_at = $startedAt;
                $ride->start_command_id = $command?->id;
                $ride->start_latitude = $device->last_lat;
                $ride->start_longitude = $device->last_lng;
                $ride->billing_status = 'unbilled';
                $ride->currency = 'ETB';
            }

            if ($bike) {
                $ride->bike_id = $bike->id;
                $ride->start_station_id = $ride->start_station_id ?? $startStationId;
            }

            $pricing = RidePricing::snapshot();
            $ride->per_minute_rate = $pricing['per_minute_rate'];
            $ride->unlock_fee = $pricing['unlock_fee'];
            $ride->pricing_breakdown = $pricing;

            $ride->save();

            return $ride->load(['bike', 'startStation', 'endStation', 'payments', 'device', 'user']);
        });
    }

    public function completeRideFromLock(
        string $imei,
        int $userId,
        int $rideMinutes,
        ?DeviceTelemetry $telemetry,
        Carbon $endedAt,
    ): ?Ride {
        return DB::transaction(function () use ($imei, $userId, $rideMinutes, $telemetry, $endedAt) {
            $ride = Ride::query()
                ->where('imei', $imei)
                ->where('status', RideStatus::Active->value)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($ride === null) {
                return null;
            }

            $bike = $ride->bike_id
                ? Bike::query()->whereKey($ride->bike_id)->lockForUpdate()->first()
                : Bike::query()->where('imei', $imei)->lockForUpdate()->first();

            $device = Device::query()
                ->where('imei', $imei)
                ->lockForUpdate()
                ->first();

            $durationSeconds = $ride->started_at
                ? max(1, (int) $ride->started_at->diffInSeconds($endedAt))
                : null;
            $effectiveMinutes = $durationSeconds !== null
                ? max(1, (int) ceil($durationSeconds / 60))
                : ($rideMinutes > 0
                    ? $rideMinutes
                    : ($ride->minutes && $ride->minutes > 0 ? (int) $ride->minutes : 0));
            $pricing = RidePricing::calculate(
                $effectiveMinutes,
                is_array($ride->pricing_breakdown) ? $ride->pricing_breakdown : null,
            );

            $ride->status = RideStatus::Completed->value;
            if ($ride->bike_id === null && $bike) {
                $ride->bike_id = $bike->id;
            }
            $ride->minutes = $effectiveMinutes > 0 ? $effectiveMinutes : $ride->minutes;
            $ride->ended_at = $endedAt;
            $ride->per_minute_rate = $pricing['per_minute_rate'];
            $ride->unlock_fee = $pricing['unlock_fee'];
            $ride->duration_seconds = $durationSeconds ?? $ride->duration_seconds;
            $ride->total_amount = $pricing['total_amount'];
            $ride->currency = $ride->currency ?? 'ETB';
            $ride->pricing_breakdown = $pricing;
            $ride->end_latitude = $device?->last_lat;
            $ride->end_longitude = $device?->last_lng;
            if ($telemetry) {
                $ride->end_event_id = $telemetry->id;
            }
            $ride->save();

            if ($device) {
                $device->lock_status = 1;
                $device->last_seen_at = $endedAt;
                $device->save();
            }

            if ($bike) {
                $bike->status = BikeStatus::Available->value;
                $bike->station_id = $ride->end_station_id ?? $bike->station_id;
                $bike->save();
            }

            return $ride->load(['bike', 'startStation', 'endStation', 'payments', 'device', 'user']);
        });
    }
}
