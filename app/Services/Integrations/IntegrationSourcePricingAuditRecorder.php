<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\IntegrationSourcePricingChange;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class IntegrationSourcePricingAuditRecorder
{
    /** @var list<string> */
    private const TRACKED_FIELDS = [
        'update_prices',
        'settings.price_tax_mode',
        'settings.vat_rate',
        'settings.b2b_enabled',
        'settings.partner_name',
    ];

    public function recordUpdatedSource(IntegrationSource $source): ?IntegrationSourcePricingChange
    {
        if (! Schema::hasTable('integration_source_pricing_changes')) {
            return null;
        }

        $before = $this->snapshot($source, original: true);
        $after = $this->snapshot($source, original: false);
        $changedFields = array_values(array_filter(
            self::TRACKED_FIELDS,
            fn (string $field): bool => $before[$field] !== $after[$field],
        ));

        if ($changedFields === []) {
            return null;
        }

        $actor = auth()->user();

        return $source->pricingChanges()->create([
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'changed_fields' => $changedFields,
            'before_values' => Arr::only($before, $changedFields),
            'after_values' => Arr::only($after, $changedFields),
            'changed_at' => now(),
        ]);
    }

    /** @return array<string, bool|float|string|null> */
    private function snapshot(IntegrationSource $source, bool $original): array
    {
        $settings = $original
            ? $this->decodeSettings($source->getRawOriginal('settings'))
            : ($source->settings ?? []);

        return [
            'update_prices' => (bool) ($original
                ? $source->getRawOriginal('update_prices')
                : $source->update_prices),
            'settings.price_tax_mode' => data_get($settings, 'price_tax_mode') === IntegrationSource::PRICE_TAX_INCLUSIVE
                ? IntegrationSource::PRICE_TAX_INCLUSIVE
                : IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'settings.vat_rate' => round(max(0, (float) data_get($settings, 'vat_rate', 20)), 4),
            'settings.b2b_enabled' => (bool) data_get($settings, 'b2b_enabled', false),
            'settings.partner_name' => filled(data_get($settings, 'partner_name'))
                ? trim((string) data_get($settings, 'partner_name'))
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeSettings(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (! is_string($settings) || $settings === '') {
            return [];
        }

        $decoded = json_decode($settings, true);

        return is_array($decoded) ? $decoded : [];
    }
}
