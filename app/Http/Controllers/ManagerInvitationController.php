<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ManagerInvitationController extends Controller
{
    public function store(CreateInvitationRequest $request): RedirectResponse
    {
        $invitation = Invitation::query()->create([
            'token' => Str::random(64),
            'employee_number' => $request->validated('employee_number'),
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays($request->validated('expires_in_days') ?? 7),
        ]);

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Приглашение создано: '.route('invitation.accept.show', $invitation->token));
    }
}
