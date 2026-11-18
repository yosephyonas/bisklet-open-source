@extends('layouts.admin')

@section('title', 'Service Area')
@section('subtitle', 'Draw the geofence polygon riders must stay inside.')

@section('actions')
    <button type="submit" form="service-area-form" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
        Save geofence
    </button>
@endsection

@push('styles')
    @if (filled($mapboxToken))
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css">
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.5.0/mapbox-gl-draw.css">
    @endif
    <style>
        .service-area-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1fr) 360px;
        }

        .service-area-map {
            width: 100%;
            height: min(68vh, 680px);
            min-height: 480px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--neutral-divider);
            box-shadow: 0 1px 2px rgb(var(--neutral-ink-rgb) / 0.05);
            background: #f3f7f6;
        }

        .service-area-panel {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .service-area-stat {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid var(--grey-150);
            font-size: 0.85rem;
            color: var(--grey-600);
        }

        .service-area-stat strong {
            color: var(--grey-900);
        }

        .service-area-textarea {
            min-height: 180px;
            font-family: "Roboto Mono", Menlo, Consolas, monospace;
            font-size: 0.78rem;
        }

        .service-area-help {
            margin: 0;
            color: var(--grey-500);
            font-size: 0.82rem;
        }

        @media (max-width: 1023px) {
            .service-area-grid {
                grid-template-columns: 1fr;
            }

            .service-area-map {
                height: 520px;
            }
        }
    </style>
@endpush

@section('content')
    <form id="service-area-form" method="POST" action="{{ route('admin.service-area.update') }}" class="service-area-grid">
        @csrf
        @method('PUT')

        <div class="card">
            @if (filled($mapboxToken))
                <div id="service-area-map" class="service-area-map"></div>
            @else
                <div class="service-area-map flex items-center justify-center p-6 text-center text-sm text-slate-600">
                    Add MAPBOX_ACCESS_TOKEN to the backend environment to enable map drawing.
                </div>
            @endif
        </div>

        <aside class="service-area-panel">
            <div class="card">
                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $serviceArea?->name ?? 'Bole Service Area') }}" class="mt-2" required>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="fill_color" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Fill</label>
                        <input id="fill_color" name="fill_color" type="color" value="{{ old('fill_color', $serviceArea?->fill_color ?? config('branding.colors.primary_teal')) }}" class="mt-2 h-10 w-full rounded-xl border border-slate-200">
                    </div>
                    <div>
                        <label for="stroke_color" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Border</label>
                        <input id="stroke_color" name="stroke_color" type="color" value="{{ old('stroke_color', $serviceArea?->stroke_color ?? config('branding.colors.deep_teal')) }}" class="mt-2 h-10 w-full rounded-xl border border-slate-200">
                    </div>
                </div>

                <div class="mt-4">
                    <div class="service-area-stat">
                        <span>Polygon points</span>
                        <strong data-point-count>0</strong>
                    </div>
                    <div class="service-area-stat">
                        <span>Status</span>
                        <strong>{{ $serviceArea ? 'Saved' : 'Using fallback' }}</strong>
                    </div>
                </div>
            </div>

            <div class="card">
                <p class="service-area-help">
                    Use the polygon tool on the map, click around the service boundary, then click the first point to close it. Draw only one polygon; saving replaces the active service area.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="button-outline" data-reset-polygon>Reset polygon</button>
                    <button type="button" class="button-outline" data-fit-polygon>Fit map</button>
                </div>
            </div>

            <div class="card">
                <label for="polygon-preview" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">GeoJSON preview</label>
                <textarea id="polygon-preview" class="service-area-textarea mt-2" readonly></textarea>
                <input type="hidden" id="polygon" name="polygon" value="{{ old('polygon') }}">
            </div>
        </aside>
    </form>
@endsection

@push('scripts')
    @if (filled($mapboxToken))
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js"></script>
        <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.5.0/mapbox-gl-draw.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const token = @json($mapboxToken);
                const style = @json($mapboxStyle);
                const initialGeojson = @json($geojson);
                const fallbackPolygon = @json($fallbackPolygon);
                const polygonInput = document.getElementById('polygon');
                const preview = document.getElementById('polygon-preview');
                const pointCount = document.querySelector('[data-point-count]');
                const fillInput = document.getElementById('fill_color');
                const strokeInput = document.getElementById('stroke_color');

                mapboxgl.accessToken = token;

                const firstFeature = initialGeojson.features[0];
                const initialRing = firstFeature.geometry.coordinates[0] || fallbackPolygon;
                const center = initialRing.reduce(function (acc, point) {
                    acc.lng += point[0];
                    acc.lat += point[1];
                    return acc;
                }, { lng: 0, lat: 0 });
                center.lng = center.lng / initialRing.length;
                center.lat = center.lat / initialRing.length;

                const map = new mapboxgl.Map({
                    container: 'service-area-map',
                    style: style || 'mapbox://styles/mapbox/streets-v12',
                    center: [center.lng, center.lat],
                    zoom: 12.5
                });

                map.addControl(new mapboxgl.NavigationControl(), 'top-left');

                const draw = new MapboxDraw({
                    displayControlsDefault: false,
                    controls: {
                        polygon: true,
                        trash: true
                    },
                    defaultMode: 'simple_select',
                    styles: [
                        {
                            id: 'gl-draw-polygon-fill-inactive',
                            type: 'fill',
                            filter: ['all', ['==', 'active', 'false'], ['==', '$type', 'Polygon']],
                            paint: { 'fill-color': fillInput.value, 'fill-opacity': 0.18 }
                        },
                        {
                            id: 'gl-draw-polygon-fill-active',
                            type: 'fill',
                            filter: ['all', ['==', 'active', 'true'], ['==', '$type', 'Polygon']],
                            paint: { 'fill-color': fillInput.value, 'fill-opacity': 0.24 }
                        },
                        {
                            id: 'gl-draw-polygon-stroke',
                            type: 'line',
                            filter: ['all', ['==', '$type', 'Polygon']],
                            paint: { 'line-color': strokeInput.value, 'line-width': 3 }
                        },
                        {
                            id: 'gl-draw-polygon-and-line-vertex',
                            type: 'circle',
                            filter: ['all', ['==', 'meta', 'vertex'], ['==', '$type', 'Point']],
                            paint: { 'circle-radius': 5, 'circle-color': strokeInput.value }
                        },
                        {
                            id: 'gl-draw-line',
                            type: 'line',
                            filter: ['all', ['==', '$type', 'LineString']],
                            paint: { 'line-color': strokeInput.value, 'line-width': 3 }
                        }
                    ]
                });

                map.addControl(draw, 'top-left');

                function currentFeatureCollection() {
                    const data = draw.getAll();
                    if (data.features.length <= 1) {
                        return data;
                    }

                    const latest = data.features[data.features.length - 1];
                    draw.deleteAll();
                    draw.add(latest);
                    return draw.getAll();
                }

                function syncPolygon() {
                    const data = currentFeatureCollection();
                    const feature = data.features[0] || null;
                    const ring = feature && feature.geometry && feature.geometry.type === 'Polygon'
                        ? feature.geometry.coordinates[0]
                        : [];

                    const payload = feature
                        ? {
                            type: 'FeatureCollection',
                            features: [feature]
                        }
                        : { type: 'FeatureCollection', features: [] };

                    polygonInput.value = JSON.stringify(payload);
                    preview.value = JSON.stringify(payload, null, 2);
                    pointCount.textContent = Math.max(0, ring.length - 1).toString();
                }

                function fitPolygon() {
                    const data = draw.getAll();
                    const feature = data.features[0];
                    if (!feature || feature.geometry.type !== 'Polygon') {
                        return;
                    }

                    const bounds = new mapboxgl.LngLatBounds();
                    feature.geometry.coordinates[0].forEach(function (point) {
                        bounds.extend(point);
                    });
                    map.fitBounds(bounds, { padding: 64, duration: 500 });
                }

                function resetPolygon() {
                    draw.deleteAll();
                    draw.add(initialGeojson);
                    syncPolygon();
                    fitPolygon();
                }

                map.on('load', function () {
                    resetPolygon();
                });

                map.on('draw.create', syncPolygon);
                map.on('draw.update', syncPolygon);
                map.on('draw.delete', syncPolygon);
                map.on('draw.selectionchange', syncPolygon);

                document.querySelector('[data-reset-polygon]').addEventListener('click', resetPolygon);
                document.querySelector('[data-fit-polygon]').addEventListener('click', fitPolygon);
                document.getElementById('service-area-form').addEventListener('submit', function (event) {
                    syncPolygon();
                    const data = JSON.parse(polygonInput.value || '{"features":[]}');
                    if (!data.features || data.features.length === 0) {
                        event.preventDefault();
                        alert('Draw a polygon before saving.');
                    }
                });
            });
        </script>
    @endif
@endpush
