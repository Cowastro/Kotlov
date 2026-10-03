<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORY_SLUG = 'teplovyie-nasosyi';

    private const SELECT_ATTRIBUTES = [
        'Хладагент' => [
            'sort_order' => -9,
            'options' => ['R32', 'R290'],
        ],
        'Электропитание' => [
            'sort_order' => -8,
            'options' => ['220 В', '380 В'],
        ],
        'Максимальная температура подачи' => [
            'sort_order' => -7,
            'options' => ['До 55 °C', 'До 60 °C', 'До 65 °C', 'До 70 °C', '75 °C и выше'],
        ],
    ];

    private const VALUE_ATTRIBUTE_FLAGS = [
        'Мощность теплового насоса' => ['in_filter' => false, 'in_product' => true, 'is_comparable' => true],
        'Потребляемая мощность' => ['in_filter' => false, 'in_product' => true, 'is_comparable' => true],
        'Максимальная температура в контуре отопления' => ['in_filter' => false, 'in_product' => true, 'is_comparable' => true],
        'Уровень шума' => ['in_filter' => false, 'in_product' => true, 'is_comparable' => true],
        'Размеры' => ['in_filter' => false, 'in_product' => true, 'is_comparable' => true],
        'Вес' => ['in_filter' => false, 'in_product' => true, 'is_comparable' => true],
    ];

    private const POWER_RANGES = [
        'до 5 кВт' => [0, 5],
        '6–10 кВт' => [5.0001, 10],
        '11–15 кВт' => [10.0001, 15],
        '16–20 кВт' => [15.0001, 20],
        '21–25 кВт' => [20.0001, 25],
        '26–30 кВт' => [25.0001, 30],
    ];

    public function up(): void
    {
        $categoryId = (int) DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $categoryId) {
            return;
        }

        $now = now();
        $attributeOptions = [];

        foreach (self::SELECT_ATTRIBUTES as $name => $definition) {
            DB::table('attributes')->updateOrInsert(
                ['category_id' => $categoryId, 'name' => $name, 'type' => 'select'],
                [
                    'group_id' => 0,
                    'sort_order' => $definition['sort_order'],
                    'suffix' => null,
                    'in_filter' => true,
                    'in_sort' => false,
                    'in_product' => true,
                    'in_brief' => in_array($name, ['Хладагент', 'Электропитание'], true),
                    'is_comparable' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $attributeId = (int) DB::table('attributes')
                ->where('category_id', $categoryId)
                ->where('name', $name)
                ->where('type', 'select')
                ->value('id');

            $attributeOptions[$name] = ['attribute_id' => $attributeId, 'options' => []];

            foreach ($definition['options'] as $index => $optionName) {
                DB::table('attribute_options')->updateOrInsert(
                    ['attribute_id' => $attributeId, 'name' => $optionName],
                    ['sort_order' => ($index + 1) * 10, 'created_at' => $now, 'updated_at' => $now],
                );

                $attributeOptions[$name]['options'][$optionName] = (int) DB::table('attribute_options')
                    ->where('attribute_id', $attributeId)
                    ->where('name', $optionName)
                    ->value('id');
            }
        }

        foreach (self::VALUE_ATTRIBUTE_FLAGS as $name => $flags) {
            DB::table('attributes')
                ->where('category_id', $categoryId)
                ->where('name', $name)
                ->update(array_merge($flags, ['updated_at' => $now]));
        }

        // Диапазон мощности нужен только в каталоге и краткой карточке.
        DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', 'Мощность')
            ->where('type', 'select')
            ->update(['in_product' => false, 'in_brief' => true, 'updated_at' => $now]);

        $products = DB::table('products')
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->get(['id', 'name', 'specs']);

        foreach ($products as $product) {
            $specs = $this->normalizeSpecs($product->specs);
            $name = (string) $product->name;

            $refrigerant = $this->refrigerant($specs, $name);
            $powerSupply = $this->powerSupply($specs);
            $maxFlowTemperature = $this->maxFlowTemperature($specs);

            $this->storeSelectValue($product->id, $attributeOptions, 'Хладагент', $refrigerant, $now);
            $this->storeSelectValue($product->id, $attributeOptions, 'Электропитание', $powerSupply, $now);
            $this->storeSelectValue($product->id, $attributeOptions, 'Максимальная температура подачи', $this->temperatureRange($maxFlowTemperature), $now);

            $this->storePowerRange($categoryId, (int) $product->id, $specs, $name, $now);
        }
    }

    public function down(): void
    {
        $categoryId = (int) DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $categoryId) {
            return;
        }

        $attributeIds = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->whereIn('name', array_keys(self::SELECT_ATTRIBUTES))
            ->where('type', 'select')
            ->pluck('id');

        DB::table('product_attribute_values')->whereIn('attribute_id', $attributeIds)->delete();
        DB::table('attribute_options')->whereIn('attribute_id', $attributeIds)->delete();
        DB::table('attributes')->whereIn('id', $attributeIds)->delete();
    }

    private function normalizeSpecs(mixed $rawSpecs): array
    {
        $decoded = is_array($rawSpecs) ? $rawSpecs : json_decode((string) $rawSpecs, true);

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $key => $spec) {
            if (is_array($spec)) {
                $label = $spec['key'] ?? $spec['name'] ?? $spec['title'] ?? null;
                $value = $spec['value'] ?? $spec['val'] ?? null;
            } else {
                $label = is_string($key) ? $key : null;
                $value = $spec;
            }

            if (! is_scalar($label) || ! is_scalar($value)) {
                continue;
            }

            $normalized[mb_strtolower(trim((string) $label))] = trim((string) $value);
        }

        return $normalized;
    }

    private function refrigerant(array $specs, string $name): ?string
    {
        $source = ($specs['хладагент'] ?? '').' '.$name;

        if (preg_match('/\bR290\b/iu', $source)) {
            return 'R290';
        }

        return preg_match('/\bR32\b/iu', $source) ? 'R32' : null;
    }

    private function powerSupply(array $specs): ?string
    {
        $source = $specs['питание'] ?? $specs['электропитание'] ?? $specs['напряжение'] ?? '';

        if (preg_match('/\b380\s*В?\b/iu', $source)) {
            return '380 В';
        }

        return preg_match('/\b220\s*В?\b/iu', $source) ? '220 В' : null;
    }

    private function maxFlowTemperature(array $specs): ?float
    {
        $source = $specs['температура воды']
            ?? $specs['максимальная температура в контуре отопления']
            ?? $specs['максимальная температура подачи']
            ?? '';

        if (preg_match('/отоплен[^\d]*(\d+(?:[,.]\d+)?)/iu', $source, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        if (preg_match_all('/\d+(?:[,.]\d+)?/u', $source, $matches) && ! empty($matches[0])) {
            return (float) str_replace(',', '.', end($matches[0]));
        }

        return null;
    }

    private function temperatureRange(?float $temperature): ?string
    {
        if ($temperature === null) {
            return null;
        }

        return match (true) {
            $temperature <= 55 => 'До 55 °C',
            $temperature <= 60 => 'До 60 °C',
            $temperature <= 65 => 'До 65 °C',
            $temperature <= 70 => 'До 70 °C',
            default => '75 °C и выше',
        };
    }

    private function storeSelectValue(int $productId, array $attributeOptions, string $attributeName, ?string $optionName, mixed $now): void
    {
        if (! $optionName || empty($attributeOptions[$attributeName]['options'][$optionName])) {
            return;
        }

        DB::table('product_attribute_values')->updateOrInsert(
            [
                'product_id' => $productId,
                'attribute_id' => $attributeOptions[$attributeName]['attribute_id'],
            ],
            [
                'option_id' => $attributeOptions[$attributeName]['options'][$optionName],
                'value' => null,
                'is_checked' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function storePowerRange(int $categoryId, int $productId, array $specs, string $name, mixed $now): void
    {
        $attributeId = (int) DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', 'Мощность')
            ->where('type', 'select')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        $source = $specs['мощность'] ?? $specs['мощность теплового насоса'] ?? $name;

        if (! preg_match_all('/\d+(?:[,.]\d+)?/u', str_replace(',', '.', $source), $matches) || empty($matches[0])) {
            return;
        }

        $power = max(array_map('floatval', $matches[0]));
        $rangeName = null;

        foreach (self::POWER_RANGES as $label => [$min, $max]) {
            if ($power >= $min && $power <= $max) {
                $rangeName = $label;
                break;
            }
        }

        if (! $rangeName) {
            return;
        }

        $optionId = (int) DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->where('name', $rangeName)
            ->value('id');

        if (! $optionId) {
            return;
        }

        DB::table('product_attribute_values')->updateOrInsert(
            ['product_id' => $productId, 'attribute_id' => $attributeId],
            ['option_id' => $optionId, 'value' => null, 'is_checked' => null, 'created_at' => $now, 'updated_at' => $now],
        );
    }
};
