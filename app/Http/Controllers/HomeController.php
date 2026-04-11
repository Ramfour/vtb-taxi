<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
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
