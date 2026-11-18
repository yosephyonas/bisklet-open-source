<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Rides\RidePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiveLockLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 100)));

        $onlineCutoff = now()->subMinutes(30);

        $locks = Device::query()
            ->with('bike:id,code,imei,status,is_active,price_per_minute,unlock_fee')
            ->where('is_active', true)
            ->whereNotNull('last_lat')
            ->whereNotNull('last_lng')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $onlineCutoff)
            ->orderByDesc('last_gps_at')
            ->orderByDesc('last_seen_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $locks->getCollection()->map(function (Device $device): array {
                $bike = $device->bike;
                $status = $bike?->status?->value ?? $bike?->status ?? $device->status ?? 'available';
                $pricing = RidePricing::snapshot();

                return [
                    'id' => $bike?->id ?? $device->id,
                    'code' => $bike?->code,
                    'imei' => $device->imei,
                    'lock_code' => $device->lock_code,
                    'status' => $status,
                    'is_active' => $device->is_active && ($bike?->is_active ?? true),
                    'battery_level' => $device->battery_percent,
                    'price_per_minute' => $pricing['per_minute_rate'],
                    'unlock_fee' => $pricing['unlock_fee'],
                    'price_birr' => $pricing['block_price'],
                    'price_minutes' => $pricing['block_minutes'],
                    'billing_model' => $pricing['billing_model'],
                    'current_location' => [
                        'latitude' => $device->last_lat,
                        'longitude' => $device->last_lng,
                        'accuracy_m' => null,
                        'updated_at' => optional($device->last_gps_at ?? $device->last_seen_at)->toIso8601String(),
                    ],
                    'device' => [
                        'id' => $device->id,
                        'imei' => $device->imei,
                        'lock_code' => $device->lock_code,
                        'lock_state' => $device->lock_state,
                        'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
                        'last_gps_at' => optional($device->last_gps_at)->toIso8601String(),
                    ],
                ];
            })->values(),
            'meta' => [
                'current_page' => $locks->currentPage(),
                'last_page' => $locks->lastPage(),
                'per_page' => $locks->perPage(),
                'total' => $locks->total(),
            ],
        ]);
    }
}
