<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    protected $fillable = [
        'crm_counterparty_id', 'code', 'name', 'currency', 'currency_rate',
        'marketplace_commission_rate', 'settlement_terms_days', 'settlement_notes',
        'automatic_order_transfer_enabled', 'automatic_order_transfer_approved_at',
        'automatic_order_transfer_approved_by', 'automatic_order_transfer_note',
        'contact', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'currency_rate' => 'float',
        'marketplace_commission_rate' => 'decimal:4',
        'settlement_terms_days' => 'integer',
        'automatic_order_transfer_enabled' => 'boolean',
        'automatic_order_transfer_approved_at' => 'datetime',
    ];

    public function imports(): HasMany
    {
        return $this->hasMany(SupplierPriceImport::class);
    }

    public function crmCounterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'crm_counterparty_id');
    }

    /** @return array<int, string> */
    public function contactPhoneNumbers(): array
    {
        $phones = $this->crmCounterparty?->contactPoints
            ->where('is_active', true)
            ->where('type', 'phone')
            ->pluck('value')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        if ($phones !== []) {
            return $phones;
        }

        preg_match_all('/(?<!\w)(?:\+?\d[\d\s().\-]{5,}\d)(?!\w)/u', (string) $this->contact, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $phone): string => trim($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function contactEmailAddresses(): array
    {
        $emails = $this->crmCounterparty?->contactPoints
            ->where('is_active', true)
            ->where('type', 'email')
            ->pluck('value')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        if ($emails !== []) {
            return $emails;
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', (string) $this->contact, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email): string => trim($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function contactSummary(): ?string
    {
        $contacts = collect([
            ...$this->contactPhoneNumbers(),
            ...$this->contactEmailAddresses(),
        ])->filter()->unique()->values();

        return $contacts->isNotEmpty()
            ? $contacts->implode(' · ')
            : (filled($this->contact) ? trim((string) $this->contact) : null);
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

    public function autoTransferDecisions(): HasMany
    {
        return $this->hasMany(SupplierAutoTransferDecision::class);
    }

    public function automaticOrderTransferApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'automatic_order_transfer_approved_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
