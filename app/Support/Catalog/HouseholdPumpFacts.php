<?php

namespace App\Support\Catalog;

use App\Models\Product;

/** Manufacturer evidence for exact catalog models; legacy import values are not a fallback. */
class HouseholdPumpFacts
{
    public static function records(): array
    {
        static $records;
        return $records ??= json_decode(file_get_contents(resource_path('data/pump-manufacturer-facts.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function verified(Product $product, string $slug): ?array
    {
        $records = self::records();
        $binding = $records['products'][(string) $product->id] ?? null;
        // An importer may rename, repurpose or move a product. Fail closed rather
        // than attaching a passport to a different model or configuration.
        if (! $binding || $binding['name'] !== $product->name || $binding['category'] !== $slug
            || ($product->relationLoaded('category') && $product->category?->slug !== $slug)) return null;
        return $records['models'][$binding['model']] ?? null;
    }

    public static function facts(Product $product, string $slug, array $dimensions): array
    {
        $record = self::verified($product, $slug);
        if (! $record) return ['numbers' => [], 'options' => ['verified' => ['no']], 'card' => ['Данные' => 'Уточняются'], 'details' => ['Данные производителя' => 'Уточняются для этого исполнения']];
        $numbers = array_filter($record['numbers'], fn ($value) => is_numeric($value) && $value > 0);
        // A conflict invalidates that field even if an older registry value remains.
        $numbers = array_diff_key($numbers, $record['conflicts'] ?? []);
        $options = ['verified' => ['yes']];
        $card = [];
        foreach (['head' => ['Макс. напор', ' м'], 'flow' => ['Макс. расход', ' м³/ч'],
            'diameter' => ['Ø насоса', ' мм'], 'bore' => ['Скважина от', ' мм'],
            'suction' => ['Макс. всасывание', ' м'], 'immersion' => ['Макс. погружение', ' м'],
            'solids' => ['Твёрдые частицы до', ' мм'], 'tank' => ['Гидроаккумулятор', ' л'],
            'rated_head' => ['Номин. напор', ' м'], 'rated_flow' => ['Номин. расход', ' м³/ч'],
            'tabulated_head' => ['Напор по таблице', ' м'], 'tabulated_flow' => ['Расход по таблице', ' м³/ч'],
            'power_kw' => ['Мощность двигателя', ' кВт'], 'input_power_kw' => ['Потребляемая мощность', ' кВт'],
            'free_passage' => ['Свободный проход', ' мм'], 'connection_dn' => ['Подключение DN', '']] as $key => [$label, $unit]) {
            if (isset($numbers[$key])) $card[$label] = str_replace('.', ',', (string) $numbers[$key]).$unit;
        }
        if (isset($dimensions['voltage'][1][$record['voltage'] ?? ''])) {
            $options['voltage'] = [$record['voltage']];
            $card['Питание'] = $dimensions['voltage'][1][$record['voltage']];
        }
        if (isset($numbers['diameter'], $dimensions['diameter'])) {
            $options['diameter'] = [$numbers['diameter'] <= 76 ? 'to-76' : ($numbers['diameter'] <= 102 ? 'to-102' : 'over')];
        }
        foreach (['bore', 'suction', 'immersion', 'solids', 'tank'] as $field) {
            if (! isset($numbers[$field], $dimensions[$field])) continue;
            foreach ($dimensions[$field][1] as $limit => $label) {
                $matches = match ($field) {
                    'bore' => $numbers[$field] <= (float) $limit,
                    'tank' => $numbers[$field] === (int) $limit || $numbers[$field] === (float) $limit,
                    default => $numbers[$field] >= (float) $limit,
                };
                if ($matches) $options[$field][] = (string) $limit;
            }
        }
        $details = $card;
        foreach (['weight_kg' => ['Масса', ' кг'], 'cable' => ['Длина кабеля', ' м']] as $key => [$label, $unit]) {
            if (isset($numbers[$key])) $details[$label] = str_replace('.', ',', (string) $numbers[$key]).$unit;
        }
        foreach ($record['conflicts'] ?? [] as $key => $conflict) {
            $label = ['immersion' => 'Макс. погружение', 'flow' => 'Макс. расход', 'head' => 'Макс. напор'][$key] ?? $key;
            $details[$label] = 'Требует уточнения: данные документов производителя различаются';
        }
        return ['numbers' => $numbers, 'options' => $options, 'card' => $card, 'details' => $details];
    }

    public static function document(Product $product, string $slug): ?array
    {
        $record = self::verified($product, $slug);
        if (! $record) return null;
        $source = $record['source'] ?? self::records()['source'];
        return ['url' => $source['url'].'#page='.$record['pages'][0], 'title' => $source['title']];
    }
}
