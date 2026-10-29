<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bSpecificationItem extends Model
{
    public const STATUSES = [
        'unmatched' => 'Не найдено',
        'matched' => 'Сопоставлено',
        'ignored' => 'Не учитывать',
    ];

    protected $fillable = [
        'b2b_specification_id', 'b2b_specification_attachment_id', 'source_row',
        'source_sku', 'source_name', 'quantity', 'unit', 'status', 'product_id',
        'confidence', 'match_reason', 'raw_data',
    ];

    protected $casts = [
        'source_row' => 'integer',
        'quantity' => 'decimal:3',
        'confidence' => 'decimal:2',
        'raw_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (B2bSpecificationItem $item): void {
            if ($item->product_id) {
                $item->status = 'matched';
                $item->match_reason ??= 'manager';
                $item->confidence ??= 100;
            } elseif ($item->status === 'matched') {
                $item->status = 'unmatched';
                $item->confidence = null;
                $item->match_reason = null;
            }
        });
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(B2bSpecification::class, 'b2b_specification_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(B2bSpecificationAttachment::class, 'b2b_specification_attachment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
