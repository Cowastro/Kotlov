<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

final class HeaterEquipment
{
    public const PARTS_CATEGORY = 'komplektuyushchie-dlya-obogrevateley';

    public static function records(): array
    {
        static $records;

        return $records ??= json_decode(file_get_contents(resource_path('data/heater-equipment.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function details(Product $product): ?array
    {
        if (! isset(HeaterCatalog::NAMES[$product->category?->slug])) {
            return null;
        }

        return self::records()['products'][$product->slug] ?? ['included' => [], 'separate' => [], 'sources' => []];
    }

    /** Exact, reviewed relationships only; a shared brand never establishes compatibility. */
    public static function relatedGroups(Product $product, ?array $details): Collection
    {
        if ($details === null) {
            return collect();
        }

        $parts = self::records()['parts'];
        $ids = collect($details['separate'])->pluck('parts')->flatten()->unique();
        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()->orderable()
            ->whereHas('category', fn ($query) => $query->where('slug', self::PARTS_CATEGORY)->where('is_active', true))
            ->whereIn('slug', $ids->map(fn (string $id) => $parts[$id]['slug'])->all())
            ->with(['category', 'brand'])->withSupplierChannels()
            ->get()->keyBy('slug');

        return collect($details['separate'])->map(function (array $group) use ($parts, $products) {
            $group['products'] = collect($group['parts'])->map(function (string $id) use ($parts, $products) {
                $part = $parts[$id];

                return isset($products[$part['slug']]) ? ['product' => $products[$part['slug']], 'source' => $part['source']] : null;
            })->filter()->values();

            return $group;
        })->filter(fn (array $group) => $group['products']->isNotEmpty())->values();
    }
}
