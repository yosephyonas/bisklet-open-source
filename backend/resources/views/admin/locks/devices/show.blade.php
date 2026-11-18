@extends('layouts.admin')

@section('title', 'Lock Device')
@section('subtitle', 'Operational health and diagnostics for '.$device->imei.'.')

@section('actions')
    <a href="{{ route('admin.locks.devices.index') }}" class="ops-button">Back to devices</a>
@endsection

@section('content')
    @include('admin.locks.partials.nav')

    @php
        $isOnline = $isLiveConnected ?? false;
        $battery = $device->battery_percent;
        $batteryTone = $battery === null ? '' : ($battery <= 20 ? 'ops-badge--danger' : ($battery <= 40 ? 'ops-badge--warning' : 'ops-badge--success'));
        $hasCoordinates = is_numeric($device->last_lat) && is_numeric($device->last_lng);
        $lockState = $device->lock_status === null ? 'Unknown' : ($device->lock_status === 1 ? 'Locked' : 'Unlocked');
    @endphp

    <div class="ops-stack">
        <section class="ops-panel ops-panel--padded">
            <div class="ops-detail-hero">
                <div>
                    <h2>{{ $device->bike?->code ?? 'Unassigned lock' }}</h2>
                    <p class="ops-mono">{{ $device->imei }}{{ $device->lock_code ? ' · Lock '.$device->lock_code : '' }}</p>
                </div>
                <div class="ops-actions">
                    <span class="ops-badge {{ $isOnline ? 'ops-badge--success' : '' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span>
                    <a href="#lock-terminal-wrap" class="ops-button ops-button--small">Open logs</a>
                    @if ($hasCoordinates)
                        <a href="https://maps.google.com/?q={{ $device->last_lat }},{{ $device->last_lng }}" target="_blank" rel="noopener" class="ops-button ops-button--small">Map</a>
                    @endif
                    <form action="{{ route('admin.locks.devices.restart-connection', $device->imei) }}" method="POST" onsubmit="return confirm('Restart this device connection?');">
                        @csrf
                        <button type="submit" class="ops-button ops-button--small ops-button--warning">Restart connection</button>
                    </form>
                </div>
            </div>

            <div class="ops-detail-metrics">
                <div class="ops-detail-metric">
                    <span>Battery</span><strong>{{ $battery !== null ? $battery.'%' : 'Unknown' }}</strong>
                    <small>{{ $device->battery_voltage !== null ? number_format($device->battery_voltage / 1000, 2).'V' : 'Voltage unavailable' }}</small>
                </div>
                <div class="ops-detail-metric">
                    <span>Last seen</span><strong>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</strong>
                    <small>{{ $device->last_seen_at?->format('M d, H:i:s') ?? 'No heartbeat' }}</small>
                </div>
                <div class="ops-detail-metric">
                    <span>Lock state</span><strong>{{ $lockState }}</strong>
                    <small>{{ $device->signal_strength !== null ? $device->signal_strength.' CSQ' : 'Signal unavailable' }}</small>
                </div>
                <div class="ops-detail-metric">
                    <span>Last location</span><strong>{{ $hasCoordinates ? number_format((float) $device->last_lat, 5).', '.number_format((float) $device->last_lng, 5) : 'Unavailable' }}</strong>
                    <small>{{ $device->last_gps_at?->diffForHumans() ?? 'No GPS timestamp' }}</small>
                </div>
            </div>
        </section>

        <div class="ops-grid">
            <section class="ops-panel">
                <header class="ops-panel__header"><div><h2>Hardware</h2><p>Identifiers used during field support.</p></div></header>
                <div class="ops-panel__body">
                    <dl class="ops-kv">
                        <div><dt>Model</dt><dd>{{ $device->model ?? '—' }}</dd></div>
                        <div><dt>Hardware</dt><dd>{{ $device->hardware_version ?? '—' }}</dd></div>
                        <div><dt>Firmware</dt><dd>{{ $device->firmware_version ?? '—' }}</dd></div>
                        <div><dt>Device code</dt><dd>{{ $device->device_code ?? '—' }}</dd></div>
                        <div><dt>BLE MAC</dt><dd class="ops-mono">{{ $device->ble_mac ?? '—' }}</dd></div>
                        <div><dt>SIM ICCID</dt><dd class="ops-mono">{{ $device->sim_iccid ?? '—' }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="ops-panel">
                <header class="ops-panel__header"><div><h2>Connection and provisioning</h2><p>Current network and configuration state.</p></div></header>
                <div class="ops-panel__body">
                    <dl class="ops-kv">
                        <div><dt>Connection</dt><dd><span class="ops-badge {{ $isOnline ? 'ops-badge--success' : '' }}">{{ $isOnline ? 'Online' : 'Offline' }}</span></dd></div>
                        <div><dt>IP address</dt><dd class="ops-mono">{{ $device->last_ip ?? '—' }}</dd></div>
                        <div><dt>GPS satellites</dt><dd>{{ $device->gps_sat ?? '—' }}</dd></div>
                        <div><dt>Provisioning</dt><dd><span class="ops-badge {{ $device->provision_status === 'applied' ? 'ops-badge--success' : ($device->provision_status === 'failed' ? 'ops-badge--danger' : '') }}">{{ ucfirst($device->provision_status ?? 'pending') }}</span></dd></div>
                        <div><dt>Listener target</dt><dd class="ops-mono">{{ $device->target_host ?: '0.0.0.0' }}:{{ $device->target_port ?: 9000 }}</dd></div>
                        <div><dt>Last provisioned</dt><dd>{{ $device->last_provisioned_at?->diffForHumans() ?? 'Never' }}</dd></div>
                    </dl>
                </div>
            </section>
        </div>

        <div class="ops-grid">
            <section class="ops-panel">
                <header class="ops-panel__header"><div><h2>Recent telemetry</h2><p>The latest parsed packets from this device.</p></div><a href="{{ route('admin.locks.telemetry', ['imei' => $device->imei]) }}" class="ops-link">View all</a></header>
                <div class="ops-table-wrap">
                    <table class="ops-table">
                        <thead><tr><th>Type</th><th class="ops-table__right">Received</th></tr></thead>
                        <tbody>
                            @forelse ($telemetry->take(8) as $packet)
                                <tr><td><span class="ops-primary">{{ $packet->type ?? 'Unknown packet' }}</span><span class="ops-secondary ops-mono">{{ \Illuminate\Support\Str::limit(json_encode($packet->parsed_json ?? [], JSON_UNESCAPED_SLASHES), 70) }}</span></td><td class="ops-table__right">{{ optional($packet->received_at ?? $packet->created_at)->diffForHumans() ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="ops-empty"><strong>No telemetry yet</strong><span>Waiting for the device to report.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ops-panel">
                <header class="ops-panel__header"><div><h2>Recent events</h2><p>Commands, alarms, and device lifecycle activity.</p></div><a href="{{ route('admin.locks.telemetry', ['imei' => $device->imei]) }}" class="ops-link">View all</a></header>
                <div class="ops-table-wrap">
                    <table class="ops-table">
                        <thead><tr><th>Event</th><th class="ops-table__right">Recorded</th></tr></thead>
                        <tbody>
                            @forelse ($lockEvents->take(8) as $event)
                                <tr><td><span class="ops-badge {{ str_contains($event->type, 'alarm') ? 'ops-badge--danger' : '' }}">{{ str_replace(['.', '_'], ' ', $event->type) }}</span><span class="ops-secondary ops-mono">{{ \Illuminate\Support\Str::limit(json_encode($event->payload ?? [], JSON_UNESCAPED_SLASHES), 70) }}</span></td><td class="ops-table__right">{{ $event->created_at?->diffForHumans() ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="ops-empty"><strong>No events yet</strong><span>Device activity will appear here.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="ops-terminal ops-terminal--compact" id="lock-terminal-wrap">
            <header class="ops-terminal__header">
                <div>
                    <p class="ops-terminal__title">Device logs</p>
                    <span class="ops-terminal__sub"><span id="lock-listener-label">{{ $device->target_host ?: '0.0.0.0' }}:{{ $device->target_port ?: 9000 }}</span> · <span id="lock-log-source">filtered by {{ $device->imei }}</span></span>
                </div>
                <button type="button" id="load-lock-logs" data-url="{{ route('admin.locks.devices.logs', $device->imei) }}" class="ops-button ops-button--small">Load logs</button>
            </header>
            <div class="ops-terminal__meta"><span>imei/{{ $device->imei }}</span><span id="lock-terminal-meta">Logs not loaded</span></div>
            <pre id="lock-terminal-output" class="ops-terminal__output">Load logs only when you need raw diagnostic output.</pre>
        </section>

        <details class="ops-disclosure">
            <summary>Advanced device commands</summary>
            <div class="ops-disclosure__body ops-stack">
                <p class="ops-help">Protocol-level commands are intended for diagnostics and field support. Confirm the device and operation before sending.</p>
                <div class="ops-form-grid">
                    <form action="{{ route('admin.locks.devices.wifi-positioning', $device->imei) }}" method="POST" class="ops-panel ops-panel--padded ops-stack">
                        @csrf
                        <div><span class="ops-primary">D2 · Wi-Fi positioning</span><span class="ops-secondary">Query or configure access-point uploads.</span></div>
                        <div class="ops-field"><label>Mode</label><select name="mode"><option value="0">Query current</option><option value="1">Disable automatic</option><option value="2">Upload when GPS is invalid</option><option value="3">Upload with D0</option></select></div>
                        <div class="ops-field"><label>Scan seconds</label><input type="number" name="scan_seconds" min="2" max="15" value="3"></div>
                        <button type="submit" class="ops-button">Send D2</button>
                    </form>

                    <form action="{{ route('admin.locks.devices.external-device', $device->imei) }}" method="POST" class="ops-panel ops-panel--padded ops-stack">
                        @csrf
                        <div><span class="ops-primary">L5 · External accessory</span><span class="ops-secondary">Operate or query an attached lock.</span></div>
                        <div class="ops-field"><label>Operation</label><select name="operation"><option value="1">Battery unlock</option><option value="2">Helmet unlock</option><option value="3">Cable unlock</option><option value="4">Hub unlock</option><option value="17">Battery lock</option><option value="18">Helmet lock</option><option value="19">Cable lock</option><option value="20">Hub lock</option><option value="33">Battery status</option><option value="34">Helmet status</option><option value="36">Cable status</option><option value="37">OC33 cable status</option></select></div>
                        <button type="submit" class="ops-button">Send L5</button>
                    </form>

                    <form action="{{ route('admin.locks.devices.beacon-validation', $device->imei) }}" method="POST" class="ops-panel ops-panel--padded ops-stack">
                        @csrf
                        <div><span class="ops-primary">B0 · Beacon validation</span><span class="ops-secondary">Reply to a return-point scan.</span></div>
                        <div class="ops-field"><label>Result</label><select name="result"><option value="0">Validated</option><option value="1">MAC invalid</option><option value="2">Time error</option><option value="3">Search again</option><option value="4">Stop search</option></select></div>
                        <button type="submit" class="ops-button">Send B0</button>
                    </form>

                    <form action="{{ route('admin.locks.devices.rfid-admin-card', $device->imei) }}" method="POST" class="ops-panel ops-panel--padded ops-stack">
                        @csrf
                        <div><span class="ops-primary">C1 · RFID admin card</span><span class="ops-secondary">Set, query, or remove an admin card.</span></div>
                        <div class="ops-field"><label>Operation</label><select name="operation"><option value="0">Set admin card</option><option value="1">Query admin card</option><option value="2">Remove admin card</option></select></div>
                        <div class="ops-field"><label>Card number</label><input type="text" name="card_number" placeholder="Hex card number or 0"></div>
                        <button type="submit" class="ops-button">Send C1</button>
                    </form>

                    <form action="{{ route('admin.locks.devices.ota-detect', $device->imei) }}" method="POST" class="ops-panel ops-panel--padded ops-stack">
                        @csrf
                        <div><span class="ops-primary">U0 · OTA detection</span><span class="ops-secondary">Send upgrade detection metadata.</span></div>
                        <div class="ops-field"><label>Payload</label><input type="text" name="payload" placeholder="220,128,32434,A1,C7qn"></div>
                        <button type="submit" class="ops-button">Send U0</button>
                    </form>

                    <div class="ops-panel ops-panel--padded ops-stack">
                        <div><span class="ops-primary">Maintenance</span><span class="ops-secondary">Connection and power recovery commands.</span></div>
                        <form action="{{ route('admin.locks.devices.cable-lock-info', $device->imei) }}" method="POST">@csrf<button type="submit" class="ops-button">Request cable lock info</button></form>
                        <form action="{{ route('admin.locks.devices.reboot', $device->imei) }}" method="POST" onsubmit="return confirm('Reboot this device?');">@csrf<button type="submit" class="ops-button ops-button--warning">Reboot device</button></form>
                        <form action="{{ route('admin.locks.devices.power-off', $device->imei) }}" method="POST" onsubmit="return confirm('Power off this device? It may require physical access to recover.');">@csrf<button type="submit" class="ops-button ops-button--danger">Power off device</button></form>
                    </div>
                </div>
            </div>
        </details>

        <details class="ops-disclosure" @if ($errors->any()) open @endif>
            <summary>Edit device metadata</summary>
            <form action="{{ route('admin.locks.devices.update', $device->imei) }}" method="POST" class="ops-disclosure__body ops-stack">
                @csrf
                @method('PUT')
                <div class="ops-form-grid">
                    @foreach ([
                        ['model', 'Model', 'text'], ['hardware_version', 'Hardware version', 'text'], ['lock_code', 'Lock code', 'text'],
                        ['device_code', 'Device code', 'text'], ['status', 'Inventory status', 'text'], ['firmware_version', 'Firmware version', 'text'],
                        ['firmware_build_date', 'Firmware build date', 'text'], ['ble_mac', 'BLE MAC', 'text'], ['sim_iccid', 'SIM ICCID', 'text'],
                        ['target_host', 'Target host', 'text'], ['target_port', 'Target port', 'number'], ['provision_status', 'Provision status', 'text'],
                    ] as [$field, $label, $type])
                        <div class="ops-field">
                            <label for="{{ $field }}">{{ $label }}</label>
                            <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $device->$field) }}" @if ($field === 'target_port') min="1" max="65535" @endif>
                            @error($field)<p class="ops-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="ops-field">
                    <input type="hidden" name="is_active" value="0">
                    <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active)) class="ops-checkbox"> Device is active</label>
                </div>
                <div class="ops-actions ops-actions--end"><button type="submit" class="ops-button ops-button--primary">Save metadata</button></div>
            </form>
        </details>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('load-lock-logs');
    const output = document.getElementById('lock-terminal-output');
    const meta = document.getElementById('lock-terminal-meta');
    const listenerLabel = document.getElementById('lock-listener-label');
    const sourceLabel = document.getElementById('lock-log-source');
    if (!button) return;

    button.addEventListener('click', async function () {
        button.disabled = true;
        button.textContent = 'Loading…';
        output.textContent = 'Loading device logs…';

        try {
            const response = await fetch(button.dataset.url + '?limit=120', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const lines = Array.isArray(data.lines) ? data.lines : [];
            output.textContent = lines.length ? lines.join('\n') : 'No matching device logs found.';
            meta.textContent = 'Updated ' + (data.fetched_at || '—');
            if (data.listener?.label) listenerLabel.textContent = data.listener.label;
            if (data.source?.file) sourceLabel.textContent = data.source.file;
            output.scrollTop = output.scrollHeight;
        } catch (error) {
            output.textContent = 'Failed to load logs: ' + (error.message || 'Unknown error');
            meta.textContent = 'Load failed';
        } finally {
            button.disabled = false;
            button.textContent = 'Reload logs';
        }
    });
});
</script>
@endpush
