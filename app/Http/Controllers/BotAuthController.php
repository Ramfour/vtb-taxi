<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotAuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'              => $user->id,
            'full_name'       => $user->full_name,
            'employee_number' => $user->employee_number,
            'phone'           => $user->phone,
            'role'            => $user->role->value,
            'role_name'       => match ($user->role) {
                UserRole::Admin   => 'admin',
                UserRole::Manager => 'manager',
                default           => 'employee',
            },
            'is_active'       => $user->is_active,
            'telegram_id'     => $user->telegram_id,
        ]);
    }

    public function linkTelegram(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_number' => ['required', 'string'],
            'telegram_id'     => ['required', 'string'],
        ]);

        $user = User::query()
            ->active()
            ->where('employee_number', $data['employee_number'])
            ->first();

        if (! $user) {
            return response()->json(['error' => 'Employee not found or inactive.'], 404);
        }

        $conflict = User::query()
            ->where('telegram_id', $data['telegram_id'])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'This Telegram ID is already linked to another account.'], 409);
        }

        $user->telegram_id = $data['telegram_id'];
        $user->save();

        return response()->json([
            'id'              => $user->id,
            'full_name'       => $user->full_name,
            'employee_number' => $user->employee_number,
            'phone'           => $user->phone,
            'role'            => $user->role->value,
            'role_name'       => match ($user->role) {
                UserRole::Admin   => 'admin',
                UserRole::Manager => 'manager',
                default           => 'employee',
            },
            'telegram_id'     => $user->telegram_id,
        ]);
    }

    public function unlinkTelegram(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->telegram_id = null;
        $user->save();

        return response()->json(['ok' => true]);
    }
}
