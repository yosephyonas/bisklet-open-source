@extends('layouts.admin')

@section('title', 'Register Lock Device')
@section('subtitle', 'Create the device record required to assign and operate a connected lock.')

@section('actions')
    <a href="{{ route('admin.locks.devices.index') }}" class="ops-button">Back to devices</a>
@endsection

@section('content')
    @include('admin.locks.partials.nav')

    <div class="ops-grid ops-grid--main">
        <form method="POST" action="{{ route('admin.locks.devices.store') }}" class="ops-panel">
            @csrf
            <header class="ops-panel__header">
                <div><h2>Device identity</h2><p>IMEI and lock code are the only required fields.</p></div>
            </header>
            <div class="ops-panel__body ops-stack">
                <div class="ops-form-grid">
                    <div class="ops-field">
                        <label for="imei">IMEI</label>
                        <input type="text" id="imei" name="imei" value="{{ old('imei') }}" required autocomplete="off">
                        @error('imei')<p class="ops-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="ops-field">
                        <label for="lock_code">Lock code</label>
                        <input type="text" id="lock_code" name="lock_code" value="{{ old('lock_code') }}" required autocomplete="off">
                        @error('lock_code')<p class="ops-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <details id="optional-metadata" class="ops-disclosure">
                    <summary>Optional hardware and provisioning details</summary>
                    <div class="ops-disclosure__body ops-form-grid">
                        @foreach ([
                            ['device_code', 'Device code', 'text'],
                            ['model', 'Model', 'text'],
                            ['hardware_version', 'Hardware version', 'text'],
                            ['firmware_version', 'Firmware version', 'text'],
                            ['firmware_build_date', 'Firmware build date', 'text'],
                            ['sim_iccid', 'SIM ICCID', 'text'],
                            ['ble_mac', 'BLE MAC', 'text'],
                            ['status', 'Inventory status', 'text'],
                            ['target_host', 'Target host', 'text'],
                            ['target_port', 'Target port', 'number'],
                        ] as [$field, $label, $type])
                            <div class="ops-field">
                                <label for="{{ $field }}">{{ $label }}</label>
                                <input type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $field === 'status' ? 'available' : '') }}"
                                       @if ($field === 'target_port') min="1" max="65535" @endif>
                                @error($field)<p class="ops-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </details>

                <div class="ops-actions ops-actions--end">
                    <a href="{{ route('admin.locks.devices.index') }}" class="ops-button">Cancel</a>
                    <button type="submit" class="ops-button ops-button--primary">Register device</button>
                </div>
            </div>
        </form>

        <aside class="ops-stack">
            <section class="ops-panel {{ count($connectedUnregistered) ? 'ops-panel--warning' : '' }}">
                <header class="ops-panel__header">
                    <div><h2>Ready to register</h2><p>Unregistered IMEIs seen in the last 24 hours.</p></div>
                    <span class="ops-badge {{ count($connectedUnregistered) ? 'ops-badge--warning' : '' }}">{{ count($connectedUnregistered) }}</span>
                </header>
                <div class="ops-panel__body ops-list">
                    @forelse ($connectedUnregistered as $entry)
                        <div class="ops-list__item">
                            <div>
                                <span class="ops-primary ops-mono">{{ $entry['imei'] }}</span>
                                <span class="ops-secondary">Seen {{ optional($entry['last_seen'])->diffForHumans() ?? 'recently' }}</span>
                            </div>
                            <button type="button" class="ops-button ops-button--small"
                                    data-connected-imei="{{ $entry['imei'] }}"
                                    data-lock-code="{{ $entry['suggested_lock_code'] ?? '' }}"
                                    data-autofill='@json($entry["auto_fill"] ?? [])'>Use</button>
                        </div>
                    @empty
                        <div class="ops-empty"><strong>No unregistered devices found</strong><span>New listener traffic will appear here.</span></div>
                    @endforelse
                </div>
            </section>

            <details class="ops-disclosure">
                <summary>{{ count($connectedRegistered) }} connected devices already registered</summary>
                <div class="ops-disclosure__body ops-list">
                    @forelse ($connectedRegistered as $entry)
                        @php
                            $registeredDevice = $entry['device'];
                        @endphp
                        <div class="ops-list__item">
                            <div>
                                <span class="ops-primary ops-mono">{{ $entry['imei'] }}</span>
                                <span class="ops-secondary">{{ $registeredDevice?->bike?->code ?? 'Unassigned' }} · {{ optional($entry['last_seen'])->diffForHumans() ?? 'recently' }}</span>
                            </div>
                            <a href="{{ route('admin.locks.devices.show', $entry['imei']) }}" class="ops-button ops-button--small">Open</a>
                        </div>
                    @empty
                        <div class="ops-empty"><span>None connected.</span></div>
                    @endforelse
                </div>
            </details>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-connected-imei]').forEach(function (button) {
        button.addEventListener('click', function () {
            const imeiInput = document.getElementById('imei');
            const lockCodeInput = document.getElementById('lock_code');
            const metadataPanel = document.getElementById('optional-metadata');
            let autofill = {};

            try { autofill = JSON.parse(button.dataset.autofill || '{}'); } catch (error) { autofill = {}; }

            imeiInput.value = button.dataset.connectedImei || '';
            if (!lockCodeInput.value) lockCodeInput.value = button.dataset.lockCode || '';

            ['device_code', 'model', 'hardware_version', 'firmware_version', 'firmware_build_date', 'sim_iccid', 'ble_mac', 'status', 'target_host', 'target_port'].forEach(function (field) {
                const input = document.getElementById(field);
                if (input && autofill[field] != null && !input.value) input.value = String(autofill[field]);
            });

            if (Object.keys(autofill).length) metadataPanel.open = true;
            lockCodeInput.focus();
        });
    });
});
</script>
@endpush
