<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication is required.');
        }

        $allowedRoles = collect($roles)
            ->map(fn (string $role): ?UserRole => match (strtolower($role)) {
                'employee' => UserRole::Employee,
                'manager' => UserRole::Manager,
                'admin' => UserRole::Admin,
                default => null,
            })
            ->filter();

        if ($allowedRoles->isEmpty() || ! $allowedRoles->contains($user->role)) {
            abort(Response::HTTP_FORBIDDEN, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
