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
        'working_hours', 'map_url', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'phones' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function city(): BelongsTo { return $this->belongsTo(City::class); }
    public function client(): BelongsTo { return $this->belongsTo(B2bClient::class, 'b2b_client_id'); }
    public function contactViews(): HasMany { return $this->hasMany(PartnerBranchContactView::class); }

    public function scopeVisibleIn(Builder $query, ?City $city): Builder
    {
        return $query->where('is_active', true)
            ->where('city_id', $city?->id ?? 0)
            ->orderBy('sort_order')->orderBy('name');
    }

    /** @return list<array{display: string, href: string}> */
    public function phoneLinks(): array
    {
        return collect($this->phones ?? [])->filter()
            ->map(fn ($phone): array => ['display' => (string) $phone, 'href' => '+'.preg_replace('/\D+/', '', (string) $phone)])
            ->values()->all();
    }
}
