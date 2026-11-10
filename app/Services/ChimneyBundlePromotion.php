<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Collection;

class ChimneyBundlePromotion
{
    /**
     * @return array{active: bool, mode: ?string, percent: float, equipment_product_ids: array<int>, chimney_product_ids: array<int>, self_selected_product_ids: array<int>, eligible_subtotal: float, discount: float, line_discounts: array<int, float>}
     */
    public function quote(array $items): array
    {
        $empty = $this->emptyQuote();
        if (! config('shop.chimney_bundle_discount.enabled', true) || $items === []) {
            return $empty;
        }

        $products = Product::query()
            ->whereIn('id', collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all())
            ->with('category')
            ->get()
            ->keyBy('id');
        $categoryParents = Category::query()->pluck('parent_id', 'id');
        $categorySlugs = Category::query()->pluck('slug', 'id');

        $equipmentIds = [];
        $chimneyIds = [];
        $selfSelectedIds = [];
        foreach ($items as $item) {
            if (($item['pricing_type'] ?? 'retail') === 'b2b') {
                continue;
            }

            $product = $products->get((int) ($item['id'] ?? 0));
            $slugs = $product
                ? $this->categoryPathSlugs((int) $product->category_id, $categoryParents, $categorySlugs)
                : array_filter([(string) ($item['category_slug'] ?? '')]);

            if ($this->slugsAreEligibleEquipment($slugs)) {
                $equipmentIds[] = (int) $item['id'];
            }
            if ($this->slugsAreChimney($slugs) && ! $this->hasActiveSale($product)) {
                $chimneyIds[] = (int) $item['id'];
                if (($item['chimney_selector_selected'] ?? false) === true) {
                    $selfSelectedIds[] = (int) $item['id'];
                }
            }
        }

        $equipmentIds = array_values(array_unique($equipmentIds));
        $chimneyIds = array_values(array_unique($chimneyIds));
        $selfSelectedIds = array_values(array_unique($selfSelectedIds));
        $equipmentBundle = $equipmentIds !== [] && $chimneyIds !== [];
        $eligibleChimneyIds = $equipmentBundle ? $chimneyIds : $selfSelectedIds;
        if ($eligibleChimneyIds === []) {
            return array_merge($empty, [
                'equipment_product_ids' => $equipmentIds,
                'chimney_product_ids' => $chimneyIds,
                'self_selected_product_ids' => $selfSelectedIds,
            ]);
        }

        $percent = $equipmentBundle
            ? (float) config('shop.chimney_bundle_discount.percent', 10)
            : (float) config('shop.chimney_bundle_discount.self_selection_percent', 5);
        $lineDiscounts = [];
        $eligibleSubtotal = 0.0;
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! in_array($id, $eligibleChimneyIds, true)) {
                continue;
            }
            $lineSubtotal = round((float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0), 2);
            $eligibleSubtotal += $lineSubtotal;
            $lineDiscounts[$id] = round($lineSubtotal * $percent / 100, 2);
        }

        return [
            'active' => true,
            'mode' => $equipmentBundle ? 'equipment_bundle' : 'self_selection',
            'percent' => $percent,
            'equipment_product_ids' => $equipmentIds,
            'chimney_product_ids' => $chimneyIds,
            'self_selected_product_ids' => $selfSelectedIds,
            'eligible_subtotal' => round($eligibleSubtotal, 2),
            'discount' => round(array_sum($lineDiscounts), 2),
            'line_discounts' => $lineDiscounts,
        ];
    }

    public const SELECTOR_INLETS = ['100', '110', '115', '120', '130', '150', '160', '180', '200'];

    public function equipmentOutletDiameter(Product $product): ?string
    {
        $official = app(StoveOfficialTechnicalSpecCatalog::class)->entries()[$product->slug]['specs'] ?? [];
        $diameters = $this->outletDiameters($official);
        if ($diameters === []) {
            $product->loadMissing(['allAttributeValues.attribute', 'allAttributeValues.option']);
            $attributes = $product->allAttributeValues
                ->filter(fn ($attribute) => $attribute->hasDisplayValue())
                ->map(fn ($attribute) => [
                    'key' => $attribute->attribute->name,
                    'value' => $attribute->option?->name ?? $attribute->value,
                ])
                ->all();
            $rawSpecs = $product->specs;
            $specs = is_string($rawSpecs) ? json_decode($rawSpecs, true) : $rawSpecs;
            $diameters = $this->outletDiameters(array_merge((array) $specs, $attributes));
        }

        return count($diameters) === 1 ? $diameters[0] : null;
    }

    private function outletDiameters(array $rows): array
    {
        $diameters = [];
        foreach ($rows as $key => $row) {
            $label = is_array($row) ? ($row['key'] ?? $row['name'] ?? '') : $key;
            $value = is_array($row) ? ($row['value'] ?? '') : $row;
            $label = trim(mb_strtolower((string) $label));
            $label = rtrim(preg_replace('/(?:,?\s*(?:мм|mm)|\s*\((?:мм|mm)\))$/u', '', $label) ?? $label);
            if (! in_array($label, [
                'диаметр дымохода', 'диаметр выхода дымохода', 'диаметр патрубка дымохода',
                'диаметр дымового патрубка', 'диаметр дымоходного патрубка', 'диаметр выходного патрубка',
            ], true)) {
                continue;
            }

            if (is_scalar($value) && preg_match('/^\s*(?:[Øø⌀Фф]\s*)?(\d{2,3})(?:[,.]0+)?\s*(?:мм|mm)?\s*$/u', (string) $value, $match)) {
                $diameters[] = $match[1];
            }
        }

        return array_values(array_unique($diameters));
    }

    public function productIsEligibleEquipment(Product $product): bool
    {
        return $this->slugsAreEligibleEquipment($this->productCategorySlugs($product));
    }

    public function productIsChimney(Product $product): bool
    {
        return $this->slugsAreChimney($this->productCategorySlugs($product));
    }

    /** @return Collection<int, Product> */
    public function recommendedChimneys(int $limit = 8): Collection
    {
        $categoryIds = Category::query()
            ->whereIn('slug', (array) config('shop.chimney_bundle_discount.chimney_category_slugs', []))
            ->pluck('id');

        $preferredBrandId = Brand::query()
            ->where('slug', config('shop.chimney_bundle_discount.preferred_brand_slug'))
            ->value('id');

        return Product::query()
            ->whereIn('category_id', $categoryIds)
            ->orderable()
            ->withSupplierChannels()
            ->with(['category', 'brand'])
            ->when($preferredBrandId, fn ($query) => $query->orderByRaw(
                'CASE WHEN brand_id = ? THEN 0 ELSE 1 END',
                [$preferredBrandId]
            ))
            ->orderByDesc('in_stock')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    private function productCategorySlugs(Product $product): array
    {
        $parents = Category::query()->pluck('parent_id', 'id');
        $slugs = Category::query()->pluck('slug', 'id');

        return $this->categoryPathSlugs((int) $product->category_id, $parents, $slugs);
    }

    private function categoryPathSlugs(int $categoryId, Collection $parents, Collection $slugs): array
    {
        $path = [];
        $seen = [];
        while ($categoryId > 0 && ! isset($seen[$categoryId])) {
            $seen[$categoryId] = true;
            if ($slug = $slugs->get($categoryId)) {
                $path[] = (string) $slug;
            }
            $categoryId = (int) ($parents->get($categoryId) ?? 0);
        }

        return $path;
    }

    private function slugsAreEligibleEquipment(array $slugs): bool
    {
        $excluded = (array) config('shop.chimney_bundle_discount.excluded_equipment_category_slugs', []);
        if (array_intersect($slugs, $excluded) !== []) {
            return false;
        }

        return array_intersect($slugs, (array) config('shop.chimney_bundle_discount.equipment_category_slugs', [])) !== [];
    }

    private function slugsAreChimney(array $slugs): bool
    {
        return array_intersect($slugs, (array) config('shop.chimney_bundle_discount.chimney_category_slugs', [])) !== [];
    }

    private function hasActiveSale(?Product $product): bool
    {
        return $product !== null
            && $product->is_sale
            && $product->sale_campaign !== null
            && $product->sale_ends_at?->isFuture();
    }

    private function emptyQuote(): array
    {
        return [
            'active' => false,
            'mode' => null,
            'percent' => (float) config('shop.chimney_bundle_discount.percent', 10),
            'equipment_product_ids' => [],
            'chimney_product_ids' => [],
            'self_selected_product_ids' => [],
            'eligible_subtotal' => 0.0,
            'discount' => 0.0,
            'line_discounts' => [],
        ];
    }
}
