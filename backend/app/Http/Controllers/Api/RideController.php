<?php

namespace App\Http\Controllers\Api;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ride\RideCompleteRequest;
use App\Http\Requests\Ride\RideStoreRequest;
use App\Http\Resources\RideResource;
use App\Models\Bike;
use App\Models\Ride;
use App\Services\NotificationService;
use App\Services\Rides\RidePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RideController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));

        $rides = Ride::query()
            ->with(['bike', 'startStation', 'endStation', 'payments', 'device', 'user'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('bike_id'), fn ($query) => $query->where('bike_id', $request->query('bike_id')))
            ->when(
                $request->filled('user_id') && $user->role === UserRole::Admin,
                fn ($query) => $query->where('user_id', $request->query('user_id')),
            )
            ->when(
                $user->role !== UserRole::Admin,
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return RideResource::collection($rides)->response();
    }

    public function store(RideStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $ride = DB::transaction(function () use ($data, $user) {
            $existingRide = Ride::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [RideStatus::Pending->value, RideStatus::Active->value])
                ->lockForUpdate()
                ->first();

            if ($existingRide) {
                throw ValidationException::withMessages([
                    'bike_id' => 'You already have an active ride.',
                ]);
            }

            $bike = Bike::query()->lockForUpdate()->findOrFail($data['bike_id']);

            if (! $bike->is_active) {
                throw ValidationException::withMessages([
                    'bike_id' => 'Bike is not active.',
                ]);
            }

            $currentStatus = $bike->status instanceof BikeStatus
                ? $bike->status
                : BikeStatus::tryFrom((string) $bike->status);

            if ($currentStatus !== null && $currentStatus !== BikeStatus::Available) {
                throw ValidationException::withMessages([
                    'bike_id' => 'Bike is not available.',
                ]);
            }

            $startStationId = $data['start_station_id'] ?? $bike->station_id;
            $pricing = RidePricing::snapshot();

            $ride = Ride::create([
                'user_id' => $user->id,
                'bike_id' => $bike->id,
                'start_station_id' => $startStationId,
                'status' => RideStatus::Active,
                'started_at' => Carbon::now(),
                'unlock_fee' => $pricing['unlock_fee'],
                'per_minute_rate' => $pricing['per_minute_rate'],
                'pricing_breakdown' => $pricing,
                'currency' => 'ETB',
                'start_latitude' => $data['start_latitude'] ?? null,
                'start_longitude' => $data['start_longitude'] ?? null,
            ]);

            $bike->fill([
                'status' => BikeStatus::InRide->value,
                'station_id' => null,
            ])->save();

            return $ride->load(['bike', 'startStation', 'device', 'user']);
        });

        return RideResource::make($ride)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Ride $ride): JsonResponse
    {
        $user = $request->user();

        if ($ride->user_id !== $user->id && $user->role !== UserRole::Admin) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to view this ride.');
        }

        $ride->load(['bike', 'startStation', 'endStation', 'payments', 'device', 'user']);

        return RideResource::make($ride)->response();
    }

    public function complete(RideCompleteRequest $request, Ride $ride): JsonResponse
    {
        $user = $request->user();

        if ($ride->user_id !== $user->id && $user->role !== UserRole::Admin) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to complete this ride.');
        }

        if ($ride->status === RideStatus::Completed) {
            $ride->load(['bike', 'startStation', 'endStation', 'device']);

            return RideResource::make($ride)->response();
        }

        if ($ride->status !== RideStatus::Active) {
            throw ValidationException::withMessages([
                'ride' => 'Ride is not active.',
            ]);
        }

        $data = $request->validated();

        $ride = DB::transaction(function () use ($ride, $data) {
            $ride = Ride::query()->lockForUpdate()->findOrFail($ride->id);
            $bike = $ride->bike_id
                ? Bike::query()->lockForUpdate()->find($ride->bike_id)
                : null;

            if ($bike === null && filled($ride->imei)) {
                $bike = Bike::query()
                    ->where('imei', $ride->imei)
                    ->lockForUpdate()
                    ->first();
            }

            if ($ride->status !== RideStatus::Active) {
                throw ValidationException::withMessages([
                    'ride' => 'Ride is not active.',
                ]);
            }

            $now = Carbon::now();
            $startedAt = $ride->started_at ?? $now;
            $durationSeconds = max(1, $startedAt->diffInSeconds($now));

            if (isset($data['distance_m'])) {
                $ride->distance_m = $data['distance_m'];
            }

            if (isset($data['end_latitude'])) {
                $ride->end_latitude = $data['end_latitude'];
            }

            if (isset($data['end_longitude'])) {
                $ride->end_longitude = $data['end_longitude'];
            }

            $minutes = $ride->minutes && $ride->minutes > 0
                ? $ride->minutes
                : max(1, (int) ceil($durationSeconds / 60));
            // Pricing is fixed when the ride starts. Never trust client-supplied
            // rate fields or a bike price changed while the ride is active.
            $pricing = RidePricing::calculate(
                (int) $minutes,
                is_array($ride->pricing_breakdown) ? $ride->pricing_breakdown : null,
            );
            $endedAt = $ride->minutes && $ride->minutes > 0
                ? $startedAt->copy()->addMinutes($minutes)
                : $now;

            if ($ride->bike_id === null && $bike !== null) {
                $ride->bike_id = $bike->id;
            }

            $ride->per_minute_rate = $pricing['per_minute_rate'];
            $ride->unlock_fee = $pricing['unlock_fee'];

            $ride->fill([
                'end_station_id' => $data['end_station_id'] ?? $ride->end_station_id,
                'duration_seconds' => max(1, (int) ($minutes * 60)),
                'ended_at' => $endedAt,
                'minutes' => $minutes,
                'status' => RideStatus::Completed,
                'total_amount' => $pricing['total_amount'],
                'pricing_breakdown' => $pricing,
            ]);

            $ride->save();

            if ($bike !== null) {
                $bike->fill([
                    'status' => BikeStatus::Available->value,
                    'station_id' => $data['end_station_id'] ?? $bike->station_id,
                ])->save();
            }

            return $ride->load(['bike', 'startStation', 'endStation', 'device']);
        });

        defer(fn () => $this->notificationService->notifyRideEnded($ride->fresh()), 'ride-ended-notification-'.$ride->id);

        return RideResource::make($ride)->response();
    }
}
