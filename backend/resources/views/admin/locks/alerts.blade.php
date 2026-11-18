@extends('layouts.admin')

@section('title', 'Lock Alerts')
@section('subtitle', 'A focused queue of alarms, disconnected locks, and batteries needing service.')

@section('content')
    @include('admin.locks.partials.nav')

    <div class="ops-stack">
        <section class="ops-summary" aria-label="Alert summary">
            <div class="ops-stat {{ $operationalAlerts->isNotEmpty() ? 'ops-stat--danger' : '' }}">
                <span class="ops-stat__label">Open alerts</span>
                <strong class="ops-stat__value">{{ number_format($operationalAlerts->count()) }}</strong>
                <span class="ops-stat__hint">Operator action queue</span>
            </div>
            <div class="ops-stat {{ $alarms->isNotEmpty() ? 'ops-stat--danger' : '' }}">
                <span class="ops-stat__label">Gateway alarms</span>
                <strong class="ops-stat__value">{{ number_format($alarms->count()) }}</strong>
                <span class="ops-stat__hint">Latest alarm records</span>
            </div>
            <div class="ops-stat {{ $offlineDevices->isNotEmpty() ? 'ops-stat--danger' : '' }}">
                <span class="ops-stat__label">Offline</span>
                <strong class="ops-stat__value">{{ number_format($offlineDevices->count()) }}</strong>
                <span class="ops-stat__hint">No live TCP session</span>
            </div>
            <div class="ops-stat {{ $lowBatteryDevices->isNotEmpty() ? 'ops-stat--warning' : '' }}">
                <span class="ops-stat__label">Low battery</span>
                <strong class="ops-stat__value">{{ number_format($lowBatteryDevices->count()) }}</strong>
                <span class="ops-stat__hint">At or below {{ $batteryThreshold }}%</span>
            </div>
        </section>

        <form method="GET" class="ops-filter">
            <div class="ops-filter__grid">
                <div class="ops-filter__field">
                    <label for="battery_threshold">Low-battery threshold</label>
                    <input type="number" id="battery_threshold" name="battery_threshold" min="0" max="100" value="{{ $batteryThreshold }}">
                </div>
            </div>
            <div class="ops-filter__actions">
                <a href="{{ route('admin.locks.alerts') }}" class="ops-button">Reset to 20%</a>
                <button type="submit" class="ops-button ops-button--primary">Update threshold</button>
            </div>
        </form>

        <section class="ops-panel {{ $operationalAlerts->isNotEmpty() ? 'ops-panel--danger' : '' }}">
            <header class="ops-panel__header">
                <div><h2>Open operational alerts</h2><p>Prioritized alerts that need investigation or maintenance.</p></div>
                <span class="ops-badge {{ $operationalAlerts->isNotEmpty() ? 'ops-badge--danger' : 'ops-badge--success' }}">{{ $operationalAlerts->count() }} open</span>
            </header>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead><tr><th>Alert</th><th>Device / bike</th><th>Severity</th><th>Occurred</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($operationalAlerts as $alert)
                            @php
                                $hasCoordinates = is_numeric($alert->latitude) && is_numeric($alert->longitude);
                            @endphp
                            <tr>
                                <td>
                                    <span class="ops-primary">{{ $alert->title }}</span>
                                    @if ($alert->body)<span class="ops-secondary">{{ \Illuminate\Support\Str::limit($alert->body, 100) }}</span>@endif
                                </td>
                                <td>
                                    <span class="ops-primary ops-mono">{{ $alert->imei ?? 'No IMEI' }}</span>
                                    @if ($alert->bike)<span class="ops-secondary">{{ $alert->bike->code }}{{ $alert->bike->model ? ' · '.$alert->bike->model : '' }}</span>@endif
                                </td>
                                <td>
                                    <span class="ops-badge {{ $alert->severity === 'critical' ? 'ops-badge--danger' : 'ops-badge--warning' }}">{{ ucfirst($alert->severity) }}</span>
                                    <span class="ops-secondary">{{ str_replace('_', ' ', $alert->source) }}</span>
                                </td>
                                <td>{{ optional($alert->occurred_at ?? $alert->created_at)->diffForHumans() ?? 'Now' }}</td>
                                <td class="ops-table__right ops-table__actions">
                                    <div class="ops-actions">
                                        @if ($alert->imei)<a href="{{ route('admin.locks.devices.show', $alert->imei) }}" class="ops-button ops-button--small">Device</a>@endif
                                        @if ($alert->bike_id)<a href="{{ route('admin.maintenance.index', ['bike_id' => $alert->bike_id, 'status' => 'open']) }}" class="ops-button ops-button--small">Maintenance</a>@endif
                                        @if ($hasCoordinates)<a href="https://maps.google.com/?q={{ $alert->latitude }},{{ $alert->longitude }}" target="_blank" rel="noopener" class="ops-button ops-button--small">Map</a>@endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ops-empty"><strong>No open operational alerts</strong><span>The action queue is clear.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ops-panel">
            <header class="ops-panel__header">
                <div><h2>Recent gateway alarms</h2><p>Raw alarm events kept separate from the operator action queue.</p></div>
                <span class="ops-badge {{ $alarms->isNotEmpty() ? 'ops-badge--danger' : '' }}">{{ $alarms->count() }} shown</span>
            </header>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead><tr><th>Device</th><th>Alarm</th><th>Location</th><th class="ops-table__right">Reported</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($alarms as $alarm)
                            @php
                                $alarmDevice = $alarm->imei ? ($alarmDevices[$alarm->imei] ?? null) : null;
                                $latitude = data_get($alarm->payload, 'lat', data_get($alarm->payload, 'payload.lat', $alarmDevice?->last_lat));
                                $longitude = data_get($alarm->payload, 'lng', data_get($alarm->payload, 'payload.lng', $alarmDevice?->last_lng));
                                $hasCoordinates = is_numeric($latitude) && is_numeric($longitude);
                            @endphp
                            <tr>
                                <td><span class="ops-primary ops-mono">{{ $alarm->imei ?? 'Unknown' }}</span>@if ($alarmDevice?->bike)<span class="ops-secondary">{{ $alarmDevice->bike->code }}</span>@endif</td>
                                <td><span class="ops-badge ops-badge--danger">Type {{ data_get($alarm->payload, 'type', 'unknown') }}</span></td>
                                <td>{{ $hasCoordinates ? number_format((float) $latitude, 5).', '.number_format((float) $longitude, 5) : 'No coordinates' }}</td>
                                <td class="ops-table__right">{{ $alarm->created_at?->diffForHumans() ?? '—' }}</td>
                                <td class="ops-table__right ops-table__actions">
                                    <div class="ops-actions">
                                        @if ($alarmDevice)<a href="{{ route('admin.locks.devices.show', $alarmDevice->imei) }}" class="ops-button ops-button--small">Device</a>@endif
                                        @if ($hasCoordinates)<a href="https://maps.google.com/?q={{ $latitude }},{{ $longitude }}" target="_blank" rel="noopener" class="ops-button ops-button--small">Map</a>@endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ops-empty"><strong>No gateway alarms</strong><span>No alarm events have been recorded.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="ops-grid">
            <section class="ops-panel {{ $offlineDevices->isNotEmpty() ? 'ops-panel--danger' : '' }}">
                <header class="ops-panel__header"><div><h2>Offline devices</h2><p>Locks with no live gateway session.</p></div><span class="ops-badge">{{ $offlineDevices->count() }}</span></header>
                <div class="ops-table-wrap">
                    <table class="ops-table">
                        <thead><tr><th>Device</th><th>Battery</th><th class="ops-table__right">Last seen</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($offlineDevices as $device)
                                <tr>
                                    <td><span class="ops-primary ops-mono">{{ $device->imei }}</span><span class="ops-secondary">{{ $device->bike?->code ?? 'Unassigned' }}</span></td>
                                    <td><span class="ops-badge {{ $device->battery_percent !== null && $device->battery_percent <= $batteryThreshold ? 'ops-badge--danger' : '' }}">{{ $device->battery_percent !== null ? $device->battery_percent.'%' : 'Unknown' }}</span></td>
                                    <td class="ops-table__right">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="ops-table__right"><a href="{{ route('admin.locks.devices.show', $device->imei) }}" class="ops-button ops-button--small">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ops-empty"><strong>All devices online</strong><span>No connectivity action needed.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ops-panel {{ $lowBatteryDevices->isNotEmpty() ? 'ops-panel--warning' : '' }}">
                <header class="ops-panel__header"><div><h2>Low battery</h2><p>Locks at or below {{ $batteryThreshold }}%.</p></div><span class="ops-badge ops-badge--warning">{{ $lowBatteryDevices->count() }}</span></header>
                <div class="ops-table-wrap">
                    <table class="ops-table">
                        <thead><tr><th>Device</th><th>Battery</th><th class="ops-table__right">Last seen</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($lowBatteryDevices as $device)
                                <tr>
                                    <td><span class="ops-primary ops-mono">{{ $device->imei }}</span><span class="ops-secondary">{{ $device->bike?->code ?? 'Unassigned' }}</span></td>
                                    <td><span class="ops-badge ops-badge--warning">{{ $device->battery_percent }}%</span></td>
                                    <td class="ops-table__right">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="ops-table__right"><a href="{{ route('admin.locks.devices.show', $device->imei) }}" class="ops-button ops-button--small">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ops-empty"><strong>Battery levels healthy</strong><span>No battery service needed.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection
