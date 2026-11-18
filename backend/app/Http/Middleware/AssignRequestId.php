<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AssignRequestId
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = trim((string) $request->headers->get('X-Request-Id'));

        if ($requestId === '') {
            $requestId = (string) Str::uuid();
        }

        $request->headers->set('X-Request-Id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
