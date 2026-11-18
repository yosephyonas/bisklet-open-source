<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // Example: $this->reportable(function (Throwable $e) {});
        $this->renderable(function (Throwable $e, $request) {
            // You can add custom render behavior per-exception here if needed.
        });
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * This inspects the request path and tries to redirect admin-area unauthenticated
     * requests to the admin login route name `admin.login` (if defined).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        // JSON / API callers
        if ($request->expectsJson()) {
            return response()->json(['message' => $exception->getMessage() ?: 'Unauthenticated.'], 401);
        }

        // If the request is under /admin, prefer named route 'admin.login'
        if ($request->is('admin') || $request->is('admin/*')) {
            if (Route::has('admin.login')) {
                return redirect()->guest(route('admin.login'));
            }
            return redirect()->guest(url('/admin/login'));
        }

        // Otherwise normal web fallback
        if (Route::has('login')) {
            return redirect()->guest(route('login'));
        }

        return redirect()->guest(url('/login'));
    }
}
