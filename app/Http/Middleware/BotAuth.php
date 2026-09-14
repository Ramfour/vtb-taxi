<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BotAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('taxi.bot_api_secret');

        if (! $secret || $request->header('X-Bot-Token') !== $secret) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid bot token.');
        }

        $telegramId = $request->header('X-Telegram-Id')
            ?? $request->input('telegram_id');

        if (! $telegramId) {
            abort(Response::HTTP_UNAUTHORIZED, 'Telegram ID is required.');
        }

        $user = User::query()
            ->active()
            ->where('telegram_id', (string) $telegramId)
            ->first();

        if (! $user) {
            abort(Response::HTTP_NOT_FOUND, 'User not found or inactive.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }
}
