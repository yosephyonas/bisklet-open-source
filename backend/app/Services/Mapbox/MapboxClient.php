<?php

namespace App\Services\Mapbox;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MapboxClient
{
    private string $accessToken;
    private string $defaultStyle;
    private string $profile;
    private bool $snapToRoad;

    public function __construct()
    {
        $config = config('services.mapbox', []);

        $this->accessToken = (string) Arr::get($config, 'access_token', '');
        $this->defaultStyle = (string) Arr::get($config, 'default_style', 'mapbox://styles/mapbox/outdoors-v12');
        $this->profile = (string) Arr::get($config, 'navigation_profile', 'cycling');
        $this->snapToRoad = (bool) Arr::get($config, 'snap_to_road', true);
    }

    public function accessToken(): string
    {
        if ($this->accessToken === '') {
            throw ValidationException::withMessages([
                'mapbox' => 'Mapbox access token is not configured.',
            ]);
        }

        return $this->accessToken;
    }

    public function defaultStyle(): string
    {
        return $this->defaultStyle;
    }

    public function navigationProfile(): string
    {
        return $this->profile;
    }

    public function directions(
        float $originLat,
        float $originLng,
        float $destinationLat,
        float $destinationLng,
        array $options = []
    ): array {
        $coordinates = sprintf(
            '%s,%s;%s,%s',
            $this->formatCoordinate($originLng),
            $this->formatCoordinate($originLat),
            $this->formatCoordinate($destinationLng),
            $this->formatCoordinate($destinationLat),
        );

        $query = array_merge([
            'geometries' => 'geojson',
            'overview' => 'full',
            'steps' => true,
            'annotations' => 'distance,duration,speed',
            'alternatives' => false,
            'access_token' => $this->accessToken(),
        ], $options);

        if ($this->snapToRoad) {
            $query['continue_straight'] = true;
        }

        $response = Http::baseUrl('https://api.mapbox.com')
            ->timeout(10)
            ->get("/directions/v5/mapbox/{$this->profile}/{$coordinates}", $query);

        if ($response->failed()) {
            $message = Arr::get($response->json(), 'message');

            throw ValidationException::withMessages([
                'mapbox' => $message ?: 'Unable to fetch directions from Mapbox.',
            ]);
        }

        return $response->json();
    }

    public function snapToRoad(
        float $latitude,
        float $longitude,
        ?float $bearingDegrees = null,
        ?float $accuracy = null
    ): array {
        $response = Http::baseUrl('https://api.mapbox.com')
            ->timeout(5)
            ->get('/matching/v5/mapbox/'.$this->profile.'/'.$this->formatCoordinate($longitude).','.$this->formatCoordinate($latitude), [
                'access_token' => $this->accessToken(),
                'radiuses' => $accuracy !== null ? (int) round($accuracy) : 10,
                'timestamps' => now()->timestamp,
                'bearings' => $bearingDegrees !== null ? sprintf('%d,90', (int) round($bearingDegrees)) : null,
                'geometries' => 'geojson',
            ]);

        if ($response->failed()) {
            return [
                'matched' => false,
                'geometry' => null,
            ];
        }

        $data = $response->json();

        return [
            'matched' => Arr::get($data, 'code') === 'Ok',
            'geometry' => Arr::get($data, 'matchings.0.geometry'),
        ];
    }

    private function formatCoordinate(float $coordinate): string
    {
        return Str::of(number_format($coordinate, 6, '.', ''))->trim();
    }
}
