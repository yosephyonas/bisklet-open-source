<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceTelemetry;
use App\Models\LockCommand;
use App\Models\GatewayEventReceipt;
use App\Models\LockEvent;
use App\Models\LockLocation;
use App\Models\LockStatus;
use App\Models\BleKey;
use App\Models\FirmwareUpdate;
use App\Models\Bike;
use App\Services\LockTrackingService;
use App\Services\NotificationService;
use App\Services\OperationalAlertService;
use App\Services\Rides\RideLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class LockEventController extends Controller
{
    public function __construct(
        private readonly LockTrackingService $trackingService,
        private readonly NotificationService $notificationService,
        private readonly OperationalAlertService $alertService,
        private readonly RideLifecycleService $rides,
    )
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', 'max:100'],
            'event_id' => ['nullable', 'string', 'max:191'],
            'payload' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'string'],
        ]);

        $event = $data['event'];
        $payload = $data['payload'] ?? [];
        $receipt = $this->recordGatewayReceipt(
            event: $event,
            payload: $payload,
            eventId: $data['event_id'] ?? null,
            occurredAt: $data['occurred_at'] ?? null,
        );

        if ($receipt === null) {
            return response()->json(['status' => 'duplicate']);
        }

        Log::info('lock.event.received', [
            'event' => $event,
            'imei' => Arr::get($payload, 'imei'),
            'payload' => $payload,
        ]);

        try {
            switch ($event) {
                case 'command.unlock.result':
                    $this->handleUnlockResult($payload);
                    break;
                case 'command.unlock.timeout':
                    $this->handleUnlockFailure($payload, 'timeout');
                    break;
                case 'command.unlock.offline':
                    $this->handleUnlockFailure($payload, 'offline');
                    break;
                case 'telemetry.heartbeat':
                    $this->handleHeartbeat($payload);
                    break;
                case 'telemetry.status':
                    $this->handleStatusTelemetry($payload);
                    break;
                case 'telemetry.gps':
                    $this->handleGpsTelemetry($payload);
                    break;
                case 'telemetry.wifi_positioning':
                    $this->handleWifiPositioningTelemetry($payload);
                    break;
                case 'command.track.result':
                    $this->handleTrackResult($payload);
                    break;
                case 'alarm.triggered':
                    $this->handleAlarm($payload);
                    break;
                case 'command.ring.result':
                    $this->storeLockEvent($payload, 'command.ring.result');
                    break;
                case 'command.status.result':
                    $this->storeLockEvent($payload, 'command.status.result');
                    break;
                case 'command.external_lock.result':
                    $this->handleExternalLockResult($payload);
                    break;
                case 'device.info':
                    $this->handleDeviceInfo($payload);
                    break;
                case 'device.cable_lock_info':
                    $this->handleCableLockInfo($payload);
                    break;
                case 'device.iccid':
                    $this->handleSimInfo($payload);
                    break;
                case 'device.ble_mac':
                    $this->handleBleMac($payload);
                    break;
                case 'device.ble_key':
                    $this->handleBleKey($payload);
                    break;
                case 'beacon.scan.result':
                    $this->handleBeaconScanResult($payload);
                    break;
                case 'rfid.unlock.request':
                    $this->handleRfidUnlockRequest($payload);
                    break;
                case 'rfid.admin_card.result':
                    $this->handleRfidAdminCardResult($payload);
                    break;
                case 'ota.status':
                    $this->handleOtaStatus($payload);
                    break;
                case 'lock.event':
                    $this->storeLockEvent($payload, 'lock.event');
                    break;
                case 'ride.ended':
                    $this->handleLockEvent($payload);
                    break;
                case 'lock.l1_received':
                    $this->storeLockEvent($payload, 'lock.l1_received');
                    break;
                case 'device.online':
                    $this->touchDevice($payload);
                    break;
            }

            $receipt->processed_at = now();
            $receipt->failed_at = null;
            $receipt->error_message = null;
            $receipt->save();
        } catch (\Throwable $exception) {
            $receipt->failed_at = now();
            $receipt->error_message = $exception->getMessage();
            $receipt->save();

            throw $exception;
        }

        return response()->json(['status' => 'ok']);
    }

    private function recordGatewayReceipt(
        string $event,
        array $payload,
        ?string $eventId,
        ?string $occurredAt,
    ): ?GatewayEventReceipt {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $eventHash = hash('sha256', $event.'|'.($eventId ?? '').'|'.($occurredAt ?? '').'|'.$payloadJson);

        $existing = GatewayEventReceipt::query()
            ->where('event_hash', $eventHash)
            ->first();

        if ($existing !== null) {
            if ($existing->processed_at !== null) {
                return null;
            }

            $existing->failed_at = null;
            $existing->error_message = null;
            $existing->save();

            return $existing;
        }

        try {
            return GatewayEventReceipt::query()->create([
                'event_hash' => $eventHash,
                'event_id' => $eventId,
                'event' => $event,
                'imei' => Arr::get($payload, 'imei'),
                'payload' => $payload,
                'occurred_at' => $this->parseTimestamp($occurredAt),
                'received_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            $receipt = GatewayEventReceipt::query()
                ->where('event_hash', $eventHash)
                ->first();

            return $receipt?->processed_at === null ? $receipt : null;
        }
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'constraint');
    }

    private function handleUnlockResult(array $payload): void
    {
        $requestId = Arr::get($payload, 'request_id');
        $command = $requestId ? LockCommand::query()->where('request_id', $requestId)->first() : null;
        if ($command === null) {
            return;
        }

        $result = (int) Arr::get($payload, 'result', 1);
        $command->status = $result === 0 ? 'success' : 'failed';
        $command->completed_at = now();
        $command->result_json = $payload;
        $command->save();

        $imei = Arr::get($payload, 'imei');
        if ($imei) {
            DeviceTelemetry::query()->create([
                'imei' => $imei,
                'type' => 'L0',
                'raw_payload' => json_encode($payload),
                'parsed_json' => $payload,
                'device_ts' => Arr::get($payload, 'device_ts'),
                'received_at' => $this->parseTimestamp(Arr::get($payload, 'received_at')),
            ]);

            if ($result === 0) {
                $userId = Arr::get($payload, 'user_id');
                if ($userId) {
                    $this->rides->startRideFromUnlock(
                        imei: $imei,
                        userId: (int) $userId,
                        command: $command,
                        startedAt: now(),
                    );
                } else {
                    $device = Device::query()->firstOrNew(['imei' => $imei]);
                    $device->last_seen_at = now();
                    $device->lock_status = 0;
                    $device->save();
                    $this->alertService->syncDeviceHealth($device);
                }
                $this->trackingService->handleRideStarted($imei);
            } else {
                $device = Device::query()->firstOrNew(['imei' => $imei]);
                $device->last_seen_at = now();
                $device->save();
                $this->alertService->syncDeviceHealth($device);
            }
        }
    }

    private function handleUnlockFailure(array $payload, string $status): void
    {
        $requestId = Arr::get($payload, 'request_id');
        $command = $requestId ? LockCommand::query()->where('request_id', $requestId)->first() : null;
        if ($command === null) {
            return;
        }

        $command->status = $status;
        $command->completed_at = now();
        $command->error_code = Arr::get($payload, 'error');
        $command->result_json = $payload;
        $command->save();
    }

    private function handleGpsTelemetry(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $receivedAt = $this->parseTimestamp(Arr::get($payload, 'received_at'));
        $hasUsableGps = $this->hasUsableGpsPayload($payload);

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        $device->last_seen_at = now();
        if ($hasUsableGps) {
            $device->last_lat = Arr::get($payload, 'lat');
            $device->last_lng = Arr::get($payload, 'lng');
            $device->last_gps_at = $receivedAt;
        }
        $device->gps_sat = Arr::get($payload, 'sat');
        if (Arr::has($payload, 'battery_voltage')) {
            $device->battery_voltage = Arr::get($payload, 'battery_voltage');
            $device->battery_percent = $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage'));
        }
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        $telemetry = DeviceTelemetry::query()->create([
            'imei' => $imei,
            'type' => Arr::get($payload, 'source', 'D0'),
            'raw_payload' => json_encode($payload),
            'parsed_json' => $payload,
            'device_ts' => Arr::get($payload, 'device_ts'),
            'received_at' => $receivedAt,
        ]);

        LockLocation::query()->create([
            'imei' => $imei,
            'lat' => Arr::get($payload, 'lat'),
            'lng' => Arr::get($payload, 'lng'),
            'hdop' => Arr::get($payload, 'hdop'),
            'sat' => Arr::get($payload, 'sat'),
            'is_valid' => (bool) Arr::get($payload, 'is_valid'),
            'captured_at' => $receivedAt,
            'source' => Arr::get($payload, 'source'),
            'raw_event_id' => $telemetry->id,
        ]);

        $this->updateBikeLocationFromLock($imei, $payload, $receivedAt);
        $this->trackingService->handleLockTelemetry($imei);
    }

    private function hasUsableGpsPayload(array $payload): bool
    {
        if (Arr::has($payload, 'is_valid') && !Arr::get($payload, 'is_valid')) {
            return false;
        }

        $lat = Arr::get($payload, 'lat');
        $lng = Arr::get($payload, 'lng');
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }

        return !($lat === 0.0 && $lng === 0.0);
    }

    private function handleWifiPositioningTelemetry(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $receivedAt = $this->parseTimestamp(Arr::get($payload, 'received_at'));

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        $device->last_seen_at = now();
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        DeviceTelemetry::query()->create([
            'imei' => $imei,
            'type' => 'D2',
            'raw_payload' => json_encode($payload),
            'parsed_json' => $payload,
            'device_ts' => Arr::get($payload, 'device_ts'),
            'received_at' => $receivedAt,
        ]);

        $this->storeLockEvent($payload, 'telemetry.wifi_positioning');
    }

    private function handleTrackResult(array $payload): void
    {
        $this->storeTelemetryEvent($payload, 'D1');
        $this->storeLockEvent($payload, 'command.track.result');
    }

    private function handleHeartbeat(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $receivedAt = $this->parseTimestamp(Arr::get($payload, 'received_at'));

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        $device->last_seen_at = now();
        if (Arr::has($payload, 'lock_status')) {
            $device->lock_status = Arr::get($payload, 'lock_status');
        }
        if (Arr::has($payload, 'battery_voltage')) {
            $device->battery_voltage = Arr::get($payload, 'battery_voltage');
            $device->battery_percent = $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage'));
        }
        if (Arr::has($payload, 'signal_strength')) {
            $device->signal_strength = Arr::get($payload, 'signal_strength');
        }
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        $this->updateLockStatus($imei, [
            'is_locked' => Arr::has($payload, 'lock_status') ? (int) Arr::get($payload, 'lock_status') === 1 : null,
            'voltage' => Arr::get($payload, 'battery_voltage'),
            'battery_percent' => $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage')),
            'signal_strength' => Arr::get($payload, 'signal_strength'),
            'last_seen_at' => $receivedAt,
        ]);

        $this->trackingService->handleLockTelemetry($imei);

        DeviceTelemetry::query()->create([
            'imei' => $imei,
            'type' => 'H0',
            'raw_payload' => json_encode($payload),
            'parsed_json' => $payload,
            'device_ts' => Arr::get($payload, 'device_ts'),
            'received_at' => $receivedAt,
        ]);
    }

    private function storeTelemetryEvent(array $payload, string $type): ?DeviceTelemetry
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return null;
        }

        $receivedAt = $this->parseTimestamp(Arr::get($payload, 'received_at'));

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        $device->last_seen_at = now();
        $device->save();

        return DeviceTelemetry::query()->create([
            'imei' => $imei,
            'type' => $type,
            'raw_payload' => json_encode($payload),
            'parsed_json' => $payload,
            'device_ts' => Arr::get($payload, 'device_ts'),
            'received_at' => $receivedAt,
        ]);
    }

    private function handleStatusTelemetry(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $receivedAt = $this->parseTimestamp(Arr::get($payload, 'received_at'));

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        $device->last_seen_at = now();
        if (Arr::has($payload, 'lock_status')) {
            $device->lock_status = Arr::get($payload, 'lock_status');
        }
        if (Arr::has($payload, 'battery_voltage')) {
            $device->battery_voltage = Arr::get($payload, 'battery_voltage');
            $device->battery_percent = $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage'));
        }
        if (Arr::has($payload, 'signal_strength')) {
            $device->signal_strength = Arr::get($payload, 'signal_strength');
        }
        if (Arr::has($payload, 'gps_sat')) {
            $device->gps_sat = Arr::get($payload, 'gps_sat');
        }
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        $this->updateLockStatus($imei, [
            'is_locked' => Arr::has($payload, 'lock_status') ? (int) Arr::get($payload, 'lock_status') === 1 : null,
            'voltage' => Arr::get($payload, 'battery_voltage'),
            'battery_percent' => $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage')),
            'signal_strength' => Arr::get($payload, 'signal_strength'),
            'last_seen_at' => $receivedAt,
        ]);

        $this->trackingService->handleLockTelemetry($imei);

        DeviceTelemetry::query()->create([
            'imei' => $imei,
            'type' => 'S5',
            'raw_payload' => json_encode($payload),
            'parsed_json' => $payload,
            'device_ts' => Arr::get($payload, 'device_ts'),
            'received_at' => $receivedAt,
        ]);
    }

    private function handleAlarm(array $payload): void
    {
        $this->storeLockEvent($payload, 'alarm.triggered');
        $imei = Arr::get($payload, 'imei');
        if ($imei) {
            $this->alertService->recordLockAlarm($payload);
            $this->trackingService->handleAlarm($imei);
            $this->notificationService->notifyAlarmTriggered($imei);
        }
    }

    private function handleDeviceInfo(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        if (Arr::has($payload, 'device_code')) {
            $device->device_code = Arr::get($payload, 'device_code');
        }
        if (Arr::has($payload, 'firmware_version')) {
            $device->firmware_version = Arr::get($payload, 'firmware_version');
        }
        if (Arr::has($payload, 'firmware_build_date')) {
            $device->firmware_build_date = Arr::get($payload, 'firmware_build_date');
        }
        if (Arr::has($payload, 'model') && trim((string) Arr::get($payload, 'model')) !== '') {
            $device->model = trim((string) Arr::get($payload, 'model'));
        }
        if (Arr::has($payload, 'hardware_version') && trim((string) Arr::get($payload, 'hardware_version')) !== '') {
            $device->hardware_version = trim((string) Arr::get($payload, 'hardware_version'));
        }
        if (blank($device->lock_code)) {
            foreach (['lock_code', 'lockCode', 'lock_sn', 'lockSn', 'lock_id', 'lockId'] as $key) {
                $value = Arr::get($payload, $key);
                if ($value === null) {
                    continue;
                }
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    $device->lock_code = $candidate;
                    break;
                }
            }
        }
        $device->last_seen_at = now();
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        $this->storeLockEvent($payload, 'device.info');
    }

    private function handleExternalLockResult(array $payload): void
    {
        $this->storeTelemetryEvent($payload, 'L5');
        $this->storeLockEvent($payload, 'command.external_lock.result');
    }

    private function handleCableLockInfo(array $payload): void
    {
        $this->storeTelemetryEvent($payload, 'G1');
        $this->storeLockEvent($payload, 'device.cable_lock_info');
    }

    private function handleSimInfo(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        if (Arr::has($payload, 'sim_iccid')) {
            $device->sim_iccid = Arr::get($payload, 'sim_iccid');
        }
        $device->last_seen_at = now();
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        $this->storeLockEvent($payload, 'device.iccid');
    }

    private function handleBleMac(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        if (Arr::has($payload, 'ble_mac')) {
            $device->ble_mac = Arr::get($payload, 'ble_mac');
        }
        $device->last_seen_at = now();
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        $this->storeLockEvent($payload, 'device.ble_mac');
    }

    private function handleBleKey(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        $keyValue = Arr::get($payload, 'key_value');
        if (!$imei || !$keyValue) {
            return;
        }

        BleKey::query()->create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'imei' => $imei,
            'key_value' => $keyValue,
            'rotated_at' => now(),
            'created_at' => now(),
        ]);

        $this->storeLockEvent($payload, 'device.ble_key');
    }

    private function handleBeaconScanResult(array $payload): void
    {
        $this->storeTelemetryEvent($payload, 'B0');
        $this->storeLockEvent($payload, 'beacon.scan.result');
    }

    private function handleRfidUnlockRequest(array $payload): void
    {
        $this->storeTelemetryEvent($payload, 'C0');
        $this->storeLockEvent($payload, 'rfid.unlock.request');
    }

    private function handleRfidAdminCardResult(array $payload): void
    {
        $this->storeTelemetryEvent($payload, 'C1');
        $this->storeLockEvent($payload, 'rfid.admin_card.result');
    }

    private function handleOtaStatus(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $cmd = Arr::get($payload, 'cmd');
        $status = match ($cmd) {
            'U1' => 'running',
            'U2' => 'completed',
            default => 'reported',
        };

        $targetVersion = '';
        if (is_array(Arr::get($payload, 'payload')) && count(Arr::get($payload, 'payload')) > 0) {
            $targetVersion = (string) Arr::get($payload, 'payload')[0];
        }

        $update = FirmwareUpdate::query()
            ->where('imei', $imei)
            ->orderByDesc('scheduled_at')
            ->first();

        if ($update === null) {
            $update = new FirmwareUpdate();
            $update->id = (string) \Illuminate\Support\Str::ulid();
            $update->imei = $imei;
            $update->scheduled_at = now();
        }

        $update->status = $status;
        if ($targetVersion !== '') {
            $update->target_version = $targetVersion;
        }
        if ($status === 'running') {
            $update->started_at = now();
        }
        if ($status === 'completed') {
            $update->completed_at = now();
            $update->progress_percent = 100;
        }
        $update->error_message = is_array(Arr::get($payload, 'payload')) ? json_encode(Arr::get($payload, 'payload')) : null;
        $update->save();

        if ($cmd === 'U0' && is_array(Arr::get($payload, 'payload'))) {
            $device = Device::query()->firstOrNew(['imei' => $imei]);
            $device->firmware_version = Arr::get($payload, 'payload')[0] ?? $device->firmware_version;
            $device->firmware_build_date = Arr::get($payload, 'payload')[2] ?? $device->firmware_build_date;
            $device->last_seen_at = now();
            $device->save();
        }

        $this->storeTelemetryEvent($payload, (string) ($cmd ?: 'U0'));
        $this->storeLockEvent($payload, 'ota.status');
    }

    private function storeLockEvent(array $payload, string $type): void
    {
        LockEvent::query()->create([
            'imei' => Arr::get($payload, 'imei'),
            'type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    private function updateLockStatus(string $imei, array $data): void
    {
        $status = LockStatus::query()->firstOrNew(['imei' => $imei]);
        if (array_key_exists('is_locked', $data)) {
            $status->is_locked = $data['is_locked'];
        }
        if (array_key_exists('voltage', $data)) {
            $status->voltage = $data['voltage'];
        }
        if (array_key_exists('battery_percent', $data)) {
            $status->battery_percent = $data['battery_percent'];
        }
        if (array_key_exists('signal_strength', $data)) {
            $status->signal_strength = $data['signal_strength'];
        }
        if (array_key_exists('last_seen_at', $data)) {
            $status->last_seen_at = $data['last_seen_at'];
        }
        $status->updated_at = now();
        $status->save();
    }

    private function handleLockEvent(array $payload): void
    {
        $telemetry = $this->storeTelemetryEvent($payload, 'L1');
        $imei = Arr::get($payload, 'imei');
        $userId = Arr::get($payload, 'user_id');
        if (!$imei || !$userId) {
            return;
        }

        $ride = $this->rides->completeRideFromLock(
            imei: $imei,
            userId: (int) $userId,
            rideMinutes: (int) Arr::get($payload, 'ride_minutes', 0),
            telemetry: $telemetry,
            endedAt: now(),
        );

        if ($ride === null) {
            return;
        }

        Log::info('ride.pricing.finalized', [
            'ride_id' => $ride->id,
            'imei' => $imei,
            'user_id' => $userId,
            'minutes' => $ride->minutes,
            'duration_seconds' => $ride->duration_seconds,
            'per_minute_rate' => $ride->per_minute_rate,
            'unlock_fee' => $ride->unlock_fee,
            'total_amount' => $ride->total_amount,
            'currency' => $ride->currency,
        ]);

        $this->updateLockStatus($imei, [
            'is_locked' => true,
            'last_seen_at' => now(),
        ]);
        $this->trackingService->handleRideEnded($imei);
        defer(fn () => $this->notificationService->notifyRideEnded($ride->fresh()), 'ride-ended-notification-'.$ride->id);
    }

    private function touchDevice(array $payload): void
    {
        $imei = Arr::get($payload, 'imei');
        if (!$imei) {
            return;
        }

        $device = Device::query()->firstOrNew(['imei' => $imei]);
        if (!$device->exists) {
            $device->is_active = true;
            $device->created_at = now();
        }
        $device->last_seen_at = now();
        if (Arr::has($payload, 'ip')) {
            $device->last_ip = Arr::get($payload, 'ip');
        }
        if (Arr::has($payload, 'battery_voltage')) {
            $device->battery_voltage = Arr::get($payload, 'battery_voltage');
            $device->battery_percent = $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage'));
        }
        $device->updated_at = now();
        $device->save();
        $this->alertService->syncDeviceHealth($device);

        if ($device->provision_status === 'pending' && $device->target_host && $device->target_port) {
            $device->provision_status = 'applied';
            $device->last_provisioned_at = now();
            $device->save();

            Log::info('lock.provision.applied', [
                'imei' => $imei,
                'target_host' => $device->target_host,
                'target_port' => $device->target_port,
                'source' => 'device.online',
            ]);
        }

        $this->updateLockStatus($imei, [
            'voltage' => Arr::get($payload, 'battery_voltage'),
            'battery_percent' => $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage')),
            'last_seen_at' => now(),
        ]);
    }

    private function parseTimestamp(?string $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return now();
        }
    }

    private function mapBatteryPercent(int $voltage): int
    {
        return match (true) {
            $voltage >= 412 => 100,
            $voltage >= 401 => 90,
            $voltage >= 390 => 80,
            $voltage >= 370 => 60,
            $voltage >= 350 => 20,
            $voltage >= 340 => 0,
            default => 0,
        };
    }

    private function updateBikeLocationFromLock(string $imei, array $payload, Carbon $receivedAt): void
    {
        $bike = Bike::query()->where('imei', $imei)->first();
        if ($bike === null) {
            return;
        }

        $lat = Arr::get($payload, 'lat');
        $lng = Arr::get($payload, 'lng');
        if ($lat === null || $lng === null) {
            return;
        }

        if (Arr::has($payload, 'is_valid') && !Arr::get($payload, 'is_valid')) {
            return;
        }

        $batteryPercent = null;
        if (Arr::has($payload, 'battery_voltage')) {
            $batteryPercent = $this->mapBatteryPercent((int) Arr::get($payload, 'battery_voltage'));
        }

        $telemetry = [
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'accuracy_m' => Arr::get($payload, 'hdop'),
            'battery_percent' => $batteryPercent,
            'captured_at' => $receivedAt->toIso8601String(),
            'updated_at' => $receivedAt->toIso8601String(),
        ];

        $metadata = $bike->metadata ?? [];
        $existingTelemetry = is_array(Arr::get($metadata, 'telemetry')) ? Arr::get($metadata, 'telemetry') : [];
        $metadata['telemetry'] = array_merge(
            $existingTelemetry,
            array_filter($telemetry, static fn ($value) => $value !== null)
        );

        $bike->metadata = $metadata;
        $bike->last_latitude = (float) $lat;
        $bike->last_longitude = (float) $lng;
        $bike->location_accuracy = Arr::get($payload, 'hdop') !== null ? (float) Arr::get($payload, 'hdop') : null;
        $bike->location_updated_at = $receivedAt;
        if ($batteryPercent !== null) {
            $bike->battery_level = $batteryPercent;
        }

        $bike->save();
    }

}
