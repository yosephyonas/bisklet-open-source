<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - BISKLET Admin</title>

    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">

    @stack('styles')
</head>

<body class="app-shell font-sans antialiased">
    @include('admin.partials.sidebar')
    <div class="sidebar-backdrop" data-sidebar-backdrop></div>

    <div class="content-shell">
        @include('admin.partials.topbar')

        <main class="content-area">
            <div class="inner">
                @include('admin.partials.flash')

                @if (View::hasSection('hero'))
                    @yield('hero')
                @endif

                <div class="inner-header">
                    <div>
                        <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                        @hasSection('subtitle')
                            <p class="page-subtitle">@yield('subtitle')</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">@yield('actions')</div>
                </div>

                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.querySelector('[data-sidebar-toggle]');
            const sidebar = document.querySelector('[data-admin-sidebar]');
            const backdrop = document.querySelector('[data-sidebar-backdrop]');

            if (!toggle || !sidebar || !backdrop) {
                return;
            }

            const closeSidebar = () => {
                sidebar.classList.remove('open');
                backdrop.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            };

            const openSidebar = () => {
                sidebar.classList.add('open');
                backdrop.classList.add('show');
                document.body.classList.add('sidebar-open');
            };

            toggle.addEventListener('click', function () {
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            backdrop.addEventListener('click', closeSidebar);

            sidebar.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    closeSidebar();
                }
            });

            window.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });

            document.querySelectorAll('[data-alert-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var alert = btn.closest('[data-dismissable]');
                    if (alert) {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-4px)';
                        setTimeout(function () {
                            alert.remove();
                        }, 200);
                    }
                });
            });
        });
    </script>
</body>

</html>
