@extends('layouts.admin')

@section('title', 'Lock Operations')
@section('subtitle', 'Live readiness, active issues, and the devices that need attention.')

@section('actions')
    <a href="{{ route('admin.locks.devices.create') }}" class="ops-button ops-button--primary">Register device</a>
@endsection

@section('content')
    @include('admin.locks.partials.nav')

    @php
        $deviceCount = (int) $totals['devices'];
        $onlineCount = (int) $totals['online'];
        $offlineCount = (int) $totals['offline'];
        $onlinePercent = $deviceCount > 0 ? round(($onlineCount / $deviceCount) * 100) : 0;
        $latestTelemetry = $recentTelemetry->first();
        $latestAlarm = $recentAlarms->first();
        $alarmIsFresh = $latestAlarm?->created_at?->greaterThanOrEqualTo(now()->subMinutes(10)) ?? false;
    @endphp

    <div class="ops-stack">
        <section class="ops-summary" aria-label="Lock fleet summary">
            <a href="{{ route('admin.locks.devices.index', ['online' => 'online']) }}" class="ops-stat ops-stat--success">
                <span class="ops-stat__label">Online now</span>
                <strong class="ops-stat__value">{{ number_format($onlineCount) }}</strong>
                <span class="ops-stat__hint">{{ $onlinePercent }}% ready for commands</span>
            </a>
            <a href="{{ route('admin.locks.devices.index', ['online' => 'offline']) }}" class="ops-stat {{ $offlineCount ? 'ops-stat--danger' : '' }}">
                <span class="ops-stat__label">Offline</span>
                <strong class="ops-stat__value">{{ number_format($offlineCount) }}</strong>
                <span class="ops-stat__hint">{{ $offlineCount ? 'Cannot receive commands' : 'No action needed' }}</span>
            </a>
            <a href="{{ route('admin.locks.devices.index', ['battery' => 'low']) }}" class="ops-stat {{ $totals['lowBattery'] ? 'ops-stat--warning' : '' }}">
                <span class="ops-stat__label">Low battery</span>
                <strong class="ops-stat__value">{{ number_format($totals['lowBattery']) }}</strong>
                <span class="ops-stat__hint">At or below {{ $batteryThreshold }}%</span>
            </a>
            <a href="{{ route('admin.locks.alerts') }}" class="ops-stat {{ $alarmIsFresh ? 'ops-stat--danger' : '' }}">
                <span class="ops-stat__label">Recent alarm</span>
                <strong class="ops-stat__value">{{ $alarmIsFresh ? 'Active' : 'Clear' }}</strong>
                <span class="ops-stat__hint">{{ $latestAlarm?->created_at?->diffForHumans() ?? 'No alarm received' }}</span>
            </a>
        </section>

        <div class="ops-grid ops-grid--main">
            <section class="ops-panel">
                <header class="ops-panel__header">
                    <div>
                        <h2>Connected devices</h2>
                        <p>Most recently seen locks that can receive commands.</p>
                    </div>
                    <a href="{{ route('admin.locks.devices.index', ['online' => 'online']) }}" class="ops-link">View all</a>
                </header>
                <div class="ops-table-wrap">
                    <table class="ops-table">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th>Bike</th>
                                <th>Last signal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentDevices as $device)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.locks.devices.show', $device->imei) }}" class="ops-primary ops-mono">{{ $device->imei }}</a>
                                        <span class="ops-badge ops-badge--success">Online</span>
                                    </td>
                                    <td>
                                        <span class="ops-primary">{{ $device->bike?->code ?? 'Unassigned' }}</span>
                                        @if ($device->bike?->model)<span class="ops-secondary">{{ $device->bike->model }}</span>@endif
                                    </td>
                                    <td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="ops-table__right"><a href="{{ route('admin.locks.devices.show', $device->imei) }}" class="ops-button ops-button--small">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ops-empty"><strong>No devices connected</strong><span>Check the listener or wait for the next heartbeat.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="ops-stack">
                <section class="ops-panel {{ $alarmIsFresh ? 'ops-panel--danger' : '' }}">
                    <header class="ops-panel__header">
                        <div><h3>Attention</h3><p>What an operator should check next.</p></div>
                    </header>
                    <div class="ops-panel__body ops-list">
                        <div class="ops-list__item">
                            <div><span class="ops-primary">Alarm status</span><span class="ops-secondary">{{ $latestAlarm?->imei ?? 'No recent device alarm' }}</span></div>
                            <span class="ops-badge {{ $alarmIsFresh ? 'ops-badge--danger' : 'ops-badge--success' }}">{{ $alarmIsFresh ? 'Check now' : 'Clear' }}</span>
                        </div>
                        <div class="ops-list__item">
                            <div><span class="ops-primary">Offline devices</span><span class="ops-secondary">Unable to receive commands</span></div>
                            <strong>{{ number_format($offlineCount) }}</strong>
                        </div>
                        <div class="ops-list__item">
                            <div><span class="ops-primary">Battery service</span><span class="ops-secondary">Plan charging or replacement</span></div>
                            <strong>{{ number_format($totals['lowBattery']) }}</strong>
                        </div>
                    </div>
                </section>

                <section class="ops-panel">
                    <header class="ops-panel__header"><div><h3>Listener</h3><p>Command connection endpoint.</p></div></header>
                    <div class="ops-panel__body">
                        <span class="ops-primary ops-mono">{{ $listenerLabel }}</span>
                        <span class="ops-secondary">Latest signal: {{ $latestTelemetry?->received_at?->diffForHumans() ?? $latestTelemetry?->created_at?->diffForHumans() ?? 'none' }}</span>
                        <div class="ops-actions mt-3">
                            <a href="{{ route('admin.locks.terminal') }}" class="ops-button ops-button--small">Open logs</a>
                            <form method="POST" action="{{ route('admin.locks.listener.restart') }}" onsubmit="return confirm('Restart all active lock connections?');">
                                @csrf
                                <button type="submit" class="ops-button ops-button--small ops-button--warning">Restart</button>
                            </form>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
