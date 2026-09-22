<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecyclerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecyclerProfileController extends Controller
{
    // Même liste que WasteOfferController::MATERIALS — un recycleur ne peut
    // accepter que des types de déchets réellement proposables.
    private const MATERIALS = ['plastique', 'papier_carton', 'metal', 'verre', 'organique', 'electronique'];

    /** Écran "Recycleurs près de vous" */
    public function index(Request $request): JsonResponse
    {
        $query = RecyclerProfile::query()->with('user:id,name,identifier');

        if ($material = $request->query('material')) {
            $query->whereJsonContains('materials', $material);
        }

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if ($lat !== null && $lng !== null) {
            // Formule de Haversine : distance en km
            $query->selectRaw('
                *, (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            ', [$lat, $lng, $lat])->orderBy('distance_km');
        } else {
            $query->orderByDesc('rating');
        }

        $profiles = $query->get()->map(fn (RecyclerProfile $p) => [
            'user_id' => $p->user_id,
            'company_name' => $p->company_name,
            'zone' => $p->zone,
            'materials' => $p->materials,
            'rating' => $p->rating,
            'distance_km' => isset($p->distance_km) ? round($p->distance_km, 1) : null,
        ]);

        return response()->json(['data' => $profiles]);
    }

    /**
     * Le recycleur connecté consulte son propre profil (existe ou non).
     *
     * Tant qu'il n'a pas encore rempli son profil, il n'apparaît pas dans
     * `GET /recyclers` : cette route sert à savoir si c'est le cas côté front.
     */
    public function me(Request $request): JsonResponse
    {
        $profile = RecyclerProfile::where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $profile]);
    }

    /**
     * Le recycleur connecté crée ou met à jour son profil (nom d'entreprise,
     * zone, position, matériaux acceptés). Sans ça, `GET /recyclers` ne le
     * proposera jamais aux citoyens.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'materials' => ['required', 'array', 'min:1'],
            'materials.*' => [Rule::in(self::MATERIALS)],
        ]);

        $profile = RecyclerProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['data' => $profile]);
    }
}
