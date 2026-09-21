<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bin extends Model
{
    public const STATUSES = ['vide', 'collecte_en_cours', 'plein'];

    protected $fillable = [
        'code', 'name', 'address', 'latitude', 'longitude',
        'fill_level', 'status', 'municipality_code',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'fill_level' => 'integer',
        ];
    }
}
