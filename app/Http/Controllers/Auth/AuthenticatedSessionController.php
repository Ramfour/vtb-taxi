<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login-modern');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt([
            'employee_number' => $credentials['employee_number'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], true)) {
            return back()
                ->withErrors([
                    'employee_number' => 'Неверный табельный номер или пароль.',
                ])
                ->onlyInput('employee_number');
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (in_array($user->role, [UserRole::Manager, UserRole::Admin], true)) {
            return redirect()->route('manager.requests.index');
        }

        return redirect()->route('employee.requests.index');
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
