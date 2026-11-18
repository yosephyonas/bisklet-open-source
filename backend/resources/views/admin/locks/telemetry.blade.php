@extends('layouts.admin')

@section('title', 'Telemetry')
@section('subtitle', 'Inspect recent device packets and lock events without the raw listener noise.')

@section('content')
    @include('admin.locks.partials.nav')

    <div class="ops-stack">
        <section class="ops-summary ops-summary--compact">
            <div class="ops-stat ops-stat--success">
                <span class="ops-stat__label">Device packets</span>
                <strong class="ops-stat__value">{{ number_format($recentCounts['telemetryHour']) }}</strong>
                <span class="ops-stat__hint">Received in the last hour</span>
            </div>
            <div class="ops-stat">
                <span class="ops-stat__label">Lock events</span>
                <strong class="ops-stat__value">{{ number_format($recentCounts['eventsHour']) }}</strong>
                <span class="ops-stat__hint">Recorded in the last hour</span>
            </div>
        </section>

        <form method="GET" class="ops-filter">
            <div class="ops-filter__grid">
                <div class="ops-filter__field">
                    <label for="imei">Device IMEI</label>
                    <input type="search" id="imei" name="imei" value="{{ $filters['imei'] ?? '' }}" placeholder="Search IMEI">
                </div>
                <div class="ops-filter__field">
                    <label for="type">Packet type</label>
                    <select id="type" name="type">
                        <option value="">All packet types</option>
                        @foreach ($telemetryTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-filter__field">
                    <label for="event">Event type</label>
                    <select id="event" name="event">
                        <option value="">All event types</option>
                        @foreach ($eventTypes as $eventType)
                            <option value="{{ $eventType }}" @selected(($filters['event'] ?? '') === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="ops-filter__actions">
                <a href="{{ route('admin.locks.telemetry') }}" class="ops-button">Clear</a>
                <button type="submit" class="ops-button ops-button--primary">Apply filters</button>
            </div>
        </form>

        <section class="ops-panel">
            <header class="ops-panel__header">
                <div><h2>Device packets</h2><p>Parsed heartbeats, GPS updates, and device status reports.</p></div>
                <span class="ops-badge">{{ $telemetry->count() }} shown</span>
            </header>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead><tr><th>Device</th><th>Type</th><th>Key data</th><th class="ops-table__right">Received</th></tr></thead>
                    <tbody>
                        @forelse ($telemetry as $event)
                            @php
                                $payload = json_encode($event->parsed_json ?? [], JSON_UNESCAPED_SLASHES);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.locks.devices.show', $event->imei) }}" class="ops-primary ops-mono">{{ $event->imei }}</a>
                                    @if (isset($bikeNames[$event->imei]))<span class="ops-secondary">{{ $bikeNames[$event->imei] }}</span>@endif
                                </td>
                                <td><span class="ops-badge">{{ $event->type ?? 'Unknown' }}</span></td>
                                <td><span class="ops-secondary ops-mono" title="{{ $payload }}">{{ \Illuminate\Support\Str::limit($payload, 90) }}</span></td>
                                <td class="ops-table__right">{{ optional($event->received_at ?? $event->created_at)->format('M d, H:i:s') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ops-empty"><strong>No packets found</strong><span>Try clearing the filters or check the listener.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ops-panel">
            <header class="ops-panel__header">
                <div><h2>Lock events</h2><p>Commands, alarms, and lifecycle events recorded by the backend.</p></div>
                <span class="ops-badge">{{ $lockEvents->count() }} shown</span>
            </header>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead><tr><th>Device</th><th>Event</th><th>Key data</th><th class="ops-table__right">Recorded</th></tr></thead>
                    <tbody>
                        @forelse ($lockEvents as $event)
                            @php
                                $payload = json_encode($event->payload ?? [], JSON_UNESCAPED_SLASHES);
                            @endphp
                            <tr>
                                <td>
                                    @if ($event->imei)
                                        <a href="{{ route('admin.locks.devices.show', $event->imei) }}" class="ops-primary ops-mono">{{ $event->imei }}</a>
                                        @if (isset($bikeNames[$event->imei]))<span class="ops-secondary">{{ $bikeNames[$event->imei] }}</span>@endif
                                    @else
                                        <span class="ops-secondary">No device</span>
                                    @endif
                                </td>
                                <td><span class="ops-badge {{ str_contains($event->type, 'alarm') ? 'ops-badge--danger' : '' }}">{{ str_replace(['.', '_'], ' ', $event->type) }}</span></td>
                                <td><span class="ops-secondary ops-mono" title="{{ $payload }}">{{ \Illuminate\Support\Str::limit($payload, 90) }}</span></td>
                                <td class="ops-table__right">{{ $event->created_at?->format('M d, H:i:s') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ops-empty"><strong>No events found</strong><span>Try clearing the filters.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
