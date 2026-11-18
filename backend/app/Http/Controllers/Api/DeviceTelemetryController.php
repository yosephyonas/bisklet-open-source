<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LockEvent\DeviceTelemetryStoreRequest;
use App\Models\Device;
use App\Models\DeviceTelemetry;
use App\Services\OperationalAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class DeviceTelemetryController extends Controller
{
    public function store(DeviceTelemetryStoreRequest $request, OperationalAlertService $alerts): JsonResponse
    {
        $data = $request->validated();
        $now = now();
        $rawPayload = $data['raw_payload'] ?? $request->getContent();

        $parsedJson = $data['parsed_json'] ?? null;
        if ($parsedJson === null && is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedJson = $decoded;
            }
        }

        $device = Device::query()->firstOrNew(['imei' => $data['imei']]);
        $device->last_seen_at = $now;

        if (array_key_exists('last_lat', $data)) {
            $device->last_lat = $data['last_lat'];
        }

        if (array_key_exists('last_lng', $data)) {
            $device->last_lng = $data['last_lng'];
        }

        if (array_key_exists('battery_voltage', $data)) {
            $device->battery_voltage = $data['battery_voltage'];
            $device->battery_percent = $this->mapBatteryPercent((int) $data['battery_voltage']);
        }

        if (array_key_exists('lock_state', $data)) {
            $device->lock_state = $data['lock_state'];
        }

        if (array_key_exists('firmware_version', $data)) {
            $device->firmware_version = $data['firmware_version'];
        }

        $this->applyDelayedMetadataAutoFill($device, is_array($parsedJson) ? $parsedJson : []);
        $device->save();
        $alerts->syncDeviceHealth($device);

        DeviceTelemetry::query()->create([
            'imei' => $data['imei'],
            'type' => $data['type'],
            'raw_payload' => $rawPayload,
            'parsed_json' => $parsedJson,
            'created_at' => $now,
        ]);

        return response()->json([
            'status' => 'accepted',
            'imei' => $data['imei'],
            'type' => $data['type'],
            'created_at' => $now->toIso8601String(),
        ], Response::HTTP_ACCEPTED);
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

    private function applyDelayedMetadataAutoFill(Device $device, array $payload): void
    {
        $fieldMap = [
            'model' => ['model'],
            'hardware_version' => ['hardware_version', 'hw_version'],
            'device_code' => ['device_code', 'device_sn', 'sn'],
            'firmware_build_date' => ['firmware_build_date', 'firmware_date', 'fw_build_date'],
            'sim_iccid' => ['sim_iccid', 'iccid'],
            'ble_mac' => ['ble_mac', 'mac', 'bleMac'],
        ];

        foreach ($fieldMap as $deviceField => $keys) {
            foreach ($keys as $key) {
                $value = Arr::get($payload, $key);
                if ($value === null) {
                    continue;
                }

                $normalized = trim((string) $value);
                if ($normalized === '') {
                    continue;
                }

                if (blank($device->{$deviceField})) {
                    $device->{$deviceField} = $normalized;
                }
                break;
            }
        }

        if (blank($device->lock_code)) {
            foreach (['lock_code', 'lockCode', 'lock_sn', 'lockSn', 'lock_id', 'lockId'] as $key) {
                $value = Arr::get($payload, $key);
                if ($value === null) {
                    continue;
                }
                $normalized = trim((string) $value);
                if ($normalized !== '') {
                    $device->lock_code = $normalized;
                    break;
                }
            }
        }
    }
}
