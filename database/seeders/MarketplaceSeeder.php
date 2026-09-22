<?php

namespace Database\Seeders;

use App\Models\RecyclerNumber;
use App\Models\RecyclerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    /** Recycleurs visibles sur l'écran "Vendre mes déchets" */
    public function run(): void
    {
        $recyclers = [
            ['RCY-0003', 'EcoRecycle Cameroun', 'Zone industrielle', 3.8480, 11.5021, ['plastique', 'papier_carton', 'metal'], 4.7],
            ['RCY-0004', 'GreenLoop Recyclage', 'Mvog-Ada', 3.8600, 11.5100, ['verre', 'plastique', 'organique'], 4.5],
            ['RCY-0005', 'Bourse Verte SARL', 'Nlongkak', 3.8800, 11.5200, ['metal', 'electronique'], 4.2],
        ];

        foreach ($recyclers as [$number, $company, $zone, $lat, $lng, $materials, $rating]) {
            $u = User::firstOrCreate(
                ['email' => strtolower(str_replace(' ', '', $company)).'@test.cm'],
                [
                    'name' => $company,
                    'phone' => '69'.random_int(1000000, 9999999),
                    'role' => 'recycleur',
                    'identifier' => $number,
                    'password' => 'password',
                ]
            );

            RecyclerNumber::where('number', $number)->update(['user_id' => $u->id, 'used_at' => now()]);

            RecyclerProfile::firstOrCreate(
                ['user_id' => $u->id],
                [
                    'company_name' => $company, 'zone' => $zone,
                    'latitude' => $lat, 'longitude' => $lng,
                    'materials' => $materials, 'rating' => $rating,
                ]
            );
        }
    }
}
