<?php

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\RecyclerNumber;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Comptes sans inscription (accès fourni directement) — CHANGER les mots de passe en production
        $fixed = [
            ['name' => 'Administrateur', 'role' => 'admin', 'identifier' => 'admin', 'email' => 'admin@smartwaste.cm'],
            ['name' => 'Bourse Nationale des Déchets', 'role' => 'bnd', 'identifier' => 'BND-001', 'email' => 'bnd@smartwaste.cm'],
            ['name' => 'ISACAM', 'role' => 'isacam', 'identifier' => 'ISACAM-001', 'email' => 'isacam@smartwaste.cm'],
            ['name' => 'Mairie de Yaoundé 1er', 'role' => 'mairie', 'identifier' => 'MAIRIE-YDE1', 'email' => 'mairie@smartwaste.cm'],
        ];
        foreach ($fixed as $u) {
            User::create($u + ['password' => 'password']);
        }

        User::create([
            'name' => 'Citoyen Test', 'email' => 'citoyen@test.cm', 'phone' => '690000001',
            'role' => 'citoyen', 'password' => 'password',
        ]);

        // Numéros attribués par la BND
        foreach (['RCY-0001', 'RCY-0002', 'RCY-0003', 'RCY-0004', 'RCY-0005'] as $n) {
            RecyclerNumber::create(['number' => $n]);
        }

        $recycler = User::create([
            'name' => 'Recycleur Test', 'email' => 'recycleur@test.cm', 'phone' => '690000002',
            'role' => 'recycleur', 'identifier' => 'RCY-0001', 'password' => 'password',
        ]);
        RecyclerNumber::where('number', 'RCY-0001')->update(['user_id' => $recycler->id, 'used_at' => now()]);

        // Bacs (Yaoundé)
        $bins = [
            ['BAC-001', 'Marché Mokolo', 'Mokolo, Yaoundé', 3.8697, 11.5021, 92, 'plein'],
            ['BAC-002', 'Carrefour Warda', 'Warda, Yaoundé', 3.8667, 11.5167, 15, 'vide'],
            ['BAC-003', 'Poste Centrale', 'Centre-ville, Yaoundé', 3.8667, 11.5167, 55, 'collecte_en_cours'],
            ['BAC-004', 'Marché Mvog-Mbi', 'Mvog-Mbi, Yaoundé', 3.8340, 11.5170, 100, 'plein'],
            ['BAC-005', 'Université de Yaoundé I', 'Ngoa-Ekellé, Yaoundé', 3.8617, 11.5019, 8, 'vide'],
        ];
        foreach ($bins as [$code, $name, $addr, $lat, $lng, $fill, $status]) {
            Bin::create([
                'code' => $code, 'name' => $name, 'address' => $addr,
                'latitude' => $lat, 'longitude' => $lng,
                'fill_level' => $fill, 'status' => $status,
                'municipality_code' => 'MAIRIE-YDE1',
            ]);
        }

        $this->call([
            MarketplaceSeeder::class,
            AlertSeeder::class,
        ]);
    }
}
