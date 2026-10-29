<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bSpecificationItem extends Model
{
    public const STATUSES = [
        'unmatched' => 'Не найдено',
        'suggested' => 'Есть варианты',
        'matched' => 'Сопоставлено',
        'ignored' => 'Не учитывать',
    ];

    protected $fillable = [
        'b2b_specification_id', 'b2b_specification_version_id', 'is_current', 'b2b_specification_attachment_id', 'source_row',
        'source_sku', 'source_name', 'quantity', 'unit', 'status', 'product_id',
        'confidence', 'match_reason', 'candidates', 'reviewed_by', 'reviewed_at', 'raw_data',
    ];

    protected $casts = [
        'source_row' => 'integer',
        'quantity' => 'decimal:3',
        'confidence' => 'decimal:2',
        'candidates' => 'array',
        'reviewed_at' => 'datetime',
        'raw_data' => 'array',
        'is_current' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (B2bSpecificationItem $item): void {
            if ($item->product_id) {
                $item->status = 'matched';
                if ($item->isDirty('product_id') && ! $item->isDirty('match_reason')) {
                    $item->match_reason = 'manager';
                    $item->confidence = 100;
                } else {
                    $item->match_reason ??= 'manager';
                    $item->confidence ??= 100;
                }
            } elseif ($item->status === 'matched') {
                $item->status = filled($item->candidates) ? 'suggested' : 'unmatched';
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

    public function version(): BelongsTo
    {
        return $this->belongsTo(B2bSpecificationVersion::class, 'b2b_specification_version_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function topCandidate(): ?array
    {
        $candidate = collect($this->candidates)->sortByDesc('score')->first();

        return is_array($candidate) && filled($candidate['product_id'] ?? null) ? $candidate : null;
    }
}
