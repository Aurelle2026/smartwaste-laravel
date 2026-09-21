<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecyclerNumber extends Model
{
    protected $fillable = ['number', 'user_id', 'used_at'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }
}
