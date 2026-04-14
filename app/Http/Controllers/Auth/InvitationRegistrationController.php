<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\UserAddress;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationRegistrationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = $this->findValidInvitation($token);

        return view('auth.accept-invitation-modern', [
            'invitation' => $invitation,
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->findValidInvitation($token);

        $user = DB::transaction(function () use ($request, $invitation) {
            $user = User::query()->updateOrCreate(
                ['employee_number' => $invitation->employee_number],
                [
                    'full_name' => $request->validated('full_name'),
                    'phone' => $request->validated('phone'),
                    'password' => $request->validated('password'),
                    'role' => $invitation->role ?? UserRole::Employee,
                    'is_active' => true,
                ],
            );

            $defaultAddress = trim((string) $request->validated('default_address'));
            if ($defaultAddress !== '') {
                $exists = UserAddress::query()
                    ->where('user_id', $user->id)
                    ->whereRaw('LOWER(address) = ?', [mb_strtolower($defaultAddress)])
                    ->exists();

                if (! $exists) {
                    UserAddress::query()->create([
                        'user_id' => $user->id,
                        'address' => $defaultAddress,
                    ]);
                }
            }

            $invitation->forceFill([
                'is_used' => true,
                'used_at' => now(),
                'used_by' => $user->id,
            ])->save();

            return $user;
        });

        AuditLogger::log($user, 'invitation_used', $invitation, [
            'is_used' => false,
        ], [
            'is_used' => true,
            'used_by' => $user->id,
        ]);

        AuditLogger::log($user, 'user_registered', $user, [], [
            'employee_number' => $user->employee_number,
            'role' => $user->role?->name,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()
            ->route('employee.requests.index')
            ->with('status', 'Регистрация завершена. Добро пожаловать в систему.');
    }

    private function findValidInvitation(string $token): Invitation
    {
        return Invitation::query()
            ->where('token', $token)
            ->where('is_used', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();
    }
}
