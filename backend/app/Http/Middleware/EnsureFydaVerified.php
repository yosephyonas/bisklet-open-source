<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFydaVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $role = $user->role;
        $roleValue = $role instanceof UserRole ? $role->value : strtolower((string) $role);

        if (! in_array($roleValue, [UserRole::Admin->value, UserRole::Mechanic->value], true)
            && $user->fyda_verified_at === null) {
            abort(Response::HTTP_FORBIDDEN, 'FYDA verification is required to perform this action.');
        }

        return $next($request);
    }
}
