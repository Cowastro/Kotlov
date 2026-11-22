<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Services\Suppliers\TdKomplektFeed;
use App\Services\Suppliers\TdKomplektReviewedSpecs;

/** Supplier facts for display, never a substitute for verified manufacturer selection data. */
class TdPumpSpecifications
{
    public const CATEGORIES = [...PumpCatalog::MANUFACTURER_CATEGORIES, 'tsirkulyatsionnyie', 'mnogostupenchatye-nasosy',
        'nasosy-dlya-kolodtsa', 'fekalnye-nasosy', 'kanalizatsionnye-nasosy', 'nasosy-povysheniya-davleniya'];

    public static function sourceRow(Product $product): ?array
    {
        if (! in_array($product->category?->slug, self::CATEGORIES, true)) {
            return null;
        }

        $offers = $product->supplierProducts()
            ->where('match_status', 'matched')
            ->whereHas('supplier', fn ($query) => $query->where('code', 'td_komplekt'))->get();
        if ($offers->count() !== 1) {
            return null;
        }
        $offer = $offers->first();
        $row = $offer->raw ?? [];
        $review = app(TdKomplektReviewedSpecs::class)->forRow($row);
        $reviewed = ($review['name'] ?? null) === $product->name;
        if (empty($row['vendor_code']) || empty($row['brand']) || ! empty($row['identity_review'])
            || (! $reviewed && (($row['name'] ?? '') !== $product->name || (int) $product->supplier_id !== (int) $offer->supplier_id))
            || $offer->supplier_article !== $row['vendor_code']
            || ($row['identity_vendor_code'] ?? $row['vendor_code']) !== $row['vendor_code']
            || ($row['identity_brand'] ?? $row['brand']) !== $row['brand']) {
            return null;
        }

        if ($reviewed) {
            $row['_reviewed_facts'] = $review['facts'];
        }

        return $row;
    }

    public static function details(Product $product, ?array $row = null): array
    {
        $row ??= self::sourceRow($product);
        if ($row === null) {
            return [];
        }

        $details = [];
        // Stop before model-decoding examples, accessories and other series' parameters.
        $description = preg_split('/^\s*(?:Расшифровка модели|Пример\s*:)/miu', $row['description'] ?? '')[0];
        $facts = $row['_reviewed_facts'] ?? app(TdKomplektFeed::class)->specs(array_replace($row, ['description' => $description, 'name' => '']), 4);
        foreach ($facts as $fact) {
            $key = preg_replace('/\s+/u', ' ', preg_replace('/^[\s*•]+/u', '', (string) $fact['key']));
            $value = trim((string) $fact['value']);
            $baseKey = preg_replace('/,\s*(?:Вт|кВт|м|мм|л|л\/мин|лмин|м3\/ч|м3\/час|бар|В|А|Гц|кг|С|°С|шт)\.?$/ui', '', $key);
            // Source rows also contain marketing and decoding examples. Keep technical labels only.
            if (! preg_match('/^(?:Тип насоса$|Конструкция$|Исполнение рабочего колеса$|Мощность|Потребляем|Номинальн|Максимальн|Минимальн|Макс[.]|Мин[.]|Напор|Расход|Производительность|Сила тока|Емкость конд|Параметры (?:сети|электросети)|Электропитание|Напряжение|Вольтаж|Частота|Монтажная длина|Подключение|Диаметр|Длина|Типоразмер электрического кабеля|Количество (?:рабочих колес|режимов работы)|Температура|Класс (?:защиты|изоляции)|Степень защиты|Материал|Масса|Вес|Габариты насоса|Гарантийный срок|Объем (?:накопительного бака|бака)|Гидроаккумулятор|Забор воды|Встроенный измельчитель|Защита от перегрева|Датчик сухого хода|Допустимый pH воды)/ui', trim($baseKey))
                || $value === '' || mb_strlen($value) > 100 || str_contains($value, ':') || str_contains($value, '=')) {
                continue;
            }
            // Keep multi-speed values and their units together; never choose one speed as a maximum.
            if ($baseKey !== $key && preg_match('/^[+-]?\d[\d ]*(?:[,.]\d+)?$/u', $value)) {
                $value .= ' '.trim(substr($key, strlen($baseKey) + 1));
                $key = $baseKey;
            }
            $details[$key] = $value;
        }
        if ($details !== []) {
            $details = ['Артикул поставщика' => (string) $row['vendor_code']] + $details;
        }

        return $details;
    }
}
