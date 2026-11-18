<?php

namespace App\Http\Controllers\Api;

use App\Enums\BikeStatus;
use App\Enums\RideStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bike\BikeStoreRequest;
use App\Http\Requests\Bike\BikeUpdateRequest;
use App\Http\Resources\BikeResource;
use App\Models\Bike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BikeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radius = $request->query('radius_m');
        $includeUnavailable = $request->boolean('include_unavailable');
        $hasStatusFilter = $request->filled('status');
        $hasGeo = is_numeric($lat) && is_numeric($lng);
        $radiusMeters = is_numeric($radius) ? (int) $radius : 200;
        $radiusMeters = min(50000, max(1, $radiusMeters));

        $bikes = Bike::query()
            ->with(['station', 'device', 'activeRide'])
            ->when(! $includeUnavailable, fn ($query) => $query->where('is_active', true))
            ->when(! $includeUnavailable, fn ($query) => $query->withoutActiveRide())
            ->when($hasStatusFilter, fn ($query) => $query->where('status', $request->query('status')))
            ->when(! $includeUnavailable && ! $hasStatusFilter, fn ($query) => $query->where('status', BikeStatus::Available->value))
            ->when($request->filled('station_id'), fn ($query) => $query->where('station_id', $request->query('station_id')))
            ->when($hasGeo, fn ($query) => $query->whereHas('device', fn ($q) => $q->where('is_active', true)))
            ->when($hasGeo, function ($query) use ($lat, $lng, $radiusMeters) {
                $lat = (float) $lat;
                $lng = (float) $lng;
                $distanceSql = '(6371000 * 2 * ASIN(SQRT('.
                    'POWER(SIN(RADIANS(? - last_latitude) / 2), 2) + '.
                    'COS(RADIANS(?)) * COS(RADIANS(last_latitude)) * '.
                    'POWER(SIN(RADIANS(? - last_longitude) / 2), 2)'.
                    ')))';

                $query
                    ->whereNotNull('last_latitude')
                    ->whereNotNull('last_longitude')
                    ->select('bikes.*')
                    ->selectRaw("$distanceSql as distance_m", [$lat, $lat, $lng])
                    ->whereRaw("$distanceSql <= ?", [$lat, $lat, $lng, $radiusMeters])
                    ->orderBy('distance_m');
            }, fn ($query) => $query->orderByDesc('created_at'))
            ->paginate($perPage);

        return BikeResource::collection($bikes)->response();
    }

    public function store(BikeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            $status = BikeStatus::tryFrom($data['status']);

            if ($status === null) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid bike status supplied.',
                ]);
            }

            $data['status'] = $status->value;
        } else {
            $data['status'] = BikeStatus::Available->value;
        }

        $data['is_active'] = $data['is_active'] ?? true;
        $data['price_per_minute'] = config('pricing.rides.per_minute_rate', 0.5);
        $data['unlock_fee'] = config('pricing.rides.unlock_fee', 5);
        $data = $this->ensureLocationTimestamp($data);

        $bike = Bike::create($data)->load(['station', 'device']);

        return BikeResource::make($bike)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Bike $bike): JsonResponse
    {
        $bike->load(['station', 'device', 'activeRide']);

        return BikeResource::make($bike)->response();
    }

    public function update(BikeUpdateRequest $request, Bike $bike): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $status = BikeStatus::tryFrom($data['status']);

            if ($status === null) {
                throw ValidationException::withMessages([
                    'status' => 'Invalid bike status supplied.',
                ]);
            }

            $data['status'] = $status->value;
        }

        $data['price_per_minute'] = config('pricing.rides.per_minute_rate', 0.5);
        $data['unlock_fee'] = config('pricing.rides.unlock_fee', 5);

        $data = $this->ensureLocationTimestamp($data);

        $bike->update($data);

        return BikeResource::make($bike->fresh(['station', 'device', 'activeRide']))->response();
    }

    public function destroy(Bike $bike): JsonResponse
    {
        if ($bike->rides()->whereIn('status', [RideStatus::Pending->value, RideStatus::Active->value])->exists()) {
            throw ValidationException::withMessages([
                'bike' => 'Bike has active rides and cannot be deleted.',
            ]);
        }

        $bike->delete();

        return response()->json([
            'message' => 'Bike deleted.',
        ]);
    }

    private function ensureLocationTimestamp(array $data): array
    {
        $locationFields = ['last_latitude', 'last_longitude', 'location_accuracy'];

        foreach ($locationFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data['location_updated_at'] = $data['location_updated_at'] ?? now();
                break;
            }
        }

        return $data;
    }
}
