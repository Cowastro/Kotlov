<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    protected $fillable = [
        'code', 'name', 'currency', 'currency_rate',
        'contact', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function sources(): HasMany
    {
        return $this->hasMany(SupplierSource::class);
    }

    public function integrationSources(): HasMany
    {
        return $this->hasMany(IntegrationSource::class);
    }

    public function channelTransitions(): HasMany
    {
        return $this->hasMany(SupplierChannelTransition::class);
    }

    public function latestChannelTransition(): HasOne
    {
        return $this->hasOne(SupplierChannelTransition::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereIn('status', [
                SupplierChannelTransition::STATUS_LEGACY_DISABLED,
                SupplierChannelTransition::STATUS_ROLLED_BACK,
            ]),
        );
    }

    public function usesLegacyChannel(): bool
    {
        $transition = $this->relationLoaded('latestChannelTransition')
            ? $this->latestChannelTransition
            : $this->latestChannelTransition()->first();

        return $transition?->status !== SupplierChannelTransition::STATUS_LEGACY_DISABLED;
    }

    public function orderRequests(): HasMany
    {
        return $this->hasMany(SupplierOrderRequest::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
