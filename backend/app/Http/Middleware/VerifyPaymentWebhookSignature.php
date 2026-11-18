<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $provider = Str::lower((string) $request->route('provider'));
        $secret = $this->secretForProvider($provider);

        if ($secret === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Payment webhook secret is not configured.');
        }

        $provided = (string) (
            $request->header('X-Bisklet-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->header('X-Chapa-Signature')
            ?? ''
        );

        if ($provided === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing payment webhook signature.');
        }

        $provided = Str::startsWith($provided, 'sha256=')
            ? Str::after($provided, 'sha256=')
            : $provided;

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid payment webhook signature.');
        }

        return $next($request);
    }

    private function secretForProvider(string $provider): string
    {
        $providerSecret = (string) config("services.payments.providers.{$provider}.webhook_secret", '');

        return $providerSecret !== ''
            ? $providerSecret
            : (string) config('services.payments.webhook_secret', '');
    }
}
