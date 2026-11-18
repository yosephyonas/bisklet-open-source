@php
    $user = Auth::user();
    $mapboxConfigured = filled(config('services.mapbox.access_token'));
    $searchHint = $mapboxConfigured ? null : 'System config incomplete';
@endphp

<header class="topbar topbar-command">
    <div class="topbar-left">
        <button type="button" class="button button-outline topbar-menu lg:hidden" data-sidebar-toggle aria-label="Toggle menu">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18" />
            </svg>
        </button>
        <div class="topbar-title-block">
            <span class="topbar-kicker">BISKLET Ops</span>
            <div class="topbar-title-row">
                <p class="topbar-title">@yield('title', 'Dashboard')</p>
                @if ($searchHint)
                    <span class="topbar-status">{{ $searchHint }}</span>
                @endif
            </div>
            @if ($searchHint)
                <p class="topbar-status-note">Map services need configuration.</p>
            @endif
        </div>
    </div>

    <div class="topbar-actions">
        <form action="{{ route('admin.search') }}" method="GET" class="topbar-search">
            <svg viewBox="0 0 24 24" class="topbar-search-icon h-4 w-4" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5a6 6 0 104.24 10.24l3.26 3.26" />
            </svg>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search admin data"
                class="h-10 w-64 rounded-xl border border-slate-200 px-4 text-sm focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/20">
            <button type="submit" class="button button-outline topbar-search-submit">Search</button>
        </form>
        <a href="{{ route('admin.search') }}" class="button button-outline topbar-search-button lg:hidden">Search</a>
        <div class="topbar-account">
            <span class="avatar text-sm">
                {{ strtoupper(mb_substr($user?->first_name ?? 'A', 0, 1)) }}
            </span>
            <div class="topbar-user">
                <p>{{ $user?->first_name }} {{ $user?->last_name }}</p>
                <span>Administrator</span>
            </div>
        </div>
        <form action="{{ route('admin.logout') }}" method="POST">
            @csrf
            <button type="submit" class="button button-outline topbar-logout">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H3" />
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</header>
