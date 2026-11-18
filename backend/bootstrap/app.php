<?php

use App\Http\Middleware\EnsureFydaVerified;
use App\Http\Middleware\EnsureGatewayRequest;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\LogAdminActivity;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyPaymentWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            AssignRequestId::class,
            SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'gateway' => EnsureGatewayRequest::class,
            'role' => EnsureUserRole::class,
            'fyda.verified' => EnsureFydaVerified::class,
            'admin.audit' => LogAdminActivity::class,
            'payment.webhook' => VerifyPaymentWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
