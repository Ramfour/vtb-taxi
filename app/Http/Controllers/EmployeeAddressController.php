<?php

namespace App\Http\Controllers;

use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $addresses = $this->ensureDefaultAddress($user);

        return response()->json([
            'addresses' => $addresses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'address' => ['required', 'string', 'max:2000'],
        ]);

        $address = trim($data['address']);

        if ($address !== '') {
            $exists = UserAddress::query()
                ->where('user_id', $user->id)
                ->whereRaw('LOWER(address) = ?', [mb_strtolower($address)])
                ->exists();

            if (! $exists) {
                UserAddress::query()->create([
                    'user_id' => $user->id,
                    'address' => $address,
                ]);
            }
        }

        return response()->json([
            'addresses' => $this->ensureDefaultAddress($user),
        ]);
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        $user = $request->user();

        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $address->delete();

        return response()->json([
            'addresses' => $this->ensureDefaultAddress($user),
        ]);
    }

    private function ensureDefaultAddress($user): array
    {
        $addresses = UserAddress::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        if ($addresses->isEmpty() && $user->default_address) {
            $addresses->push(UserAddress::query()->create([
                'user_id' => $user->id,
                'address' => $user->default_address,
            ]));
        }

        return $addresses->map(fn (UserAddress $address) => [
            'id' => $address->id,
            'address' => $address->address,
        ])->values()->all();
    }
}
