<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecyclerProfile extends Model
{
    protected $fillable = ['user_id', 'company_name', 'zone', 'latitude', 'longitude', 'materials', 'rating'];

    protected function casts(): array
    {
        return [
            'materials' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'rating' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
