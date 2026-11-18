<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AdminAuditLog;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function create(Request $request)
    {
        if (Auth::guard('web')->check() && Auth::user()?->role === UserRole::Admin) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return view('admin.auth.login');
    }

    /**
     * Handle login attempt.
     */
    public function store(LoginRequest $request)
    {
        $identifier = trim($request->identifier());
        $email = $request->email();
        $password = $request->password();
        $remember = $request->remember();

        $throttleKey = $this->throttleKey(Str::lower($email ?? $identifier), $request->ip());
        $maxAttempts = 5;
        $decaySeconds = 60;

        // Lockout check
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::warning('Admin auth lockout', ['email' => $email, 'ip' => $request->ip(), 'wait_seconds' => $seconds]);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        // Attempt authentication
        $credentials = ['password' => $password];

        if ($email) {
            $credentials['email'] = Str::lower($email);
        } elseif (preg_match('/^\\+?[0-9]+$/', $identifier)) {
            $credentials['phone'] = $identifier;
        } else {
            $credentials['national_id'] = $identifier;
        }

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            $attemptsLeft = $maxAttempts - RateLimiter::attempts($throttleKey);

            Log::info('Admin auth failed', ['identifier' => $identifier, 'ip' => $request->ip(), 'attempts_left' => $attemptsLeft]);

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        // Successful login: clear throttle, regenerate session
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        // Ensure only admin users can access
        if ($user?->role !== UserRole::Admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::warning('Non-admin attempted admin login', ['identifier' => $identifier, 'id' => $user?->id ?? null]);

            throw ValidationException::withMessages([
                'identifier' => 'Only admin users can access the control panel.',
            ]);
        }

        // OPTIONAL: require FYDA verification to sign in (uncomment to enable)
        // if (is_null($user->fyda_verified_at)) {
        //     Auth::guard('web')->logout();
        //     $request->session()->invalidate();
        //     $request->session()->regenerateToken();
        //
        //     throw ValidationException::withMessages([
        //         'email' => 'Your account must be FYDA-verified before accessing the admin panel.',
        //     ]);
        // }

        Log::info('Admin login success', ['email' => $email, 'id' => $user->id]);
        AdminAuditLog::record([
            'user_id' => $user->id,
            'action' => 'admin.login',
            'description' => 'Admin Login',
            'route_name' => 'admin.login.store',
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => ['identifier' => $identifier],
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Logout the admin.
     */
    public function destroy(Request $request)
    {
        AdminAuditLog::record([
            'user_id' => $request->user()?->id,
            'action' => 'admin.logout',
            'description' => 'Admin Logout',
            'route_name' => 'admin.logout',
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'metadata' => [],
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Build a throttle key unique per email + IP.
     */
    protected function throttleKey(string $email, ?string $ip): string
    {
        return Str::lower($email) . '|' . ($ip ?? 'unknown');
    }
}
