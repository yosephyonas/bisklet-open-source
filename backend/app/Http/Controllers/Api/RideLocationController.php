<?php

namespace App\Http\Controllers\Api;

use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ride\RideLocationRequest;
use App\Http\Resources\RideResource;
use App\Models\Ride;
use App\Services\GeofenceService;
use App\Services\LockTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class RideLocationController extends Controller
{
    public function store(
        RideLocationRequest $request,
        Ride $ride,
        LockTrackingService $trackingService,
        GeofenceService $geofence,
    ): JsonResponse {
        $user = $request->user();

        if ($ride->user_id !== $user->id && $user->role !== UserRole::Admin) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to update this ride.');
        }

        if ($ride->status !== RideStatus::Active) {
            return response()->json([
                'message' => 'Ride is not active.',
            ], Response::HTTP_CONFLICT);
        }

        $data = $request->validated();
        $capturedAt = isset($data['captured_at'])
            ? Carbon::parse($data['captured_at'])
            : now();

        $telemetry = $ride->telemetry ?? [];
        $userLocation = [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'accuracy_m' => Arr::get($data, 'accuracy_m'),
            'speed_mps' => Arr::get($data, 'speed_mps'),
            'bearing' => Arr::get($data, 'bearing'),
            'captured_at' => $capturedAt->toIso8601String(),
        ];

        $telemetry['user_location'] = $userLocation;
        $telemetry['user_location_updated_at'] = now()->toIso8601String();

        $wasInZone = Arr::get($telemetry, 'geofence_inside', true);
        $isInZone = $geofence->isInsideZone($userLocation['latitude'], $userLocation['longitude']);
        $telemetry['geofence_inside'] = $isInZone;

        if ($wasInZone && ! $isInZone) {
            $telemetry['geofence_exit_detected_at'] = now()->toIso8601String();
        }

        $ride->telemetry = $telemetry;

        if ($ride->start_latitude === null || $ride->start_longitude === null) {
            $ride->start_latitude = $userLocation['latitude'];
            $ride->start_longitude = $userLocation['longitude'];
        }

        $ride->save();

        if ($ride->imei) {
            $trackingService->handlePhoneLocation($ride->imei, $userLocation['captured_at']);
        }

        return RideResource::make($ride->fresh(['bike', 'startStation', 'endStation', 'payments', 'device', 'user']))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
