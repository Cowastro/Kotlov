<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class IntegrationSourcePricingChange extends Model
{
    public const FIELD_LABELS = [
        'update_prices' => 'Обновление цен из источника',
        'price_currency' => 'Валюта входной цены',
        'price_currency_rate' => 'Курс к BYN',
        'settings.price_tax_mode' => 'Режим НДС',
        'settings.vat_rate' => 'Ставка НДС',
        'settings.b2b_enabled' => 'Партнёрские цены',
        'settings.b2b_category_ids' => 'Разрешённые категории B2B',
        'settings.partner_name' => 'Название поставщика для партнёра',
    ];

    protected $fillable = [
        'integration_source_id', 'user_id', 'actor_name', 'changed_fields',
        'before_values', 'after_values', 'changed_at',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'before_values' => 'array',
        'after_values' => 'array',
        'changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Запись журнала правил цен нельзя изменять.'));
        static::deleting(fn (): never => throw new LogicException('Запись журнала правил цен нельзя удалять.'));
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changeSummary(): string
    {
        return collect($this->changed_fields ?? [])
            ->map(fn (string $field): string => self::FIELD_LABELS[$field] ?? $field)
            ->join(', ');
    }

    public function valuesSummary(string $attribute): string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values) || $values === []) {
            return '—';
        }

        return collect($values)
            ->map(fn (mixed $value, string $field): string => $this->formatValue($field, $value))
            ->join(' · ');
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($field === 'settings.b2b_category_ids') {
            return is_array($value) && $value !== []
                ? 'ID категорий: '.implode(', ', $value)
                : 'ничего не публиковать';
        }

        return match ($field) {
            'update_prices', 'settings.b2b_enabled' => $value ? 'включено' : 'выключено',
            'settings.price_tax_mode' => $value === IntegrationSource::PRICE_TAX_INCLUSIVE
                ? 'цена с НДС'
                : 'цена без НДС',
            'settings.vat_rate' => number_format((float) $value, 2, ',', ' ').'%',
            'price_currency_rate' => rtrim(rtrim(number_format((float) $value, 6, ',', ' '), '0'), ','),
            default => filled($value) ? (string) $value : 'не задано',
        };
    }
}
