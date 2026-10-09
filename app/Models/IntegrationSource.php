<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSource extends Model
{
    protected $fillable = [
        'code', 'name', 'driver', 'username', 'password_hash', 'is_active', 'create_products',
        'update_prices', 'update_stock', 'settings',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'create_products' => 'boolean',
        'update_prices' => 'boolean',
        'update_stock' => 'boolean',
        'settings' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(IntegrationProduct::class);
    }
}
