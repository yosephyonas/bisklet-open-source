<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Navigation\RoutePreviewRequest;
use App\Services\GeofenceService;
use App\Services\Mapbox\MapboxClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class MapboxController extends Controller
{
    public function __construct(
        private readonly MapboxClient $mapboxClient,
        private readonly GeofenceService $geofence,
    ) {
    }

    public function config(Request $request): JsonResponse
    {
        try {
            $token = $this->mapboxClient->accessToken();
        } catch (ValidationException) {
            $token = null;
        }

        return response()->json([
            'mapbox' => [
                'access_token' => $token,
                'default_style' => $this->mapboxClient->defaultStyle(),
                'navigation_profile' => $this->mapboxClient->navigationProfile(),
            ],
            'service_area' => $this->geofence->toFeatureCollection(),
        ]);
    }

    public function route(RoutePreviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        $options = [
            'annotations' => 'distance,duration,speed',
        ];

        if (Arr::get($data, 'avoid_tolls')) {
            $options['exclude'] = 'toll';
        }

        if (Arr::get($data, 'avoid_ferries')) {
            $options['exclude'] = isset($options['exclude'])
                ? $options['exclude'].',ferry'
                : 'ferry';
        }

        if (Arr::has($data, 'locale')) {
            $options['language'] = $data['locale'];
        }

        $directions = $this->mapboxClient->directions(
            $data['origin_latitude'],
            $data['origin_longitude'],
            $data['destination_latitude'],
            $data['destination_longitude'],
            $options,
        );

        return response()->json([
            'routes' => Arr::get($directions, 'routes', []),
            'waypoints' => Arr::get($directions, 'waypoints', []),
            'code' => Arr::get($directions, 'code'),
            'uuid' => Arr::get($directions, 'uuid'),
        ], Response::HTTP_OK);
    }
}
