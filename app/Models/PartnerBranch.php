<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A partner's retail point in a city ("филиал KOTLOV"); a city can have several. */
class PartnerBranch extends Model
{
    protected $fillable = [
        'city_id', 'b2b_client_id', 'name', 'address', 'phones', 'email',
        'working_hours', 'map_url', 'storefront_title', 'storefront_intro',
        'storefront_promo', 'hero_image', 'gallery', 'map_embed_url',
        'seo_title', 'seo_description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'pending_changes' => 'array',
        'changes_submitted_at' => 'datetime',
        'phones' => 'array',
        'gallery' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    public function contactViews(): HasMany
    {
        return $this->hasMany(PartnerBranchContactView::class);
    }

    public function isPublic(): bool
    {
        return $this->is_active && $this->client?->status === 'approved';
    }

    public function scopeVisibleIn(Builder $query, ?City $city): Builder
    {
        // Only points of an approved B2B partner are public: pausing the partner hides its points.
        return $query->when($city?->primary_partner_branch_id, fn (Builder $query, $id) => $query->orderByRaw('CASE WHEN partner_branches.id = ? THEN 0 ELSE 1 END', [$id]))
            ->where('is_active', true)
            ->where('city_id', $city?->id ?? 0)
            ->whereHas('client', fn (Builder $client) => $client->where('status', 'approved'))
            ->orderBy('sort_order')->orderBy('name');
    }

    /** @return list<array{display: string, href: string}> */
    public function phoneLinks(): array
    {
        return collect($this->phones ?? [])->filter()
            ->map(fn ($phone): array => ['display' => (string) $phone, 'href' => '+'.preg_replace('/\D+/', '', (string) $phone)])
            ->values()->all();
    }

    public function mapLink(): ?string
    {
        if (trim((string) $this->map_url) !== '') {
            return $this->map_url;
        }

        return trim((string) $this->address) !== ''
            ? 'https://yandex.by/maps/?text='.rawurlencode($this->address)
            : null;
    }

    public function mapEmbedLink(): ?string
    {
        if (trim((string) $this->map_embed_url) !== '') {
            return $this->map_embed_url;
        }

        $address = trim((string) $this->address);
        if ($address === '') {
            return null;
        }

        // Locate the building, not an old business registered in the same unit.
        // The complete address including the unit remains visible beside the map.
        $address = preg_replace('/,\s*(?:пав(?:ильон)?\.?|офис|оф\.|каб\.|помещение|пом\.)\s*.+$/ui', '', $address);

        $cityName = trim((string) $this->city?->name);
        if ($cityName !== '' && mb_stripos($address, $cityName) === false) {
            $address = $cityName.', '.$address;
        }

        return 'https://www.google.com/maps?'.http_build_query([
            'q' => $address.', Беларусь', 'hl' => 'ru', 'z' => 16, 'output' => 'embed',
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
