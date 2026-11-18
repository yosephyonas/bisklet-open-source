<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'private_key',
        'private_key_base64',
        'client_secret',
        'secret',
        'authorization',
        'certificate',
        'assertion',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request)) {
            $route = $request->route();
            $routeName = (string) ($route?->getName() ?? 'admin.action');

            AdminAuditLog::record([
                'user_id' => $request->user()?->id,
                'action' => $routeName,
                'description' => $this->describe($routeName),
                'route_name' => $routeName,
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'metadata' => [
                    'status_code' => $response->getStatusCode(),
                    'route_parameters' => $this->stringifyRouteParameters($route?->parameters() ?? []),
                    'input' => $this->redact($request->except(['_token', '_method'])),
                ],
            ]);
        }

        return $response;
    }

    private function shouldRecord(Request $request): bool
    {
        if (! $request->user() || $request->isMethodSafe()) {
            return false;
        }

        return Str::startsWith((string) $request->route()?->getName(), 'admin.');
    }

    private function describe(string $routeName): string
    {
        return Str::headline(Str::after($routeName, 'admin.'));
    }

    private function stringifyRouteParameters(array $parameters): array
    {
        return collect($parameters)
            ->map(function ($value) {
                if (is_object($value) && method_exists($value, 'getKey')) {
                    return [
                        'type' => class_basename($value),
                        'id' => $value->getKey(),
                    ];
                }

                return is_scalar($value) || $value === null ? $value : (string) json_encode($value);
            })
            ->all();
    }

    private function redact(array $input): array
    {
        return collect($input)
            ->mapWithKeys(function ($value, string $key) {
                if ($this->isSensitive($key)) {
                    return [$key => '[redacted]'];
                }

                if (is_array($value)) {
                    return [$key => $this->redact($value)];
                }

                return [$key => is_string($value) ? Str::limit($value, 300, '') : $value];
            })
            ->all();
    }

    private function isSensitive(string $key): bool
    {
        $normalized = Str::of($key)->lower()->replace(['-', '.'], '_')->toString();

        return Arr::first(self::SENSITIVE_KEYS, fn (string $needle) => str_contains($normalized, $needle)) !== null;
    }
}
