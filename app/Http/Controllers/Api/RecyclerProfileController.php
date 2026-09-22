<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecyclerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecyclerProfileController extends Controller
{
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
}
