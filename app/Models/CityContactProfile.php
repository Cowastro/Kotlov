<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityContactProfile extends Model
{
    protected $fillable = [
        'city_id',
        'company_name',
        'phones',
        'email',
        'address',
        'map_url',
        'map_embed_url',
        'is_active',
    ];

    protected $casts = [
        'phones' => 'array',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
