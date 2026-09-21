<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Bin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BinController extends Controller
{
    /** Public : la carte du visiteur */
    public function index(Request $request): JsonResponse
    {
        $bins = Bin::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $bins]);
    }

    public function show(Bin $bin): JsonResponse
    {
        return response()->json(['data' => $bin]);
    }

    public function legend(): JsonResponse
    {
        return response()->json(['data' => [
            ['status' => 'vide', 'label' => 'Vide', 'color' => '#4CAF50'],
            ['status' => 'collecte_en_cours', 'label' => 'Collecte en cours', 'color' => '#FF9800'],
            ['status' => 'plein', 'label' => 'Plein', 'color' => '#F44336'],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'unique:bins,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'fill_level' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', Rule::in(Bin::STATUSES)],
        ]);

        $user = $request->user();
        if ($user->role === UserRole::Mairie) {
            $data['municipality_code'] = $user->identifier;
        } else {
            $data['municipality_code'] = $request->input('municipality_code');
        }

        return response()->json(['data' => Bin::create($data)], 201);
    }

    public function update(Request $request, Bin $bin): JsonResponse
    {
        $this->authorizeMunicipality($request, $bin);

        $data = $request->validate([
            'code' => ['sometimes', 'string', Rule::unique('bins', 'code')->ignore($bin->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'fill_level' => ['sometimes', 'integer', 'between:0,100'],
            'status' => ['sometimes', Rule::in(Bin::STATUSES)],
        ]);

        $bin->update($data);

        return response()->json(['data' => $bin->fresh()]);
    }

    /** Recycleur / mairie / admin : changer l'état d'un bac */
    public function updateStatus(Request $request, Bin $bin): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Bin::STATUSES)],
            'fill_level' => ['nullable', 'integer', 'between:0,100'],
        ]);

        if ($data['status'] === 'vide' && ! isset($data['fill_level'])) {
            $data['fill_level'] = 0;
        }

        $bin->update($data);

        return response()->json(['data' => $bin->fresh()]);
    }

    public function destroy(Request $request, Bin $bin): JsonResponse
    {
        $this->authorizeMunicipality($request, $bin);
        $bin->delete();

        return response()->json(['message' => 'Bac supprimé.']);
    }

    /** Une mairie ne gère que ses propres bacs */
    private function authorizeMunicipality(Request $request, Bin $bin): void
    {
        $user = $request->user();

        if ($user->role === UserRole::Mairie && $bin->municipality_code !== $user->identifier) {
            abort(403, 'Ce bac ne relève pas de votre mairie.');
        }
    }
}
