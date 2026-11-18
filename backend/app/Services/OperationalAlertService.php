<?php

namespace App\Services;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Models\Bike;
use App\Models\DamageReport;
use App\Models\Device;
use App\Models\OperationalAlert;
use App\Models\Ride;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OperationalAlertService
{
    public function recordLockAlarm(array $payload): ?OperationalAlert
    {
        $imei = Arr::get($payload, 'imei');
        if (! $imei) {
            return null;
        }

        $bike = Bike::query()->where('imei', $imei)->first();
        $device = Device::query()->where('imei', $imei)->first();
        $ride = $this->activeRideForImei($imei);
        $alarmType = (int) Arr::get($payload, 'type', 0);

        if ($alarmType === 6) {
            $this->resolveOpenAlerts($imei, 'lock_alarm', ['resolved_by' => 'alarm_cleared']);

            return null;
        }

        $isLocked = (int) Arr::get($payload, 'lock_status', $device?->lock_status ?? 1) === 1;
        $sourceLabel = match ($alarmType) {
            1 => $isLocked ? 'Locked bike movement detected' : 'Illegal movement detected',
            2 => 'Fall alarm detected',
            default => 'Lock alarm triggered',
        };
        $severity = match ($alarmType) {
            1 => $isLocked ? 'critical' : 'warning',
            2 => 'critical',
            default => 'warning',
        };

        $alert = $this->firstOpenOrCreate([
            'source' => 'lock_alarm',
            'imei' => $imei,
            'bike_id' => $bike?->id,
            'ride_id' => $ride?->id,
            'user_id' => $ride?->user_id,
            'severity' => $severity,
            'title' => $sourceLabel,
            'body' => sprintf(
                '%s for %s.',
                $sourceLabel,
                $bike?->code ? "bike {$bike->code}" : "lock {$imei}"
            ),
            'latitude' => $this->numericOrNull(Arr::get($payload, 'lat', Arr::get($payload, 'payload.lat'))),
            'longitude' => $this->numericOrNull(Arr::get($payload, 'lng', Arr::get($payload, 'payload.lng'))),
            'metadata' => [
                'alarm_type' => $alarmType,
                'locked' => $isLocked,
                'payload' => $payload,
            ],
            'occurred_at' => $this->parseTimestamp(Arr::get($payload, 'received_at')),
        ]);

        $this->notifyAdmins($alert);

        return $alert;
    }

    public function syncDeviceHealth(Device $device): void
    {
        if ($device->imei) {
            $this->resolveOpenAlerts($device->imei, 'offline_lock', ['resolved_by' => 'device_seen']);
        }

        $threshold = $this->lowBatteryThreshold();
        if ($device->battery_percent === null) {
            return;
        }

        if ((int) $device->battery_percent <= $threshold) {
            $this->recordLowBattery($device);

            return;
        }

        $this->resolveOpenAlerts($device->imei, 'low_battery', [
            'resolved_by' => 'battery_recovered',
            'battery_percent' => (int) $device->battery_percent,
        ]);
    }

    public function recordLowBattery(Device $device): ?OperationalAlert
    {
        if (! $device->imei || $device->battery_percent === null) {
            return null;
        }

        $threshold = $this->lowBatteryThreshold();
        if ((int) $device->battery_percent > $threshold) {
            return null;
        }

        $bike = Bike::query()->where('imei', $device->imei)->first();
        $alert = $this->firstOpenOrCreate([
            'source' => 'low_battery',
            'imei' => $device->imei,
            'bike_id' => $bike?->id,
            'severity' => (int) $device->battery_percent <= 10 ? 'critical' : 'warning',
            'title' => 'Low lock battery',
            'body' => sprintf(
                '%s battery is at %d%%.',
                $bike?->code ? "Bike {$bike->code}" : "Lock {$device->imei}",
                (int) $device->battery_percent
            ),
            'latitude' => $device->last_lat,
            'longitude' => $device->last_lng,
            'metadata' => [
                'battery_percent' => (int) $device->battery_percent,
                'battery_voltage' => $device->battery_voltage,
                'threshold' => $threshold,
            ],
            'occurred_at' => now(),
        ]);

        $this->notifyAdmins($alert);
        $this->notifyActiveRiderLowBattery($device, $alert);

        return $alert;
    }

    public function recordBikeLowBattery(Bike $bike): ?OperationalAlert
    {
        if ($bike->battery_level === null || (float) $bike->battery_level > $this->lowBatteryThreshold()) {
            return null;
        }

        $alert = $this->firstOpenOrCreate([
            'source' => 'low_battery',
            'imei' => $bike->imei,
            'bike_id' => $bike->id,
            'severity' => (float) $bike->battery_level <= 10 ? 'critical' : 'warning',
            'title' => 'Low bike battery',
            'body' => sprintf('Bike %s battery is at %.0f%%.', $bike->code, (float) $bike->battery_level),
            'latitude' => $bike->last_latitude,
            'longitude' => $bike->last_longitude,
            'metadata' => [
                'battery_percent' => (float) $bike->battery_level,
                'threshold' => $this->lowBatteryThreshold(),
                'source' => 'bike_telemetry',
            ],
            'occurred_at' => now(),
        ]);

        $this->notifyAdmins($alert);

        return $alert;
    }

    public function recordOfflineDevice(Device $device, Carbon $cutoff): ?OperationalAlert
    {
        if (! $device->imei) {
            return null;
        }

        $bike = Bike::query()->where('imei', $device->imei)->first();

        $alert = $this->firstOpenOrCreate([
            'source' => 'offline_lock',
            'imei' => $device->imei,
            'bike_id' => $bike?->id,
            'severity' => 'warning',
            'title' => 'Lock offline',
            'body' => sprintf(
                '%s has not reported since %s.',
                $bike?->code ? "Bike {$bike->code}" : "Lock {$device->imei}",
                optional($device->last_seen_at)->toDateTimeString() ?? 'registration'
            ),
            'latitude' => $device->last_lat,
            'longitude' => $device->last_lng,
            'metadata' => [
                'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
                'offline_cutoff' => $cutoff->toIso8601String(),
            ],
            'occurred_at' => $device->last_seen_at ?? now(),
        ]);

        $this->notifyAdmins($alert);

        return $alert;
    }

    public function recordDamageReport(DamageReport $report): OperationalAlert
    {
        $report->loadMissing(['bike', 'ride']);
        $severity = in_array($report->severity, ['major', 'critical'], true) || $report->affects_safety
            ? 'critical'
            : 'warning';

        $alert = $this->firstOpenOrCreate([
            'source' => 'damage_report',
            'imei' => $report->bike?->imei,
            'bike_id' => $report->bike_id,
            'ride_id' => $report->ride_id,
            'user_id' => $report->user_id,
            'severity' => $severity,
            'title' => 'Bike damage reported',
            'body' => sprintf(
                '%s reported %s damage on bike %s.',
                $report->user?->first_name ?: 'A rider',
                str_replace('_', ' ', $report->category),
                $report->bike?->code ?? (string) $report->bike_id
            ),
            'latitude' => $report->latitude,
            'longitude' => $report->longitude,
            'metadata' => [
                'damage_report_id' => $report->id,
                'maintenance_log_id' => $report->maintenance_log_id,
                'category' => $report->category,
                'damage_severity' => $report->severity,
                'affects_safety' => $report->affects_safety,
                'photo_urls' => $report->photo_urls,
            ],
            'occurred_at' => $report->created_at ?? now(),
        ]);

        $this->notifyAdmins($alert);

        return $alert;
    }

    public function resolveOpenAlerts(string $imei, string $source, array $metadata = []): int
    {
        $alerts = OperationalAlert::query()
            ->where('imei', $imei)
            ->where('source', $source)
            ->where('status', 'open')
            ->get();

        foreach ($alerts as $alert) {
            $alert->status = 'resolved';
            $alert->resolved_at = now();
            $alert->metadata = array_merge($alert->metadata ?? [], $metadata);
            $alert->save();
        }

        return $alerts->count();
    }

    private function firstOpenOrCreate(array $attributes): OperationalAlert
    {
        $query = OperationalAlert::query()
            ->where('source', $attributes['source'])
            ->where('status', 'open');

        if (! empty($attributes['imei'])) {
            $query->where('imei', $attributes['imei']);
        } elseif (! empty($attributes['bike_id'])) {
            $query->where('bike_id', $attributes['bike_id']);
        }

        if ($attributes['source'] === 'damage_report') {
            $query->where('metadata->damage_report_id', Arr::get($attributes, 'metadata.damage_report_id'));
        }

        $existing = $query->first();
        if ($existing) {
            $existing->fill([
                'severity' => $this->maxSeverity($existing->severity, $attributes['severity']),
                'title' => $attributes['title'],
                'body' => $attributes['body'],
                'latitude' => $attributes['latitude'] ?? $existing->latitude,
                'longitude' => $attributes['longitude'] ?? $existing->longitude,
                'metadata' => array_merge($existing->metadata ?? [], $attributes['metadata'] ?? []),
                'occurred_at' => $attributes['occurred_at'] ?? $existing->occurred_at,
            ]);
            $existing->save();

            return $existing;
        }

        return OperationalAlert::query()->create(array_merge($attributes, [
            'id' => (string) Str::ulid(),
            'status' => 'open',
        ]));
    }

    private function notifyAdmins(OperationalAlert $alert): void
    {
        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->get();

        foreach ($admins as $admin) {
            $exists = UserNotification::query()
                ->where('user_id', $admin->id)
                ->where('type', 'operational_alert')
                ->where('data->alert_id', $alert->id)
                ->exists();

            if ($exists) {
                continue;
            }

            UserNotification::query()->create([
                'id' => (string) Str::ulid(),
                'user_id' => $admin->id,
                'ride_id' => $alert->ride_id,
                'imei' => $alert->imei,
                'type' => 'operational_alert',
                'title' => $alert->title,
                'body' => $alert->body ?? '',
                'data' => [
                    'alert_id' => $alert->id,
                    'source' => $alert->source,
                    'severity' => $alert->severity,
                    'bike_id' => $alert->bike_id,
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function notifyActiveRiderLowBattery(Device $device, OperationalAlert $alert): void
    {
        $ride = $this->activeRideForImei($device->imei);
        if (! $ride?->user_id) {
            return;
        }

        $exists = UserNotification::query()
            ->where('user_id', $ride->user_id)
            ->where('type', 'low_battery')
            ->where('ride_id', $ride->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($exists) {
            return;
        }

        UserNotification::query()->create([
            'id' => (string) Str::ulid(),
            'user_id' => $ride->user_id,
            'ride_id' => $ride->id,
            'imei' => $device->imei,
            'type' => 'low_battery',
            'title' => 'Low bike battery',
            'body' => sprintf('This bike battery is low (%d%%). Please park safely soon.', (int) $device->battery_percent),
            'data' => [
                'alert_id' => $alert->id,
                'battery_percent' => (int) $device->battery_percent,
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function activeRideForImei(?string $imei): ?Ride
    {
        if (! $imei) {
            return null;
        }

        return Ride::query()
            ->where('status', RideStatus::Active->value)
            ->where(function ($query) use ($imei) {
                $query->where('imei', $imei)
                    ->orWhere('active_imei', $imei);
            })
            ->orderByDesc('created_at')
            ->first();
    }

    private function lowBatteryThreshold(): int
    {
        return max(0, min(100, (int) config('services.locks.low_battery_threshold', 20)));
    }

    private function maxSeverity(string $current, string $next): string
    {
        $rank = ['info' => 0, 'warning' => 1, 'critical' => 2];

        return ($rank[$next] ?? 1) > ($rank[$current] ?? 1) ? $next : $current;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function parseTimestamp(?string $value): Carbon
    {
        if (! $value) {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }
    }
}
