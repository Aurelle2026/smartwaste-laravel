<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WasteOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WasteOfferController extends Controller
{
    private const MATERIALS = ['plastique', 'papier_carton', 'metal', 'verre', 'organique', 'electronique'];

    /** Citoyen : "Proposer mes déchets" */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'waste_type' => ['required', Rule::in(self::MATERIALS)],
            'quantity_kg' => ['required', 'numeric', 'min:0.1'],
            'recycler_id' => ['nullable', 'integer', 'exists:users,id'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if (! empty($data['recycler_id'])
            && User::where('id', $data['recycler_id'])->where('role', 'recycleur')->doesntExist()) {
            throw ValidationException::withMessages(['recycler_id' => ['Ce recycleur n\'existe pas.']]);
        }

        $offer = WasteOffer::create([
            'citizen_id' => $request->user()->id,
            'recycler_id' => $data['recycler_id'] ?? null,
            'waste_type' => $data['waste_type'],
            'quantity_kg' => $data['quantity_kg'],
            'photo_path' => $request->file('photo')?->store('waste-offers', 'public'),
        ]);

        return response()->json(['data' => $offer], 201);
    }

    /** Citoyen : ses propositions / Recycleur : celles qui lui sont proposées ou libres */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = WasteOffer::query()->with(['citizen:id,name,phone', 'recycler:id,name']);

        if ($user->role->value === 'citoyen') {
            $query->where('citizen_id', $user->id);
        } elseif ($user->role->value === 'recycleur') {
            $query->where(fn ($q) => $q->whereNull('recycler_id')->orWhere('recycler_id', $user->id));
        }
        // admin/mairie : tout voir, pas de filtre

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    /** Faire avancer une proposition */
    public function updateStatus(Request $request, WasteOffer $wasteOffer): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WasteOffer::STATUSES)],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $role = $user->role->value;
        $target = $data['status'];
        $current = $wasteOffer->status;

        $allowed = match (true) {
            $role === 'citoyen' && $wasteOffer->citizen_id === $user->id
                && $current === 'en_attente' && $target === 'annulee' => true,

            $role === 'recycleur'
                && ($wasteOffer->recycler_id === null || $wasteOffer->recycler_id === $user->id)
                && $current === 'en_attente' && in_array($target, ['acceptee', 'refusee']) => true,

            $role === 'recycleur' && $wasteOffer->recycler_id === $user->id
                && $current === 'acceptee' && $target === 'recuperee' => true,

            $role === 'recycleur' && $wasteOffer->recycler_id === $user->id
                && $current === 'recuperee' && $target === 'payee' => true,

            default => false,
        };

        if (! $allowed) {
            return response()->json([
                'message' => "Transition non autorisée : {$current} → {$target} pour ce rôle.",
            ], 403);
        }

        if ($target === 'payee' && empty($data['price'])) {
            throw ValidationException::withMessages(['price' => ['Le montant payé est requis.']]);
        }

        $wasteOffer->status = $target;
        if ($target === 'acceptee') {
            $wasteOffer->recycler_id = $user->id;
            $wasteOffer->accepted_at = now();
        } elseif ($target === 'recuperee') {
            $wasteOffer->collected_at = now();
        } elseif ($target === 'payee') {
            $wasteOffer->price = $data['price'];
            $wasteOffer->paid_at = now();
        }
        $wasteOffer->save();

        return response()->json(['data' => $wasteOffer->fresh()]);
    }
}
