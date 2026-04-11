<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveActingUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-Id')
            ?? $request->query('user_id')
            ?? $request->input('user_id');

        if (! $userId) {
            abort(Response::HTTP_UNAUTHORIZED, 'User context is required.');
        }

        $user = User::query()
            ->active()
            ->find($userId);

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'User not found or inactive.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }
}
