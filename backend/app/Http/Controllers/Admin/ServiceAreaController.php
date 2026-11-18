<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceArea;
use App\Services\GeofenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ServiceAreaController extends Controller
{
    public function edit(GeofenceService $geofence): View
    {
        $serviceArea = ServiceArea::active();

        return view('admin.service-areas.edit', [
            'serviceArea' => $serviceArea,
            'geojson' => $geofence->toFeatureCollection(),
            'mapboxToken' => config('services.mapbox.access_token'),
            'mapboxStyle' => config('services.mapbox.default_style', 'mapbox://styles/mapbox/streets-v12'),
            'fallbackPolygon' => GeofenceService::FALLBACK_ZONE_POLYGON,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'polygon' => ['required', 'json'],
            'fill_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stroke_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $polygon = json_decode($validated['polygon'], true);
        $polygon = $this->normalizePolygon($polygon);

        ServiceArea::query()->update(['is_active' => false]);

        ServiceArea::query()->updateOrCreate(
            ['slug' => 'primary-service-area'],
            [
                'name' => $validated['name'],
                'polygon' => $polygon,
                'fill_color' => $validated['fill_color'],
                'stroke_color' => $validated['stroke_color'],
                'is_active' => true,
            ],
        );

        return redirect()
            ->route('admin.service-area.edit')
            ->with('success', 'Service area geofence saved successfully.');
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function normalizePolygon(array $polygon): array
    {
        $ring = Arr::get($polygon, 'type') === 'FeatureCollection'
            ? Arr::get($polygon, 'features.0.geometry.coordinates.0', [])
            : (Arr::get($polygon, 'type') === 'Feature'
                ? Arr::get($polygon, 'geometry.coordinates.0', [])
                : (Arr::get($polygon, 'type') === 'Polygon'
                    ? Arr::get($polygon, 'coordinates.0', [])
                    : $polygon));

        $coords = [];
        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            $lng = (float) $point[0];
            $lat = (float) $point[1];

            if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) {
                continue;
            }

            $coords[] = [$lng, $lat];
        }

        if (count($coords) < 3) {
            abort(422, 'Draw a polygon with at least three points.');
        }

        $first = $coords[0];
        $last = $coords[count($coords) - 1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $coords[] = $first;
        }

        if (count($coords) < 4) {
            abort(422, 'Draw a closed polygon with at least three points.');
        }

        return $coords;
    }
}
