@php
    $items = [
        ['label' => 'Overview', 'route' => 'admin.locks.dashboard', 'match' => 'admin.locks.dashboard', 'icon' => 'M3 10.5 12 3l9 7.5M5.25 9.5V21h13.5V9.5M9 21v-6h6v6'],
        ['label' => 'Alerts', 'route' => 'admin.locks.alerts', 'match' => 'admin.locks.alerts', 'icon' => 'M12 9v4m0 4h.01M10.3 4.3 2.8 17.2A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.8L13.7 4.3a2 2 0 0 0-3.4 0Z'],
        ['label' => 'Telemetry', 'route' => 'admin.locks.telemetry', 'match' => 'admin.locks.telemetry', 'icon' => 'M4 18v-5m5 5V7m5 11v-8m5 8V4'],
        ['label' => 'Terminal', 'route' => 'admin.locks.terminal', 'match' => 'admin.locks.terminal', 'icon' => 'm7 8 4 4-4 4m6 0h4M4 4h16v16H4z'],
        ['label' => 'Devices', 'route' => 'admin.locks.devices.index', 'match' => 'admin.locks.devices.*', 'icon' => 'M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 15h4'],
    ];
@endphp

<nav class="ops-nav" aria-label="Lock operations sections">
    @foreach ($items as $item)
        @php
            $active = request()->routeIs($item['match']);
        @endphp
        <a href="{{ route($item['route']) }}"
           class="ops-nav__item {{ $active ? 'is-active' : '' }}"
           @if ($active) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
            </svg>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
