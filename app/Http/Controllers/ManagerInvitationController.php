<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInvitationRequest;
use App\Models\Invitation;
use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ManagerInvitationController extends Controller
{
    public function store(CreateInvitationRequest $request): RedirectResponse
    {
        $requestedRole = $request->validated('role');
        $role = $request->user()?->role === UserRole::Admin && $requestedRole === 'manager'
            ? UserRole::Manager
            : UserRole::Employee;

        $invitation = Invitation::query()->create([
            'token' => Str::random(64),
            'employee_number' => $request->validated('employee_number'),
            'role' => $role,
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays((int) ($request->validated('expires_in_days') ?? 7)),
        ]);

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Приглашение создано: '.route('invitation.accept.show', $invitation->token));
    }
}
