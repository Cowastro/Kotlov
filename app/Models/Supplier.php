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
        'contact', 'communication_channels', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'currency_rate' => 'float',
        'marketplace_commission_rate' => 'decimal:4',
        'settlement_terms_days' => 'integer',
        'automatic_order_transfer_enabled' => 'boolean',
        'automatic_order_transfer_approved_at' => 'datetime',
        'communication_channels' => 'array',
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
        $crmPhones = $this->crmCounterparty?->contactPoints
            ->where('is_active', true)
            ->where('type', 'phone')
            ->pluck('value')
            ->filter()
            ->values()
            ->all() ?? [];

        $channelPhones = collect($this->communication_channels ?? [])
            ->filter(fn ($channel): bool => is_array($channel))
            ->filter(fn (array $channel): bool => in_array($channel['type'] ?? null, ['phone', 'whatsapp', 'viber', 'telegram'], true))
            ->pluck('value')
            ->filter(fn ($value): bool => preg_match('/\d{7,}/', preg_replace('/\D/', '', (string) $value) ?? '') === 1)
            ->values()
            ->all();

        // Price-list URLs may contain long numeric identifiers (for example a
        // Google Sheets `gid`) which are not telephone numbers.
        $contactText = preg_replace('~https?://\S+~iu', ' ', (string) $this->contact) ?? '';
        preg_match_all('/(?<!\w)(?:\+?\d[\d\s().\-]{5,}\d)(?!\w)/u', $contactText, $matches);

        // Direct operational contacts are intentionally first: this is the
        // number managers should call before a general CRM company phone.
        return collect([...$channelPhones, ...$crmPhones, ...($matches[0] ?? [])])
            ->map(fn (string $phone): string => trim($phone))
            ->filter()
            ->unique(fn (string $phone): string => preg_replace('/\D/', '', $phone) ?? $phone)
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function contactEmailAddresses(): array
    {
        $emails = $this->crmCounterparty?->contactPoints
            ->where('is_active', true)
            ->where('type', 'email')
            ->sortByDesc('is_primary')
            ->pluck('value')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        $channelEmails = collect($this->communication_channels ?? [])
            ->flatMap(function ($channel, $key): array {
                if (is_string($key) && in_array(mb_strtolower($key), ['email', 'mail'], true)) {
                    return is_array($channel) ? $channel : [$channel];
                }

                if (is_array($channel) && in_array(mb_strtolower((string) ($channel['type'] ?? '')), ['email', 'mail'], true)) {
                    return [$channel['value'] ?? null];
                }

                return [];
            })
            ->filter(fn ($value): bool => filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn ($value): string => trim((string) $value))
            ->values()
            ->all();

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', (string) $this->contact, $matches);

        return collect([...$emails, ...$channelEmails, ...($matches[0] ?? [])])
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

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class)->withTimestamps();
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(SupplierSync::class);
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
