@extends('layouts.admin')

@php
    $telemetry = data_get($bike->metadata, 'telemetry');
@endphp

@section('title', 'Bike ' . $bike->code)
@section('subtitle', 'Real time status, hardware profile, and recent usage for this bike.')

@section('hero')
    <div class="page-hero">
        <div class="hero-grid">
            <div class="hero-stat">
                <span class="label">Status</span>
                <span class="value">{{ ucfirst(str_replace('_', ' ', $bike->status?->value ?? $bike->status)) }}</span>
                <span class="helper">{{ $bike->is_active ? 'Operational' : 'Unavailable' }}</span>
            </div>
            <div class="hero-stat">
                <span class="label">Battery</span>
                <span class="value">{{ $bike->battery_level !== null ? number_format($bike->battery_level, 0) . '%' : 'N/A' }}</span>
                <span class="helper">Odometer {{ $bike->odometer_km !== null ? number_format($bike->odometer_km, 1) . ' km' : 'unknown' }}</span>
            </div>
            <div class="hero-stat">
                <span class="label">Last Service</span>
                <span class="value">{{ optional($bike->last_service_at)->format('M d') ?? 'Never' }}</span>
                <span class="helper">{{ optional($bike->last_service_at)->diffForHumans() ?? 'Schedule service' }}</span>
            </div>
            <div class="hero-stat">
                <span class="label">Station</span>
                <span class="value">{{ $bike->station?->code ?? 'Floating' }}</span>
                <span class="helper">{{ $bike->station?->name ?? 'No dock assigned' }}</span>
            </div>
        </div>
        <div class="hero-actions">
            <span class="badge-soft-blue">Mapbox: {{ \Illuminate\Support\Str::limit(config('services.mapbox.default_style'), 22, '...') }}</span>
            <span class="badge-soft-green">Tracking: API polling</span>
        </div>
    </div>
@endsection

@push('styles')
    @if (filled(config('services.mapbox.access_token')))
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css">
    @endif
@endpush

@push('scripts')
    @if (filled(config('services.mapbox.access_token')))
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof mapboxgl === 'undefined') {
                    return;
                }

                const token = @json(config('services.mapbox.access_token'));
                const style = @json(config('services.mapbox.default_style'));
                const telemetry = @json($telemetry);
                const hasTelemetry = telemetry && telemetry.longitude !== undefined && telemetry.latitude !== undefined;
                const fallbackCenter = [38.7565, 9.0301]; // Addis Ababa by default

                const lng = hasTelemetry ? Number(telemetry.longitude) : fallbackCenter[0];
                const lat = hasTelemetry ? Number(telemetry.latitude) : fallbackCenter[1];
                const speed = hasTelemetry ? Number(telemetry.speed_kph ?? 0) : 0;
                const geometry = hasTelemetry && telemetry.mapbox_geometry ? telemetry.mapbox_geometry : null;

                if (!token) {
                    return;
                }

                mapboxgl.accessToken = token;

                const map = new mapboxgl.Map({
                    container: 'bike-map-{{ $bike->id }}',
                    style,
                    center: [lng, lat],
                    zoom: hasTelemetry ? 15.5 : 12,
                });

                map.addControl(new mapboxgl.NavigationControl(), 'top-left');

                const placeholder = document.querySelector('#bike-map-{{ $bike->id }} .bike-map-placeholder');

                map.on('load', function () {
                    if (hasTelemetry) {
                        if (placeholder) {
                            placeholder.remove();
                        }

                        new mapboxgl.Marker({ color: '{{ config('branding.colors.primary_teal') }}' })
                            .setLngLat([lng, lat])
                            .setPopup(new mapboxgl.Popup({ offset: 16 }).setHTML(`
                                <div style="font-size:12px;line-height:1.4;">
                                    <strong>Bike {{ $bike->code }}</strong><br>
                                    Lat ${lat.toFixed(5)}, Lng ${lng.toFixed(5)}<br>
                                    Speed ${speed.toFixed(1)} km/h
                                </div>
                            `))
                            .addTo(map);

                        if (geometry) {
                            map.addSource('bike-trace', {
                                type: 'geojson',
                                data: geometry,
                            });
                            map.addLayer({
                                id: 'bike-trace-line',
                                type: 'line',
                                source: 'bike-trace',
                                paint: {
                                    'line-color': '{{ config('branding.colors.primary_teal') }}',
                                    'line-width': 4,
                                    'line-opacity': 0.75,
                                },
                            });
                        }
                    }
                });
            });
        </script>
    @endif
@endpush

@section('actions')
    <a href="{{ route('admin.bikes.edit', $bike) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 013.182 3.182l-9.546 9.546a4.5 4.5 0 01-1.897 1.13l-3.141.898.898-3.141a4.5 4.5 0 011.13-1.897z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
        </svg>
        <span>Edit</span>
    </a>
    <form action="{{ route('admin.bikes.destroy', $bike) }}" method="POST" onsubmit="return confirm('Delete this bike? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 3v7m6-7v7M9 4h6a1 1 0 011 1v2H8V5a1 1 0 011-1z" />
            </svg>
            <span>Delete</span>
        </button>
    </form>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $bike->status?->value ?? $bike->status)) }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Active</dt><dd class="font-semibold {{ $bike->is_active ? 'text-emerald-600' : 'text-rose-600' }}">{{ $bike->is_active ? 'Yes' : 'No' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Battery</dt><dd>{{ $bike->battery_level !== null ? $bike->battery_level . '%' : 'N/A' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Last service</dt><dd>{{ optional($bike->last_service_at)->format('M d, Y H:i') ?? 'Never' }}</dd></div>
            </dl>
        </div>

        <div class="card">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Assignment</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $bike->station?->name ?? 'Unassigned' }}</p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Station code</dt><dd>{{ $bike->station?->code ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Model</dt><dd>{{ $bike->model ?? 'Standard' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Type</dt><dd>{{ $bike->type ?? 'Standard' }}</dd></div>
                <div class="flex items-center justify-between"><dt>Lock code</dt><dd>{{ $bike->device?->lock_code ?? '-' }}</dd></div>
                <div class="flex items-center justify-between"><dt>IMEI</dt><dd>{{ $bike->imei ?? '-' }}</dd></div>
            </dl>
        </div>

        <div class="card">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pricing</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">ETB {{ number_format(config('pricing.rides.price_birr', 10), 2) }} <span class="text-sm font-medium text-slate-500">per {{ config('pricing.rides.minutes', 20) }} min block</span></p>
            <dl class="mt-4 space-y-1 text-xs text-slate-500">
                <div class="flex items-center justify-between"><dt>Unlock fee</dt><dd>ETB {{ number_format(config('pricing.rides.unlock_fee', 5), 2) }}</dd></div>
                <div class="flex items-center justify-between"><dt>Odometer</dt><dd>{{ $bike->odometer_km !== null ? number_format($bike->odometer_km, 1) . ' km' : 'N/A' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="mt-6 bg-layer">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Latest telemetry</p>
                <p class="text-xs text-slate-600">
                    Location is saved by the backend and refreshed by API reads.
                </p>
            </div>
            <div class="text-xs text-slate-600 space-y-1">
                @if ($telemetry)
                    <p>
                        <span class="badge-soft-blue">Lat {{ number_format($telemetry['latitude'], 5) }}</span>
                        <span class="badge-soft-blue">Lng {{ number_format($telemetry['longitude'], 5) }}</span>
                    </p>
                    <p class="text-slate-500">
                        Last update {{ \Carbon\Carbon::parse($telemetry['updated_at'] ?? $telemetry['captured_at'] ?? now())->diffForHumans() }}
                    </p>
                @else
                    <p class="text-slate-500">No telemetry received yet.</p>
                @endif
            </div>
        </div>
    </div>

@php
    $mapboxToken = config('services.mapbox.access_token');
@endphp
@if (filled($mapboxToken))
    <div class="card mt-8 bike-map-card">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div>
                <p class="text-sm font-semibold text-slate-900">Mapbox position</p>
                <p class="text-xs text-slate-600">Style: {{ config('services.mapbox.default_style') }}</p>
            </div>
            <span class="badge-soft-blue">Profile: {{ config('services.mapbox.navigation_profile') }}</span>
        </div>
        <div class="bike-map-canvas" id="bike-map-{{ $bike->id }}">
            @unless ($telemetry)
                <div class="bike-map-placeholder">
                    <div>
                        <strong>No telemetry yet.</strong><br>
                        Post live coordinates to <code class="badge-micro">/api/v1/bikes/{{ $bike->id }}/telemetry</code> to draw this route.
                    </div>
                </div>
            @endunless
        </div>
    </div>
@endif

    @if ($bike->hardware || $bike->metadata)
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            @if ($bike->hardware)
                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Hardware profile</p>
                        <span class="text-xs text-slate-500">JSON</span>
                    </div>
                    <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($bike->hardware, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
            @if ($bike->metadata)
                <div class="card">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">Metadata</p>
                        <span class="text-xs text-slate-500">JSON</span>
                    </div>
                    <pre class="mt-4 overflow-auto rounded-xl bg-slate-950/90 p-4 text-xs leading-relaxed text-slate-100">{{ json_encode($bike->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Recent maintenance</p>
                <a href="{{ route('admin.maintenance.index', ['bike_id' => $bike->id]) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">See all</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($bike->maintenanceLogs as $log)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ ucfirst($log->status) }}</p>
                            <p class="text-xs text-slate-500">{{ $log->type }} @ {{ optional($log->performed_at ?? $log->created_at)->format('M d, Y') }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            @if ($log->cost)
                                <p class="font-semibold text-slate-900">ETB {{ number_format($log->cost, 2) }}</p>
                            @endif
                            <p>{{ $log->user?->first_name }} {{ $log->user?->last_name }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No maintenance history yet.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-slate-900">Recent rides</p>
                <a href="{{ route('admin.rides.index', ['bike_code' => $bike->code]) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">See all</a>
            </div>
            <div class="mt-4 divide-y divide-slate-100 text-sm text-slate-600">
                @forelse ($bike->rides as $ride)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <a href="{{ route('admin.rides.show', $ride) }}" class="font-semibold text-slate-900 hover:text-slate-700">Ride #{{ $ride->id }}</a>
                            <p class="text-xs text-slate-500">{{ $ride->user?->first_name }} {{ $ride->user?->last_name }} - {{ strtoupper($ride->status?->value ?? $ride->status) }}</p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p>{{ optional($ride->started_at)->format('M d, H:i') ?? '-' }}</p>
                            @if ($ride->total_amount)
                                <p class="font-semibold text-slate-900">ETB {{ number_format($ride->total_amount, 2) }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">No rides recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
