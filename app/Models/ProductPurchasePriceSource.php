<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchasePriceSource extends Model
{
    public const TYPE_SUPPLIER_INVOICE = 'supplier_invoice';

    public const TYPE_SUPPLIER_PRICE_DOCUMENT = 'supplier_price_document';

    protected $fillable = [
        'product_id', 'supplier_id', 'created_by', 'source_type', 'supplier_name',
        'document_number', 'document_date', 'valid_until', 'source_price', 'currency',
        'currency_rate', 'price_tax_mode', 'vat_rate', 'price_byn', 'attachment_path',
        'notes', 'is_active', 'confirmed_at',
    ];

    protected $casts = [
        'document_date' => 'date',
        'valid_until' => 'date',
        'source_price' => 'decimal:2',
        'currency_rate' => 'decimal:6',
        'vat_rate' => 'decimal:2',
        'price_byn' => 'decimal:2',
        'is_active' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUsable(Builder $query, ?CarbonInterface $on = null): Builder
    {
        $date = ($on ?? now())->toDateString();

        return $query
            ->where('is_active', true)
            ->where('price_byn', '>', 0)
            ->where(fn (Builder $validity): Builder => $validity
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $date));
    }

    public function isUsable(?CarbonInterface $on = null): bool
    {
        return $this->is_active
            && (float) $this->price_byn > 0
            && ($this->valid_until === null || $this->valid_until->gte($on ?? today()));
    }

    public function supplierDisplayName(): ?string
    {
        return $this->supplier?->name ?: $this->supplier_name;
    }

    public function documentLabel(): string
    {
        $number = filled($this->document_number) ? ' №'.$this->document_number : '';
        $date = $this->document_date ? ' от '.$this->document_date->format('d.m.Y') : '';

        $label = $this->source_type === self::TYPE_SUPPLIER_INVOICE ? 'Счёт поставщика' : 'Документ поставщика';

        return $label.$number.$date;
    }
}
