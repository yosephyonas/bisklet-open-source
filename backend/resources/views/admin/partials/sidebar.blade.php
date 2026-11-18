@php
    $navItems = [
        [
            'label' => 'Dashboard',
            'route' => 'admin.dashboard',
            'icon' => '<path d="M12 3l8 6v12a1 1 0 01-1 1h-5v-6H10v6H5a1 1 0 01-1-1V9l8-6z" />',
        ],
        [
            'label' => 'Search',
            'route' => 'admin.search',
            'icon' => '<path d="M11 4a7 7 0 105.292 12.292l4.208 4.208a1 1 0 001.414-1.414l-4.208-4.208A7 7 0 0011 4z" />',
        ],
        [
            'label' => 'Users',
            'route' => 'admin.users.index',
            'icon' => '<path d="M16 11c1.66 0 3-1.79 3-4s-1.34-4-3-4-3 1.79-3 4 1.34 4 3 4zm-8 0c1.66 0 3-1.79 3-4S9.66 3 8 3 5 4.79 5 7s1.34 4 3 4zm0 2c-2.67 0-8 1.34-8 4v2h8v-2c0-.68.26-1.33.74-1.82A4.8707 4.8707 0 008 13zm8 0c-.29 0-.62.02-.97.05C16.65 13.82 18 15.11 18 17v2h6v-2c0-2.66-5.33-4-8-4z" />',
        ],
        [
            'label' => 'Stations',
            'route' => 'admin.stations.index',
            'icon' => '<path d="M4 4h16l-1.5 13.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 4zm4 6h8l-.5 5.5h-7z" />',
        ],
        [
            'label' => 'Service Area',
            'route' => 'admin.service-area.edit',
            'icon' => '<path d="M12 2l8 4v7c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V6l8-4zm0 3.2L7 7.4v5.4c0 3.1 1.8 5.3 5 6 3.2-.7 5-2.9 5-6V7.4l-5-2.2z" />',
        ],
        [
            'label' => 'Bikes',
            'route' => 'admin.bikes.index',
            'icon' => '<path d="M5.5 16a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0zm13 0a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM12 5h4a1 1 0 110 2h-1.382l2.276 4H19a1 1 0 110 2h-2a1 1 0 01-.894-.553L13.618 7H12a1 1 0 010-2zm-2.553 6l1.106 2h-2.23L7.618 7H6a1 1 0 010-2h2a1 1 0 01.894.553L9.447 11z" />',
        ],
        [
            'label' => 'Rides',
            'route' => 'admin.rides.index',
            'icon' => '<path d="M3 13l6-9h6l6 9h-3l2 7h-4l-2-7h-4l-2 7H4l2-7H3z" />',
        ],
        [
            'label' => 'Payments',
            'route' => 'admin.payments.index',
            'icon' => '<path d="M4 6h16a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2zm0 2v2h16V8H4zm0 4v4h16v-4H4z" />',
        ],
        [
            'label' => 'Maintenance',
            'route' => 'admin.maintenance.index',
            'icon' => '<path d="M21 2l-9 9-3-3-8 8v3h3l8-8 3 3 9-9z" />',
        ],
        [
            'label' => 'Lock Ops',
            'route' => 'admin.locks.dashboard',
            'icon' => '<path d="M12 2a6 6 0 00-6 6v3H5a1 1 0 00-1 1v8a2 2 0 002 2h12a2 2 0 002-2v-8a1 1 0 00-1-1h-1V8a6 6 0 00-6-6zm-4 9V8a4 4 0 118 0v3H8zm4 3a2 2 0 012 2v2h-4v-2a2 2 0 012-2z" />',
        ],
        [
            'label' => 'FYDA',
            'route' => 'admin.fyda-verifications.index',
            'icon' => '<path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 5v4l3 3-1.5 1.5L11 12V7h2z" />',
        ],
        [
            'label' => 'Health',
            'route' => 'admin.system-health',
            'icon' => '<path d="M4 13h4l2-7 4 12 2-5h4v2h-2.6L14 22 10 10 8.8 15H4v-2z" />',
        ],
        [
            'label' => 'Audit Log',
            'route' => 'admin.audit-logs.index',
            'icon' => '<path d="M6 3h12a1 1 0 011 1v16a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1zm2 4h8v2H8V7zm0 4h8v2H8v-2zm0 4h5v2H8v-2z" />',
        ],
    ];
@endphp

<aside class="sidebar w-64 shrink-0 lg:flex lg:flex-col" data-admin-sidebar>
    <div class="sidebar-header">
        <span class="logo-mark">B</span>
        <div class="sidebar-brand">
            <p class="sidebar-title">BISKLET</p>
            <p class="sidebar-sub">Ops desk</p>
        </div>
    </div>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6">
        @foreach ($navItems as $item)
            @php
                $isActive = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*');
            @endphp
            <a href="{{ route($item['route']) }}"
               class="nav-link {{ $isActive ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 opacity-80">
                    {!! $item['icon'] !!}
                </svg>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
    <div class="sidebar-footer">
        &copy; {{ now()->year }} BISKLET
    </div>
</aside>
