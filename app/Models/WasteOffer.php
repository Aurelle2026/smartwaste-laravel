<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WasteOffer extends Model
{
    public const STATUSES = ['en_attente', 'acceptee', 'refusee', 'recuperee', 'payee', 'annulee'];

    protected $fillable = [
        'citizen_id', 'recycler_id', 'waste_type', 'quantity_kg',
        'photo_path', 'status', 'price', 'accepted_at', 'collected_at', 'paid_at',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'float',
            'price' => 'float',
            'accepted_at' => 'datetime',
            'collected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function recycler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recycler_id');
    }
}
