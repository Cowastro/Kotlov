<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\Product;
use App\Services\B2bCatalogOfferResolver;
use App\Services\B2bSupplyOptionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class B2bMarketplaceController extends Controller
{
    public function __invoke(
        Request $request,
        B2bCatalogOfferResolver $resolver,
        B2bSupplyOptionResolver $supplyOptions,
    ) {
        $user = $request->user();
        $staffPreview = $request->boolean('b2b-preview')
            && ! $resolver->canViewPartnerPrices($user)
            && $resolver->canPreviewPartnerPrices($user);
        abort_unless($resolver->canViewPartnerPrices($user) || $staffPreview, 403);

        $mode = $request->string('mode')->toString() === 'reference' ? 'reference' : 'orderable';
        $query = mb_substr(trim($request->string('q')->toString()), 0, 100);
        $categoryId = max(0, $request->integer('category'));
        // The main search is global. A category remains selected only while the
        // user browses the tree; starting a search switches back to all goods.
        if ($query !== '') {
            $categoryId = 0;
        }
        $brandId = max(0, $request->integer('brand'));
        $sourceKey = mb_substr(trim($request->string('source')->toString()), 0, 40);
        $sort = in_array($request->string('sort')->toString(), ['name', 'price_asc', 'price_desc', 'stock'], true)
            ? $request->string('sort')->toString()
            : 'name';

        $allOffers = $staffPreview
            ? $resolver->offersForStaffPreview($user)
            : $resolver->offersForUser($user);
        $allOptions = $staffPreview
            ? $allOffers->map(function ($offer) use ($resolver): array {
                $availability = $resolver->availabilityFor($offer);

                return [
                    'key' => 'integration:'.$offer->id,
                    'product_id' => (int) $offer->product_id,
                    'product' => $offer->product,
                    'source_key' => 'integration:'.$offer->integration_source_id,
                    'source_label' => $offer->source->partnerName(),
                    'price' => $resolver->priceWithTax($offer),
                    'base_price' => $resolver->priceWithTax($offer),
                    'stock' => $availability['stock'],
                    'stock_label' => $availability['label'],
                    'delivery_label' => 'Срок уточняется',
                ];
            })
            : $supplyOptions->optionsForUser($user);
        $orderableProductIds = $allOptions->pluck('product_id')->unique()->map(fn ($id): int => (int) $id);
        $sourceGroups = $allOptions
            ->groupBy(fn (array $option): string => mb_strtolower(trim((string) $option['source_label'])))
            ->map(function (Collection $items, string $normalizedName): array {
                return [
                    'key' => substr(sha1($normalizedName), 0, 12),
                    'label' => $items->first()['source_label'],
                    'source_keys' => $items->pluck('source_key')->unique()->values(),
                ];
            })
            ->sortBy('label')
            ->values();
        $selectedSource = $sourceGroups->firstWhere('key', $sourceKey);
        if (! $selectedSource) {
            $sourceKey = '';
        }

        $listingOptions = $selectedSource
            ? $allOptions->whereIn('source_key', $selectedSource['source_keys'])
            : $allOptions;

        $categoryIndex = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'image'])
            ->keyBy('id');
        $categoryFilterIds = $this->categoryBranchIds($categoryIndex, $categoryId);

        $brands = $mode === 'reference'
            ? Brand::query()->whereHas('products', fn ($query) => $query->active()->notArchived())->orderBy('name')->get()
            : $allOptions->pluck('product.brand')->filter()->unique('id')->sortBy('name')->values();

        if ($mode === 'reference') {
            $referenceBaseQuery = Product::query()
                ->active()
                ->notArchived()
                ->whereNotIn('id', $orderableProductIds);
            $availableCategoryIds = (clone $referenceBaseQuery)->pluck('category_id');
            $productsQuery = $referenceBaseQuery->with(['category', 'brand']);

            $this->applyProductFilters($productsQuery, $query, $categoryId, $categoryFilterIds, $brandId);
            $products = $productsQuery->orderBy('name')->paginate(30)->withQueryString();
        } else {
            $availableCategoryIds = $listingOptions
                ->unique('product_id')
                ->pluck('product.category_id');
            $rows = $listingOptions->groupBy('product_id')->map(function (Collection $options): array {
                $option = $options->first();
                $product = $option['product'];

                return [
                    'product' => $product,
                    'option' => $option,
                    'options' => $options->values(),
                    'price' => $option['price'],
                    'retail_price' => (float) $product->price,
                    'stock' => $option['stock'],
                    'stock_label' => $option['stock_label'],
                ];
            })->filter(function (array $row) use ($query, $categoryId, $categoryFilterIds, $brandId): bool {
                $product = $row['product'];
                $haystack = mb_strtolower(implode(' ', [
                    $product->name,
                    $product->sku,
                    $product->category?->name,
                    $product->brand?->name,
                ]));

                return ($query === '' || str_contains($haystack, mb_strtolower($query)))
                    && ($categoryId === 0 || in_array((int) $product->category_id, $categoryFilterIds, true))
                    && ($brandId === 0 || (int) $product->brand_id === $brandId);
            });

            $rows = (match ($sort) {
                'price_asc' => $rows->sortBy('price'),
                'price_desc' => $rows->sortByDesc('price'),
                'stock' => $rows->sortByDesc(fn (array $row): float => $row['stock'] ?? 0),
                default => $rows->sortBy(fn (array $row): string => mb_strtolower($row['product']->name)),
            })->values();

            $products = $this->paginate($rows, $request, 30);
        }

        $representativeProducts = $mode === 'reference'
            ? $products->getCollection()
            : $listingOptions->unique('product_id')->pluck('product')->filter();
        $categoryTree = $this->categoryTree($categoryIndex, $availableCategoryIds, $categoryId, $representativeProducts);
        $minimalLayout = true;

        $visibleProductIds = $mode === 'reference'
            ? $products->getCollection()->pluck('id')
            : $products->getCollection()->pluck('product.id');
        $priceRequests = $staffPreview
            ? collect()
            : ContactRequest::query()
                ->where('user_id', $user->id)
                ->where('source', 'b2b_price_request')
                ->whereIn('product_id', $visibleProductIds)
                ->latest('id')
                ->get()
                ->unique('product_id')
                ->keyBy('product_id');

        return view('pages.b2b.marketplace', compact(
            'products', 'mode', 'query', 'categoryId', 'brandId', 'sourceKey', 'sort',
            'categoryTree', 'brands', 'sourceGroups', 'orderableProductIds', 'priceRequests', 'minimalLayout', 'staffPreview',
        ));
    }

    public function suggest(
        Request $request,
        B2bCatalogOfferResolver $resolver,
        B2bSupplyOptionResolver $supplyOptions,
    ): JsonResponse {
        $user = $request->user();
        $staffPreview = $request->boolean('b2b-preview')
            && ! $resolver->canViewPartnerPrices($user)
            && $resolver->canPreviewPartnerPrices($user);
        abort_unless($resolver->canViewPartnerPrices($user) || $staffPreview, 403);

        $query = mb_substr(trim($request->string('q')->toString()), 0, 100);
        if (mb_strlen($query) < 2) {
            return response()->json(['items' => []]);
        }

        $needle = mb_strtolower($query);
        $preview = $request->boolean('b2b-preview') ? 1 : null;
        $mode = $request->string('mode')->toString() === 'reference' ? 'reference' : 'orderable';

        if ($mode === 'reference') {
            $orderableProductIds = ($staffPreview
                ? $resolver->offersForStaffPreview($user)->pluck('product_id')
                : $supplyOptions->optionsForUser($user)->pluck('product_id'))
                ->unique();
            $items = Product::query()
                ->active()
                ->notArchived()
                ->with(['category', 'brand'])
                ->whereNotIn('id', $orderableProductIds)
                ->where(function ($builder) use ($query): void {
                    $builder->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$query}%"))
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$query}%"));
                })
                ->orderBy('name')
                ->limit(8)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?: ''),
                    'category' => (string) ($product->category?->name ?: ''),
                    'brand' => (string) ($product->brand?->name ?: ''),
                    'image' => (string) $product->image_url,
                    'price' => (float) $product->price,
                    'stock' => 'Запросить оптовую цену',
                    'url' => route('b2b.catalog', array_filter([
                        'mode' => 'reference',
                        'q' => $product->sku ?: $product->name,
                        'b2b-preview' => $preview,
                    ])),
                ]);

            return response()->json(['items' => $items]);
        }

        $options = $staffPreview
            ? $resolver->offersForStaffPreview($user)->map(function ($offer) use ($resolver): array {
                $availability = $resolver->availabilityFor($offer);

                return [
                    'product_id' => (int) $offer->product_id,
                    'product' => $offer->product,
                    'price' => $resolver->priceWithTax($offer),
                    'stock_label' => $availability['label'],
                ];
            })
            : $supplyOptions->optionsForUser($user);

        $items = $options
            ->groupBy('product_id')
            ->map(fn (Collection $productOptions): array => $productOptions->first())
            ->filter(function (array $option) use ($needle): bool {
                $product = $option['product'];
                $haystack = mb_strtolower(implode(' ', [
                    $product?->name,
                    $product?->sku,
                    $product?->brand?->name,
                    $product?->category?->name,
                ]));

                return str_contains($haystack, $needle);
            })
            ->sortBy(function (array $option) use ($needle): array {
                $product = $option['product'];
                $sku = mb_strtolower((string) $product?->sku);
                $name = mb_strtolower((string) $product?->name);

                return [
                    $sku === $needle ? 0 : ($name === $needle ? 1 : (str_starts_with($sku, $needle) ? 2 : 3)),
                    $name,
                ];
            })
            ->take(8)
            ->map(function (array $option) use ($preview): array {
                $product = $option['product'];

                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => (string) ($product->sku ?: ''),
                    'category' => (string) ($product->category?->name ?: ''),
                    'brand' => (string) ($product->brand?->name ?: ''),
                    'image' => (string) $product->image_url,
                    'price' => $option['price'],
                    'stock' => $option['stock_label'],
                    'url' => route('b2b.catalog', array_filter([
                        'mode' => 'orderable',
                        'q' => $product->sku ?: $product->name,
                        'b2b-preview' => $preview,
                    ])),
                ];
            })
            ->values();

        return response()->json(['items' => $items]);
    }

    private function applyProductFilters($builder, string $query, int $categoryId, array $categoryFilterIds, int $brandId): void
    {
        if ($query !== '') {
            $builder->where(function ($nested) use ($query): void {
                $nested->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', "%{$query}%"));
            });
        }

        if ($categoryId > 0) {
            $builder->whereIn('category_id', $categoryFilterIds);
        }
        if ($brandId > 0) {
            $builder->where('brand_id', $brandId);
        }
    }

    /** @return list<int> */
    private function categoryBranchIds(Collection $categories, int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $ids = [$categoryId];
        do {
            $children = $categories
                ->filter(fn (Category $category): bool => in_array((int) $category->parent_id, $ids, true))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->diff($ids)
                ->values()
                ->all();
            $ids = array_values(array_unique([...$ids, ...$children]));
        } while ($children !== []);

        return $ids;
    }

    private function categoryTree(Collection $categories, Collection $availableCategoryIds, int $selectedId, Collection $representativeProducts): Collection
    {
        $directCounts = $availableCategoryIds
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->countBy();
        $branchCounts = [];
        $branchImages = [];
        $branchImageScores = [];

        foreach ($directCounts as $categoryId => $count) {
            $currentId = (int) $categoryId;
            $visited = [];
            while ($currentId > 0 && isset($categories[$currentId]) && ! isset($visited[$currentId])) {
                $branchCounts[$currentId] = ($branchCounts[$currentId] ?? 0) + $count;
                $visited[$currentId] = true;
                $currentId = (int) $categories[$currentId]->parent_id;
            }
        }

        foreach ($representativeProducts as $product) {
            $productCategory = $product->category;
            $productImage = (string) $product->image_url;
            $currentId = (int) $product->category_id;
            $visited = [];
            while ($currentId > 0 && isset($categories[$currentId]) && ! isset($visited[$currentId])) {
                $category = $categories[$currentId];
                $searchableCategory = mb_strtolower(implode(' ', [
                    $productCategory?->name,
                    $productCategory?->slug,
                ]));
                $searchableProduct = mb_strtolower(implode(' ', [
                    $product->name,
                    $product->sku,
                ]));
                $score = (int) $product->category_id === $currentId ? 1000 : 0;

                foreach ($this->categoryImageTokens($category) as $token) {
                    if (str_contains($searchableCategory, $token)) {
                        $score += 40;
                    }
                    if (str_contains($searchableProduct, $token)) {
                        $score += 15;
                    }
                }

                if (str_contains($productImage, 'product-placeholder')) {
                    $score -= 500;
                }

                if (! isset($branchImageScores[$currentId]) || $score > $branchImageScores[$currentId]) {
                    $branchImageScores[$currentId] = $score;
                    $branchImages[$currentId] = $productImage;
                }
                $visited[$currentId] = true;
                $currentId = (int) $categories[$currentId]->parent_id;
            }
        }

        $selectedPath = [];
        $currentId = $selectedId;
        while ($currentId > 0 && isset($categories[$currentId]) && ! isset($selectedPath[$currentId])) {
            $selectedPath[$currentId] = true;
            $currentId = (int) $categories[$currentId]->parent_id;
        }

        $visible = $categories->filter(fn (Category $category): bool => isset($branchCounts[$category->id]));
        $children = $visible->groupBy(fn (Category $category): int => (int) $category->parent_id);
        $build = function (Category $category) use (&$build, $children, $branchCounts, $branchImages, $selectedId, $selectedPath): array {
            return [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => filled($category->image)
                    ? $category->image_url
                    : ($branchImages[$category->id] ?? $category->image_url),
                'count' => (int) ($branchCounts[$category->id] ?? 0),
                'selected' => (int) $category->id === $selectedId,
                'expanded' => isset($selectedPath[$category->id]),
                'children' => $children->get((int) $category->id, collect())
                    ->sortBy(fn (Category $child): string => mb_strtolower(trim($child->name)), SORT_NATURAL | SORT_FLAG_CASE)
                    ->map($build)
                    ->values(),
            ];
        };

        return $visible
            ->filter(fn (Category $category): bool => (int) $category->parent_id === 0 || ! isset($branchCounts[(int) $category->parent_id]))
            ->sortBy(fn (Category $category): string => mb_strtolower(trim($category->name)), SORT_NATURAL | SORT_FLAG_CASE)
            ->map($build)
            ->values();
    }

    /** @return list<string> */
    private function categoryImageTokens(Category $category): array
    {
        $ignored = ['система', 'товары', 'товар', 'для', 'или', 'system'];
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($category->name.' '.$category->slug)) ?: [];

        return collect($tokens)
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4 && ! in_array($token, $ignored, true))
            ->unique()
            ->values()
            ->all();
    }

    private function paginate(Collection $items, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $request->integer('page', 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
