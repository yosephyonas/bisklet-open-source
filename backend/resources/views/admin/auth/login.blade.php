<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login - BISKLET</title>

    <link rel="stylesheet" href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>

<body class="login-wrapper">
    <div class="login-card">
        <span class="logo-mark">B</span>

        <h1>Welcome back, operator</h1>
        <p>Use your administrator credentials to access the BISKLET control center.</p>

        @if (session('error'))
            <div class="flash-message error">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="flash-message info">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="identifier" class="text-sm font-semibold text-slate-700">Email, phone, or national ID</label>
                <input id="identifier" name="identifier" type="text" value="{{ old('identifier') }}" required autofocus
                    placeholder="admin@bisklet.et">
            </div>

            <div>
                <label for="password" class="text-sm font-semibold text-slate-700">Password</label>
                <input id="password" name="password" type="password" required placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between text-sm text-slate-600">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="remember">
                    <span>Keep me signed in</span>
                </label>
                <span class="badge-dot neutral">FYDA verified</span>
            </div>

            <button type="submit" class="button button-primary w-full">Sign in</button>
        </form>

        <footer>© {{ now()->year }} BISKLET Mobility. Secure access enforced.</footer>
    </div>
</body>

</html>
