<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGatewayRequest
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.go_gateway.webhook_token', '');

        if ($expected === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Gateway webhook token is not configured.');
        }

        $timestamp = (string) $request->header('X-Gateway-Timestamp', '');
        $nonce = (string) $request->header('X-Gateway-Nonce', '');
        $provided = (string) $request->header('X-Gateway-Signature', '');
        if (! ctype_digit($timestamp)
            || abs(now()->utc()->timestamp - (int) $timestamp) > 300
            || preg_match('/^[A-Za-z0-9._:+\/@=-]{1,128}$/', $nonce) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $provided) !== 1) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid gateway credentials.');
        }

        $target = '/'.ltrim($request->getRequestUri(), '/');
        $canonical = implode("\n", [
            $timestamp,
            $nonce,
            strtoupper($request->method()),
            $target,
            hash('sha256', $request->getContent()),
        ]);
        $signature = hash_hmac('sha256', $canonical, $expected);

        if (! hash_equals($signature, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid gateway credentials.');
        }

        return $next($request);
    }
}
