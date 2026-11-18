<?php

namespace App\Services;

use App\Enums\RideStatus;
use App\Models\Ride;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NotificationService
{
    public function notifyRideEnded(Ride $ride): ?UserNotification
    {
        if (!$ride->user_id) {
            return null;
        }

        $existing = UserNotification::query()
            ->where('user_id', $ride->user_id)
            ->where('type', 'ride_ended')
            ->where('ride_id', $ride->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $user = $ride->user()->first();
        if (!$user) {
            return null;
        }

        $bike = $ride->bike()->first();
        $bikeLabel = $bike?->code ?? $ride->imei ?? 'your bike';
        $currency = $ride->currency ?? 'ETB';
        $total = $ride->total_amount ?? 0.0;

        $title = 'Ride completed';
        $body = sprintf(
            'Your ride on %s has ended. Total cost: %s %.2f.',
            $bikeLabel,
            $currency,
            $total
        );

        $notification = $this->createNotification(
            user: $user,
            type: 'ride_ended',
            title: $title,
            body: $body,
            rideId: $ride->id,
            imei: $ride->imei,
            data: [
                'ride_id' => $ride->id,
                'bike_code' => $bike?->code,
                'total_amount' => $total,
                'currency' => $currency,
                'minutes' => $ride->minutes,
                'ended_at' => optional($ride->ended_at)->toIso8601String(),
            ],
        );

        $this->maybeNotifyLowBalance($user);

        return $notification;
    }

    public function notifyAlarmTriggered(string $imei): ?UserNotification
    {
        $ride = Ride::query()
            ->where('imei', $imei)
            ->where('status', RideStatus::Active->value)
            ->orderByDesc('created_at')
            ->first();

        if (!$ride || !$ride->user_id) {
            return null;
        }

        $recentAlarm = UserNotification::query()
            ->where('user_id', $ride->user_id)
            ->where('type', 'alarm')
            ->where('ride_id', $ride->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($recentAlarm) {
            return $recentAlarm;
        }

        $user = $ride->user()->first();
        if (!$user) {
            return null;
        }

        $bike = $ride->bike()->first();
        $bikeLabel = $bike?->code ?? $imei;

        return $this->createNotification(
            user: $user,
            type: 'alarm',
            title: 'Alarm triggered',
            body: sprintf('We detected movement on bike %s. Check your ride status.', $bikeLabel),
            rideId: $ride->id,
            imei: $imei,
            data: [
                'ride_id' => $ride->id,
                'bike_code' => $bike?->code,
                'imei' => $imei,
            ],
        );
    }

    public function maybeNotifyLowBalance(User $user): ?UserNotification
    {
        $metadata = $user->metadata ?? [];
        if (!is_array($metadata)) {
            return null;
        }

        $balance = Arr::get($metadata, 'wallet_balance');
        if (!is_numeric($balance)) {
            return null;
        }

        $threshold = (float) config('services.wallet.low_balance_threshold', 50);
        if ($balance > $threshold) {
            return null;
        }

        $lastNotifiedAt = Arr::get($metadata, 'low_balance_notified_at');
        if (is_string($lastNotifiedAt)) {
            try {
                $last = \Carbon\Carbon::parse($lastNotifiedAt);
                if ($last->greaterThanOrEqualTo(now()->subDay())) {
                    return null;
                }
            } catch (\Throwable $e) {
                // ignore parse errors
            }
        }

        $notification = $this->createNotification(
            user: $user,
            type: 'low_balance',
            title: 'Low balance',
            body: sprintf('Your wallet balance is low (ETB %.2f). Add funds to keep riding.', (float) $balance),
            data: [
                'balance' => (float) $balance,
                'threshold' => $threshold,
            ],
        );

        $metadata['low_balance_notified_at'] = now()->toIso8601String();
        $user->metadata = $metadata;
        $user->save();

        return $notification;
    }

    private function createNotification(
        User $user,
        string $type,
        string $title,
        string $body,
        ?int $rideId = null,
        ?string $imei = null,
        array $data = [],
    ): UserNotification {
        return UserNotification::query()->create([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'ride_id' => $rideId,
            'imei' => $imei,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
