<?php

namespace App\Services;

use App\Models\ServiceArea;

class GeofenceService
{
    // Polygon vertices in [lng, lat] order. The ring is closed by repeating the first point.
    public const FALLBACK_ZONE_POLYGON = [
        [38.7890, 9.0220],
        [38.7960, 9.0200],
        [38.8010, 9.0140],
        [38.8030, 9.0060],
        [38.7970, 8.9990],
        [38.7920, 8.9930],
        [38.7880, 8.9870],
        [38.7840, 8.9800],
        [38.7820, 8.9740],
        [38.7810, 8.9670],
        [38.7800, 8.9620],
        [38.7795, 8.9590],
        [38.7870, 8.9580],
        [38.7890, 8.9610],
        [38.7900, 8.9650],
        [38.7910, 8.9710],
        [38.7920, 8.9780],
        [38.7930, 8.9840],
        [38.7950, 8.9890],
        [38.8000, 8.9960],
        [38.8060, 9.0020],
        [38.8080, 9.0080],
        [38.8100, 9.0130],
        [38.8090, 9.0190],
        [38.8020, 9.0240],
        [38.7890, 9.0220],
    ];

    /**
     * Returns true when ($lat, $lng) is inside the active service-area polygon.
     */
    public function isInsideZone(float $lat, float $lng): bool
    {
        $poly = $this->polygon();
        $n = count($poly);
        $inside = false;
        $j = $n - 1;

        for ($i = 0; $i < $n; $i++) {
            $xi = $poly[$i][0];
            $yi = $poly[$i][1];
            $xj = $poly[$j][0];
            $yj = $poly[$j][1];

            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }

    public function toGeoJson(): string
    {
        return json_encode($this->toFeatureCollection());
    }

    /**
     * @return array{type: string, features: array<int, array<string, mixed>>}
     */
    public function toFeatureCollection(): array
    {
        $serviceArea = ServiceArea::active();
        $polygon = $this->polygon($serviceArea);

        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$polygon],
                ],
                'properties' => [
                    'name' => $serviceArea?->name ?? 'Bisklet Service Area',
                    'slug' => $serviceArea?->slug ?? 'fallback-service-area',
                    'fill_color' => $serviceArea?->fill_color ?? config('branding.colors.primary_teal'),
                    'stroke_color' => $serviceArea?->stroke_color ?? config('branding.colors.deep_teal'),
                ],
            ]],
        ];
    }

    /**
     * @return array<int, array{0: float|int, 1: float|int}>
     */
    public function polygon(?ServiceArea $serviceArea = null): array
    {
        $serviceArea ??= ServiceArea::active();
        $polygon = $serviceArea?->polygon;

        if (! is_array($polygon) || count($polygon) < 4) {
            return self::FALLBACK_ZONE_POLYGON;
        }

        return $polygon;
    }
}
