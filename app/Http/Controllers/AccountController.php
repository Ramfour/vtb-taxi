<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class AccountController extends Controller
{
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        AuditLogger::log($request->user(), 'password_updated', $request->user(), [], []);

        return redirect()
            ->back()
            ->with('status', 'Пароль обновлён.');
    }
}
