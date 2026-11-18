<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        $normalizedRoles = array_map(static function ($role) {
            if ($role instanceof UserRole) {
                return $role->value;
            }

            return strtolower((string) $role);
        }, $roles);

        $currentRole = $user->role;
        $currentRoleValue = $currentRole instanceof UserRole
            ? $currentRole->value
            : strtolower((string) $currentRole);

        if (! in_array($currentRoleValue, $normalizedRoles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not authorized to access this resource.');
        }

        return $next($request);
    }
}
