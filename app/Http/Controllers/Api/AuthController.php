<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token auth for the BizStay Flutter app (both roles, one endpoint).
 * Managers/admins sign in with email+password. Tenants sign in with
 * phone+password (their User row is linked via guest_id; seeded from
 * guest phone on first tenant login or created by the manager).
 */
final class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $login = trim($data['login']);
        $user = str_contains($login, '@')
            ? User::query()->where('email', $login)->first()
            : User::query()->where('email', $login)->orWhereHas('guest', fn ($q) => $q->where('phone', $login))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => ['Invalid credentials.']]);
        }

        $token = $user->createToken($data['device_name'] ?? 'bizstay-flutter')->plainTextToken;

        return response()->json([
            'token' => $token,
            'role' => $user->role ?? 'manager',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'manager',
                'guest_id' => $user->guest_id,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('guest.currentBooking.bed.room');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? 'manager',
            'guest' => $user->guest ? [
                'id' => $user->guest->id,
                'full_name' => $user->guest->full_name,
                'phone' => $user->guest->phone,
                'bed' => $user->guest->currentBooking?->bed?->bed_code,
                'room' => $user->guest->currentBooking?->bed?->room?->room_number,
                'outstanding' => $user->guest->outstandingBalance(),
            ] : null,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
