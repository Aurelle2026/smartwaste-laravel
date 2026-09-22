<?php

namespace Database\Seeders;

use App\Models\Bin;
use App\Models\BinAlert;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        $citoyen = User::where('role', 'citoyen')->first();
        $isacam = User::where('role', 'isacam')->first();

        $bin001 = Bin::where('code', 'BAC-001')->first(); // plein
        $bin003 = Bin::where('code', 'BAC-003')->first();
        $bin004 = Bin::where('code', 'BAC-004')->first();

        if (! $citoyen || ! $isacam || ! $bin001 || ! $bin003 || ! $bin004) {
            return; // dépend de DatabaseSeeder déjà exécuté
        }

        $data = [
            ['bin' => $bin003, 'status' => 'en_attente'],
            ['bin' => $bin004, 'status' => 'en_cours', 'assigned' => true],
            ['bin' => $bin001, 'status' => 'traite', 'assigned' => true, 'resolved' => true],
        ];

        foreach ($data as $row) {
            $alert = BinAlert::create([
                'code' => 'TMP',
                'bin_id' => $row['bin']->id,
                'reporter_id' => $citoyen->id,
                'assigned_to' => ($row['assigned'] ?? false) ? $isacam->id : null,
                'comment' => 'Bac plein depuis ce matin',
                'status' => $row['status'],
                'started_at' => ($row['assigned'] ?? false) ? now()->subDay() : null,
                'resolved_at' => ($row['resolved'] ?? false) ? now() : null,
            ]);
            $alert->update(['code' => 'ALR-'.str_pad($alert->id, 4, '0', STR_PAD_LEFT)]);
        }
    }
}
