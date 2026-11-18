<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Message\RequestInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('services.rate_limits.api_per_minute', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute((int) config('services.rate_limits.auth_per_minute', 10))
                ->by($request->ip());
        });

        RateLimiter::for('lock-commands', function (Request $request) {
            return Limit::perMinute((int) config('services.rate_limits.lock_commands_per_minute', 20))
                ->by(($request->user()?->id ?: $request->ip()).'|'.$request->route('imei', 'scan'));
        });

        RateLimiter::for('gateway-webhooks', function (Request $request) {
            return Limit::perMinute((int) config('services.rate_limits.gateway_webhooks_per_minute', 600))
                ->by($request->ip());
        });

        RateLimiter::for('payment-webhooks', function (Request $request) {
            return Limit::perMinute((int) config('services.rate_limits.payment_webhooks_per_minute', 120))
                ->by($request->route('provider').'|'.$request->ip());
        });

        Http::globalRequestMiddleware(function (RequestInterface $request) {
            $token = (string) config('services.go_gateway.command_token', '');
            $gatewayUrl = rtrim((string) config('services.go_gateway.base_url', ''), '/');

            if ($token === '' || $gatewayUrl === '') {
                return $request;
            }

            $uri = (string) $request->getUri();

            if (! str_starts_with($uri, $gatewayUrl)) {
                return $request;
            }

            $timestamp = (string) now()->utc()->timestamp;
            $nonce = bin2hex(random_bytes(16));
            $body = (string) $request->getBody();
            $target = $request->getUri()->getPath();
            if ($request->getUri()->getQuery() !== '') {
                $target .= '?'.$request->getUri()->getQuery();
            }
            $canonical = implode("\n", [
                $timestamp,
                $nonce,
                strtoupper($request->getMethod()),
                $target ?: '/',
                hash('sha256', $body),
            ]);

            return $request
                ->withHeader('X-Gateway-Timestamp', $timestamp)
                ->withHeader('X-Gateway-Nonce', $nonce)
                ->withHeader('X-Gateway-Signature', hash_hmac('sha256', $canonical, $token));
        });
    }
}
