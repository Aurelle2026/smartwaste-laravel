<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bin;
use App\Models\BinAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BinAlertController extends Controller
{
    /** Écran "Signaler un bac" : créer une alerte */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bin_code' => ['required', 'string', 'exists:bins,code'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $bin = Bin::where('code', $data['bin_code'])->firstOrFail();

        $alert = BinAlert::create([
            'code' => 'TMP',
            'bin_id' => $bin->id,
            'reporter_id' => $request->user()->id,
            'photo_path' => $request->file('photo')?->store('bin-alerts', 'public'),
            'comment' => $data['comment'] ?? null,
        ]);

        $alert->update(['code' => 'ALR-'.str_pad($alert->id, 4, '0', STR_PAD_LEFT)]);

        return response()->json(['data' => $alert->fresh(['bin'])], 201);
    }

    /** Écran "Suivi des alertes" : liste (les siennes, ou toutes pour ISACAM/admin) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role->value;

        $query = BinAlert::query()->with(['bin:id,code,name', 'reporter:id,name', 'assignee:id,name']);

        if (! in_array($role, ['isacam', 'admin', 'mairie'], true)) {
            $query->where('reporter_id', $user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    /** Cartes de résumé en haut de l'écran : "1 en attente", "1 en cours", "1 traité" */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role->value;

        $query = BinAlert::query();
        if (! in_array($role, ['isacam', 'admin', 'mairie'], true)) {
            $query->where('reporter_id', $user->id);
        }

        $counts = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json(['data' => [
            'en_attente' => $counts['en_attente'] ?? 0,
            'en_cours' => $counts['en_cours'] ?? 0,
            'traite' => $counts['traite'] ?? 0,
        ]]);
    }

    /** ISACAM/admin : prendre en charge puis traiter une alerte */
    public function updateStatus(Request $request, BinAlert $binAlert): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(BinAlert::STATUSES)],
        ]);

        $role = $request->user()->role->value;
        $target = $data['status'];
        $current = $binAlert->status;

        $allowed = in_array($role, ['isacam', 'admin'], true)
            && (
                ($current === 'en_attente' && $target === 'en_cours')
                || ($current === 'en_cours' && $target === 'traite')
            );

        if (! $allowed) {
            return response()->json([
                'message' => "Transition non autorisée : {$current} → {$target} pour ce rôle.",
            ], 403);
        }

        $binAlert->status = $target;
        if ($target === 'en_cours') {
            $binAlert->assigned_to = $request->user()->id;
            $binAlert->started_at = now();
        } elseif ($target === 'traite') {
            $binAlert->resolved_at = now();
            // Le bac signalé plein repasse à vide une fois traité
            $binAlert->bin()->update(['status' => 'vide', 'fill_level' => 0]);
        }
        $binAlert->save();

        return response()->json(['data' => $binAlert->fresh(['bin', 'reporter', 'assignee'])]);
    }
}
