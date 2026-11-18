<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Telemetry\TelemetryStoreRequest;
use App\Http\Resources\BikeResource;
use App\Models\Bike;
use App\Services\Mapbox\MapboxClient;
use App\Services\OperationalAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TelemetryController extends Controller
{
    public function store(
        TelemetryStoreRequest $request,
        Bike $bike,
        MapboxClient $mapboxClient,
        OperationalAlertService $alerts
    ): JsonResponse {
        $data = $request->validated();
        $now = now();

        $telemetry = [
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'bearing' => Arr::get($data, 'bearing'),
            'speed_kph' => Arr::get($data, 'speed_kph'),
            'accuracy_m' => Arr::get($data, 'accuracy_m'),
            'battery_percent' => Arr::get($data, 'battery_percent'),
            'odometer_km' => Arr::get($data, 'odometer_km'),
            'captured_at' => $now->toIso8601String(),
        ];

        try {
            $locationGeometry = $mapboxClient->snapToRoad(
                $telemetry['latitude'],
                $telemetry['longitude'],
                $telemetry['bearing'],
                $telemetry['accuracy_m']
            );
        } catch (ValidationException) {
            $locationGeometry = [
                'matched' => false,
                'geometry' => null,
            ];
        }

        $metadata = $bike->metadata ?? [];
        $metadata['telemetry'] = $telemetry;
        $metadata['telemetry']['mapbox_geometry'] = $locationGeometry['geometry'] ?? null;
        $metadata['telemetry']['matched_to_road'] = (bool) ($locationGeometry['matched'] ?? false);
        $metadata['telemetry']['updated_at'] = $now->toIso8601String();

        $bike->metadata = $metadata;
        $bike->last_latitude = $telemetry['latitude'];
        $bike->last_longitude = $telemetry['longitude'];
        $bike->location_accuracy = Arr::get($data, 'accuracy_m') !== null
            ? (float) Arr::get($data, 'accuracy_m')
            : null;
        $bike->location_updated_at = $now;

        if (Arr::has($data, 'battery_percent')) {
            $bike->battery_level = (float) Arr::get($data, 'battery_percent');
        }

        if (Arr::has($data, 'odometer_km')) {
            $bike->odometer_km = (float) Arr::get($data, 'odometer_km');
        }

        $bike->save();

        if (Arr::has($data, 'battery_percent')) {
            if ((float) Arr::get($data, 'battery_percent') <= (int) config('services.locks.low_battery_threshold', 20)) {
                $alerts->recordBikeLowBattery($bike);
            } elseif ($bike->imei) {
                $alerts->resolveOpenAlerts($bike->imei, 'low_battery', [
                    'resolved_by' => 'bike_battery_recovered',
                    'battery_percent' => (float) Arr::get($data, 'battery_percent'),
                ]);
            }
        }

        return BikeResource::make($bike->fresh(['station', 'device']))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
