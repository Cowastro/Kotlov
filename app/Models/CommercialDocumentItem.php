<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialDocumentItem extends Model
{
    protected $fillable = [
        'commercial_document_id', 'product_id', 'name', 'sku', 'unit', 'quantity',
        'unit_price', 'discount_percent', 'vat_rate', 'line_total', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (CommercialDocumentItem $item): void {
            $gross = (float) $item->quantity * (float) $item->unit_price;
            $item->line_total = round($gross * (1 - ((float) $item->discount_percent / 100)), 2);
        });

        static::saved(fn (CommercialDocumentItem $item) => $item->document?->recalculate());
        static::deleted(fn (CommercialDocumentItem $item) => $item->document?->recalculate());
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CommercialDocument::class, 'commercial_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
