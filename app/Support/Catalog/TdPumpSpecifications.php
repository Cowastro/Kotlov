<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Services\Suppliers\TdKomplektFeed;

/** Supplier facts for display, never a substitute for verified manufacturer selection data. */
class TdPumpSpecifications
{
    public static function details(Product $product): array
    {
        if (! in_array($product->category?->slug, PumpCatalog::MANUFACTURER_CATEGORIES, true)) {
            return [];
        }

        $offers = $product->supplierProducts()->where('supplier_id', $product->supplier_id)
            ->where('match_status', 'matched')
            ->whereHas('supplier', fn ($query) => $query->where('code', 'td_komplekt'))->get();
        if ($offers->count() !== 1) {
            return [];
        }
        $offer = $offers->first();
        $row = $offer->raw ?? [];
        if (empty($row['vendor_code']) || empty($row['brand']) || ! empty($row['identity_review'])
            || ($row['name'] ?? '') !== $product->name
            || $offer->supplier_article !== $row['vendor_code']
            || ($row['identity_vendor_code'] ?? $row['vendor_code']) !== $row['vendor_code']
            || ($row['identity_brand'] ?? $row['brand']) !== $row['brand']) {
            return [];
        }

        $details = [];
        // Stop before model-decoding examples, accessories and other series' parameters.
        $description = preg_split('/^\s*Расшифровка модели/miu', $row['description'] ?? '')[0];
        $facts = app(TdKomplektFeed::class)->specs(array_replace($row, ['description' => $description, 'name' => '']), 4);
        foreach ($facts as $fact) {
            $key = trim((string) $fact['key']);
            $value = trim((string) $fact['value']);
            if (! preg_match('/^(?:Тип насоса|Мощность двигателя|Потребляемая мощность|Сила тока|Емкость кондексатора|Емкость конденсатора|Параметры электросети|Параметры сети|Макс\. высота напора|Максимальный напор|Макс\. глубина погружения|Максимальная глубина погружения|Макс\. производительность|Производительность|Макс\. давление|Количество рабочих кол[её]с|Диаметр насоса|Длина насоса|Длина электрического кабеля|Типоразмер электрического кабеля, NxS|Максимальный размер включений|Максимальный размер (?:тверды[хx]|неабразивны[хx]) частиц|Максимальная температура перекачиваемой воды|Диаметр шланга|Масса нетто|Габариты насоса|Гарантийный срок)$/ui', $key)
                || $value === '' || mb_strlen($value) > 100 || str_contains($value, ':')) {
                continue;
            }
            $details[$key] = $value;
        }
        if ($details !== []) {
            $details = ['Артикул поставщика' => (string) $row['vendor_code']] + $details;
        }

        return $details;
    }
}
