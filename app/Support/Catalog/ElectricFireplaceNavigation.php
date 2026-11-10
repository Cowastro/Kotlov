<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

final class ElectricFireplaceNavigation
{
    public const ENTRIES = [
        'insert' => ['category' => 'elektrokamini', 'label' => 'Электроочаги'],
        'kit' => ['category' => 'elektrokamini', 'label' => 'Каминокомплекты'],
        'portal' => ['category' => 'oblicovki', 'label' => 'Порталы'],
    ];

    public static function selection(string $category, mixed $requested): ?string
    {
        return is_string($requested) && (self::ENTRIES[$requested]['category'] ?? null) === $category
            ? $requested
            : null;
    }

    public static function productIds(Collection $categoryIds, string $type): array
    {
        return Product::query()->orderable()
            ->whereIn('category_id', $categoryIds)
            ->select('id', 'slug', 'name', 'specs')
            ->when($type !== 'portal', fn ($query) => $query->with('attributeValues'))
            ->lazyById(100)
            ->filter(fn (Product $product) => $type === 'portal'
                ? self::isPortal($product)
                : FireplaceSpecifications::electricType($product) === $type)
            ->pluck('id')
            ->all();
    }

    /** @return Collection<int, Product> */
    public static function compatiblePortals(Product $insert): Collection
    {
        $slugs = array_keys(array_filter(
            config('electric-fireplaces.compatibility', []),
            fn (array $entry) => in_array($insert->slug, $entry['inserts'], true)
        ));
        return self::listedProducts($slugs, 'oblicovki')
            ->filter(fn (Product $portal) => self::isPortal($portal))
            ->values();
    }

    /** @return Collection<int, Product> */
    public static function compatibleInserts(Product $portal): Collection
    {
        $slugs = self::isPortal($portal)
            ? config('electric-fireplaces.compatibility.'.$portal->slug.'.inserts', [])
            : [];

        return self::listedProducts($slugs, 'elektrokamini');
    }

    private static function listedProducts(array $slugs, string $category): Collection
    {
        if ($slugs === []) {
            return collect();
        }

        return Product::query()->orderable()
            ->whereHas('category', fn ($query) => $query->where('slug', $category)->where('is_active', true))
            ->whereIn('slug', $slugs)
            ->with(['category', 'brand'])
            ->withSupplierChannels()
            ->orderByDesc('in_stock')
            ->orderBy('name')
            ->get();
    }

    private static function isPortal(Product $product): bool
    {
        return preg_match('/^\s*портал(?:\s|$)/iu', $product->name) === 1;
    }
}
