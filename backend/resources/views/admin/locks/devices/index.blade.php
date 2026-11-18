@extends('layouts.admin')

@section('title', 'Lock Devices')
@section('subtitle', 'Find a lock, check readiness, and open its operational details.')

@section('actions')
    <a href="{{ route('admin.locks.devices.create') }}" class="ops-button ops-button--primary">Register device</a>
@endsection

@section('content')
    @include('admin.locks.partials.nav')

    <div class="ops-stack">
        <form method="GET" class="ops-filter">
            <div class="ops-filter__grid">
                <div class="ops-filter__field">
                    <label for="q">Search</label>
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="IMEI, lock code, or SIM">
                </div>
                <div class="ops-filter__field">
                    <label for="online">Connectivity</label>
                    <select id="online" name="online">
                        <option value="">All devices</option>
                        <option value="online" @selected(($filters['online'] ?? '') === 'online')>Online</option>
                        <option value="offline" @selected(($filters['online'] ?? '') === 'offline')>Offline</option>
                    </select>
                </div>
                <div class="ops-filter__field">
                    <label for="battery">Battery</label>
                    <select id="battery" name="battery">
                        <option value="">All levels</option>
                        <option value="low" @selected(($filters['battery'] ?? '') === 'low')>Low (20% or less)</option>
                        <option value="healthy" @selected(($filters['battery'] ?? '') === 'healthy')>Healthy</option>
                        <option value="unknown" @selected(($filters['battery'] ?? '') === 'unknown')>Unknown</option>
                    </select>
                </div>
                <div class="ops-filter__field">
                    <label for="provision_status">Provisioning</label>
                    <select id="provision_status" name="provision_status">
                        <option value="">All states</option>
                        @foreach (['pending', 'applied', 'failed'] as $state)
                            <option value="{{ $state }}" @selected(($filters['provision_status'] ?? '') === $state)>{{ ucfirst($state) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ops-filter__field">
                    <label for="status">Inventory status</label>
                    <input type="text" id="status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="For example: available">
                </div>
            </div>
            <div class="ops-filter__actions">
                <a href="{{ route('admin.locks.devices.index') }}" class="ops-button">Clear</a>
                <button type="submit" class="ops-button ops-button--primary">Apply filters</button>
            </div>
        </form>

        <section class="ops-panel">
            <header class="ops-panel__header">
                <div><h2>Devices</h2><p>{{ number_format($devices->total()) }} matching lock records.</p></div>
            </header>
            <div class="ops-table-wrap">
                <table class="ops-table">
                    <thead>
                        <tr><th>Device</th><th>Assigned bike</th><th>Connectivity</th><th>Battery</th><th>Provisioning</th><th class="ops-table__right">Last seen</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($devices as $device)
                            @php
                                $isOnline = in_array($device->imei, $liveImeis ?? [], true);
                                $battery = $device->battery_percent;
                                $batteryTone = $battery === null ? '' : ($battery <= 20 ? 'ops-badge--danger' : ($battery <= 40 ? 'ops-badge--warning' : 'ops-badge--success'));
                                $provisionTone = $device->provision_status === 'applied' ? 'ops-badge--success' : ($device->provision_status === 'failed' ? 'ops-badge--danger' : '');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.locks.devices.show', $device->imei) }}" class="ops-primary ops-mono">{{ $device->imei }}</a>
                                    <span class="ops-secondary">{{ $device->lock_code ? 'Lock '.$device->lock_code : 'No lock code' }}</span>
                                </td>
                                <td>
                                    <span class="ops-primary">{{ $device->bike?->code ?? 'Unassigned' }}</span>
                                    @if ($device->bike?->model)<span class="ops-secondary">{{ $device->bike->model }}</span>@endif
                                </td>
                                <td><span class="ops-badge {{ $isOnline ? 'ops-badge--success' : '' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span></td>
                                <td><span class="ops-badge {{ $batteryTone }}">{{ $battery !== null ? $battery.'%' : 'Unknown' }}</span></td>
                                <td>
                                    <span class="ops-badge {{ $provisionTone }}">{{ ucfirst($device->provision_status ?? 'pending') }}</span>
                                    <span class="ops-secondary">{{ ucfirst($device->status ?? 'unknown') }}</span>
                                </td>
                                <td class="ops-table__right">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="ops-table__right ops-table__actions"><a href="{{ route('admin.locks.devices.show', $device->imei) }}" class="ops-button ops-button--small">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="ops-empty"><strong>No devices found</strong><span>Clear the filters or register a new device.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{ $devices->withQueryString()->links() }}
    </div>
@endsection
