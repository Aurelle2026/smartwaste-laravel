<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinAlert;
use App\Models\User;
use App\Models\WasteOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /** Écran "Mon profil" : cartes "Mon activité" + total gagné */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $alertsSent = BinAlert::where('reporter_id', $user->id)->count();
        $alertsResolved = BinAlert::where('reporter_id', $user->id)->where('status', 'traite')->count();

        $offersProposed = WasteOffer::where('citizen_id', $user->id)->count();
        $totalEarned = WasteOffer::where('citizen_id', $user->id)
            ->where('status', 'payee')
            ->sum('price');

        return response()->json(['data' => [
            'alerts_sent' => $alertsSent,
            'alerts_resolved' => $alertsResolved,
            'offers_proposed' => $offersProposed,
            'total_earned' => (float) $totalEarned,
        ]]);
    }

    /** Le crayon : modifier nom / email / téléphone / mot de passe */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'current_password' => ['required_with:new_password', 'string'],
            'new_password' => ['sometimes', 'string', 'min:6'],
        ]);

        if (isset($data['new_password'])) {
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Mot de passe actuel incorrect.'],
                ]);
            }
            $user->password = $data['new_password'];
        }

        $user->fill(collect($data)->only(['name', 'email', 'phone'])->toArray());
        $user->save();

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    private function payload(User $user): array
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
