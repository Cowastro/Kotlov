<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketResearchReport extends Model
{
    public const DECISIONS = [
        'price_applied' => 'Цена применена',
        'price_unchanged' => 'Цена подтверждена',
        'consultation_hold' => 'Оставлено на консультации',
    ];

    protected $fillable = [
        'product_id', 'batch_code', 'review_reason', 'summary', 'verdict', 'confidence',
        'decision', 'decision_reason', 'price_before', 'recommended_price', 'price_after',
        'requires_price_confirmation', 'search_queries', 'rejected_candidates',
        'excluded_offers', 'observations_snapshot', 'research_model', 'researched_at', 'applied_at',
        'research_payload',
    ];

    protected $casts = [
        'price_before' => 'decimal:2',
        'recommended_price' => 'decimal:2',
        'price_after' => 'decimal:2',
        'requires_price_confirmation' => 'boolean',
        'search_queries' => 'array',
        'rejected_candidates' => 'array',
        'excluded_offers' => 'array',
        'research_payload' => 'array',
        'observations_snapshot' => 'array',
        'researched_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function decisionLabel(): string
    {
        return self::DECISIONS[$this->decision] ?? $this->decision;
    }
}
