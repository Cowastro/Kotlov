<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $fillable = [
        'product_id', 'attribute_id', 'option_id', 'is_checked', 'value',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class);
    }

    public function hasDisplayValue(): bool
    {
        return match ($this->attribute?->type) {
            'select' => ! empty($this->option?->name),
            'check' => $this->is_checked !== null,
            default => ! empty($this->value)
                && $this->value !== '—'
                && ! in_array((string) $this->value, ['0', '0.0', '0.00'], true),
        };
    }

    // Получить отображаемое значение
    public function getDisplayValueAttribute(): string
    {
        return match ($this->attribute->type) {
            'select' => $this->option?->name ?? '—',
            'check' => $this->is_checked ? 'Да' : 'Нет',
            default => ($this->value ?? '—').($this->attribute->suffix ? ' '.$this->attribute->suffix : ''),
        };
    }
}
