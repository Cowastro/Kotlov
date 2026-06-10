<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'code', 'name', 'currency', 'currency_rate',
        'contact', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'currency_rate' => 'float',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(SupplierPriceImport::class);
    }

    public function priceItems(): HasMany
    {
        return $this->hasMany(SupplierPriceItem::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(SupplierProductMapping::class, 'supplier_code', 'code');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
