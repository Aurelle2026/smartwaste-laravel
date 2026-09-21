<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RecyclerNumber;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(UserRole::registrable())],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'recycler_number' => ['required_if:role,recycleur', 'nullable', 'string'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $identifier = null;
            $recyclerNumber = null;

            if ($data['role'] === UserRole::Recycleur->value) {
                $recyclerNumber = RecyclerNumber::where('number', $data['recycler_number'])
                    ->whereNull('user_id')
                    ->lockForUpdate()
                    ->first();

                if (! $recyclerNumber) {
                    throw ValidationException::withMessages([
                        'recycler_number' => ['Numéro de recycleur invalide ou déjà utilisé.'],
                    ]);
                }
                $identifier = $recyclerNumber->number;
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => $data['role'],
                'identifier' => $identifier,
                'password' => $data['password'],
            ]);

            $recyclerNumber?->update(['user_id' => $user->id, 'used_at' => now()]);

            return $user;
        });

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->createToken('mobile')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(UserRole::values())],
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $query = User::where('role', $data['role']);

        if ($data['role'] === UserRole::Citoyen->value) {
            $query->where(fn ($q) => $q->where('email', $data['identifier'])
                ->orWhere('phone', $data['identifier']));
        } else {
            $query->where('identifier', $data['identifier']);
        }

        $user = $query->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Identifiants incorrects.'],
            ]);
        }

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->createToken('mobile')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'identifier' => $user->identifier,
        ];
    }
}
