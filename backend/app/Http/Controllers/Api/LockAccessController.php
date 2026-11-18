<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RideResource;
use App\Models\Device;
use App\Models\LockCommand;
use App\Models\ScanSession;
use App\Services\Rides\RideLifecycleService;
use App\Services\Rides\RidePricing;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LockAccessController extends Controller
{
    public function __construct(private readonly RideLifecycleService $rides) {}

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lock_code' => ['nullable', 'string', 'max:64'],
            'identifier' => ['nullable', 'string', 'max:64'],
            'bike_code' => ['nullable', 'string', 'max:64'],
            'bike_id' => ['nullable'],
        ]);

        $identifier = $this->resolveScanIdentifier($data);
        if ($identifier === null) {
            return response()->json(['error' => 'A lock or bike identifier is required'], 422);
        }

        $user = $request->user();
        $device = $this->findDeviceForScanIdentifier($identifier);
        if (! $device) {
            return response()->json(['error' => 'Lock not found'], 404);
        }

        $existingBikeRide = $this->rides->findActiveRideForLock($device->imei, $device->bike?->id);
        if ($existingBikeRide !== null && (int) $existingBikeRide->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Bike is already in use'], 409);
        }

        if ($existingBikeRide === null && ($reason = $this->unavailableReason($device)) !== null) {
            return response()->json(['error' => $reason], 409);
        }

        $token = Str::random(40);
        $tokenHash = hash('sha256', $token);
        $issuedAt = now();
        $expiresAt = $issuedAt->copy()->addSeconds(120);

        ScanSession::query()
            ->where('user_id', $user->id)
            ->where('imei', $device->imei)
            ->whereNull('used_at')
            ->update(['used_at' => $issuedAt]);

        ScanSession::query()->create([
            'user_id' => $user->id,
            'imei' => $device->imei,
            'scan_token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => $issuedAt,
        ]);

        $pricing = RidePricing::snapshot();

        return response()->json([
            'imei' => $device->imei,
            'status' => $device->status,
            'lock_status' => $device->lock_status,
            'battery_percent' => $device->battery_percent,
            'last_lat' => $device->last_lat,
            'last_lng' => $device->last_lng,
            'bike_id' => $device->bike?->id,
            'bike_code' => $device->bike?->code,
            'bike_status' => $device->bike?->status?->value ?? $device->bike?->status,
            'bike_is_active' => $device->bike?->is_active ?? true,
            'device_is_active' => $device->is_active,
            'price_per_minute' => $pricing['per_minute_rate'],
            'unlock_fee' => $pricing['unlock_fee'],
            'price_birr' => $pricing['block_price'],
            'price_minutes' => $pricing['block_minutes'],
            'billing_model' => $pricing['billing_model'],
            'lock_code' => $device->lock_code,
            'scan_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function unlock(Request $request, string $imei): JsonResponse
    {
        $data = $request->validate([
            'scan_token' => ['required', 'string'],
            'reset_flag' => ['nullable', 'integer', 'min:0', 'max:1'],
        ]);

        $user = $request->user();
        $device = Device::query()->where('imei', $imei)->first();
        if (! $device) {
            return response()->json(['error' => 'Lock not found'], 404);
        }
        if (blank($device->lock_code)) {
            Log::warning('lock.unlock.blocked_missing_lock_code', [
                'imei' => $imei,
                'user_id' => (string) $user->id,
            ]);

            return response()->json(['error' => 'Lock code not linked to device'], 409);
        }

        try {
            return Cache::lock("ride-start:user:{$user->id}", 15)->block(3, function () use ($data, $device, $imei, $request, $user) {
                return Cache::lock("ride-start:device:{$imei}", 15)->block(3, function () use ($data, $device, $imei, $request, $user) {
                    $tokenHash = hash('sha256', $data['scan_token']);

                    $existingRide = $this->rides->findUserActiveRide((int) $user->id);
                    if ($existingRide !== null) {
                        if ($existingRide->imei === $imei) {
                            return response()->json([
                                'status' => 'success',
                                'request_id' => null,
                                'result' => ['message' => 'Ride already active'],
                                'active_ride' => RideResource::make($existingRide)->resolve($request),
                            ]);
                        }

                        return response()->json([
                            'error' => 'You already have an active ride',
                            'active_ride' => RideResource::make($existingRide)->resolve($request),
                        ], 409);
                    }

                    $existingBikeRide = $this->rides->findActiveRideForLock($imei, $device->bike?->id);
                    if ($existingBikeRide !== null) {
                        return response()->json([
                            'error' => 'Bike is already in use',
                        ], 409);
                    }

                    if (($reason = $this->unavailableReason($device)) !== null) {
                        return response()->json(['error' => $reason], 409);
                    }

                    $session = ScanSession::query()
                        ->where('user_id', $user->id)
                        ->where('imei', $imei)
                        ->where('scan_token_hash', $tokenHash)
                        ->whereNull('used_at')
                        ->where('expires_at', '>', now())
                        ->first();

                    if (! $session) {
                        return response()->json(['error' => 'Invalid or expired scan token'], 403);
                    }

                    $session->used_at = now();
                    $session->save();

                    $requestId = (string) Str::ulid();
                    $unixTime = now()->utc()->timestamp;
                    $resetFlag = (int) ($data['reset_flag'] ?? 0);

                    $command = LockCommand::query()->create([
                        'id' => (string) Str::ulid(),
                        'request_id' => $requestId,
                        'imei' => $imei,
                        'user_id' => (string) $user->id,
                        'command' => 'unlock',
                        'status' => 'pending',
                        'requested_at' => now(),
                        'reset_flag' => $resetFlag,
                        'op_unix' => $unixTime,
                    ]);

                    $gatewayUrl = rtrim(config('services.go_gateway.base_url', 'http://127.0.0.1:9001'), '/');

                    $activeRide = null;

                    try {
                        Log::info('lock.unlock.request', [
                            'imei' => $imei,
                            'user_id' => (string) $user->id,
                            'reset_flag' => $resetFlag,
                            'unix_time' => $unixTime,
                            'request_id' => $requestId,
                            'gateway' => $gatewayUrl,
                        ]);

                        $response = Http::timeout(10)->post($gatewayUrl.'/commands/unlock', [
                            'imei' => $imei,
                            'user_id' => (string) $user->id,
                            'reset_flag' => $resetFlag,
                            'unix_time' => $unixTime,
                            'request_id' => $requestId,
                        ]);

                        $command->sent_at = now();

                        if ($response->successful()) {
                            $payload = $response->json();
                            $result = (int) ($payload['result'] ?? 1);
                            $command->status = $result === 0 ? 'success' : 'failed';
                            $command->completed_at = now();
                            $command->result_json = $payload;
                            if ($result === 0) {
                                $activeRide = $this->rides->startRideFromUnlock($device->imei, $user->id, $command, now());
                            }
                        } else {
                            $command->status = $response->status() === 504 ? 'timeout' : 'failed';
                            $command->error_code = (string) $response->status();
                            $command->result_json = $response->json();
                        }
                    } catch (\Throwable $exception) {
                        $command->status = 'failed';
                        $command->error_code = 'gateway_error';
                        $command->result_json = ['error' => $exception->getMessage()];
                    }

                    $command->save();

                    return response()->json([
                        'status' => $command->status,
                        'request_id' => $requestId,
                        'result' => $command->result_json,
                        'active_ride' => $activeRide ? RideResource::make($activeRide)->resolve($request) : null,
                    ]);
                });
            });
        } catch (LockTimeoutException) {
            return response()->json([
                'error' => 'Ride start already in progress. Please wait a moment and retry.',
            ], 409);
        }
    }

    private function resolveScanIdentifier(array $data): ?string
    {
        foreach (['lock_code', 'identifier', 'bike_code', 'bike_id'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = trim((string) $data[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function findDeviceForScanIdentifier(string $identifier): ?Device
    {
        $query = Device::query()->with('bike')
            ->where('lock_code', $identifier)
            ->orWhereHas('bike', function ($builder) use ($identifier) {
                $builder->where('code', $identifier);

                if (preg_match('/^\d+$/', $identifier) === 1) {
                    $builder->orWhere('id', (int) $identifier);
                }
            });

        return $query->first();
    }

    private function unavailableReason(Device $device): ?string
    {
        if (! $device->is_active) {
            return 'Bike is unavailable';
        }

        $deviceStatus = strtolower(trim((string) $device->status));
        if (in_array($deviceStatus, ['offline', 'maintenance', 'disabled', 'error'], true)) {
            return $deviceStatus === 'maintenance'
                ? 'Bike is under maintenance'
                : 'Bike is offline';
        }

        $bike = $device->bike;
        if ($bike === null) {
            return 'Lock is not linked to a bike';
        }

        if (! $bike->is_active) {
            return 'Bike is unavailable';
        }

        $bikeStatus = strtolower((string) ($bike->status?->value ?? $bike->status));

        return match ($bikeStatus) {
            'available' => null,
            'reserved' => 'Bike is reserved',
            'maintenance' => 'Bike is under maintenance',
            'offline' => 'Bike is offline',
            'in_ride' => 'Bike is already in use',
            default => 'Bike is unavailable',
        };
    }
}
