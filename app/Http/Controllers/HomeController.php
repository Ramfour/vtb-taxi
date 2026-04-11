<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (Auth::check()) {
            return in_array(Auth::user()->role, [UserRole::Manager, UserRole::Admin], true)
                ? redirect()->route('manager.requests.index')
                : redirect()->route('employee.requests.index');
        }

        return view('landing-modern', [
            'employees' => User::query()
                ->active()
                ->where('role', UserRole::Employee->value)
                ->orderBy('full_name')
                ->get(),
            'managers' => User::query()
                ->active()
                ->whereIn('role', [UserRole::Manager->value, UserRole::Admin->value])
                ->orderBy('full_name')
                ->get(),
        ]);
    }
}
