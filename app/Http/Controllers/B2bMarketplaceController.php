<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\Product;
use App\Services\B2bCatalogOfferResolver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class B2bMarketplaceController extends Controller
{
    public function __invoke(Request $request, B2bCatalogOfferResolver $resolver)
    {
        $user = $request->user();
        abort_unless($resolver->canViewPartnerPrices($user), 403);

        $mode = $request->string('mode')->toString() === 'reference' ? 'reference' : 'orderable';
        $query = mb_substr(trim($request->string('q')->toString()), 0, 100);
        $categoryId = max(0, $request->integer('category'));
        $brandId = max(0, $request->integer('brand'));
        $sourceId = max(0, $request->integer('source'));
        $sort = in_array($request->string('sort')->toString(), ['name', 'price_asc', 'price_desc', 'stock'], true)
            ? $request->string('sort')->toString()
            : 'name';

        $allOffers = $resolver->offersForUser($user);
        $orderableProductIds = $allOffers->pluck('product_id')->unique()->map(fn ($id): int => (int) $id);
        $sourceProductIds = $sourceId > 0
            ? $allOffers->where('integration_source_id', $sourceId)->pluck('product_id')->unique()
            : collect();
        $offers = $allOffers
            ->groupBy('product_id')
            ->map(fn (Collection $items) => $items->sortBy(fn ($offer): float => $resolver->priceWithTax($offer))->first())
            ->when($sourceId > 0, fn (Collection $items) => $items->only($sourceProductIds->all()));

        $categories = $mode === 'reference'
            ? Category::query()->where('is_active', true)->orderBy('name')->get()
            : $allOffers->pluck('product.category')->filter()->unique('id')->sortBy('name')->values();
        $brands = $mode === 'reference'
            ? Brand::query()->whereHas('products', fn ($query) => $query->active()->notArchived())->orderBy('name')->get()
            : $allOffers->pluck('product.brand')->filter()->unique('id')->sortBy('name')->values();
        $sources = $allOffers->pluck('source')->filter()->unique('id')->sortBy('name')->values();

        if ($mode === 'reference') {
            $productsQuery = Product::query()
                ->active()
                ->notArchived()
                ->with(['category', 'brand'])
                ->whereNotIn('id', $orderableProductIds);

            $this->applyProductFilters($productsQuery, $query, $categoryId, $brandId);
            $products = $productsQuery->orderBy('name')->paginate(30)->withQueryString();
        } else {
            $rows = $offers->map(function ($offer) use ($resolver): array {
                $product = $offer->product;

                return [
                    'product' => $product,
                    'offer' => $offer,
                    'price' => $resolver->priceWithTax($offer),
                    'retail_price' => (float) $product->price,
                    'stock' => $resolver->isProcurementOffer($offer) ? null : (float) $offer->stock_quantity,
                    'stock_label' => $resolver->isProcurementOffer($offer)
                        ? data_get($offer->source?->settings, 'warehouse_label', 'Под заказ')
                        : data_get($offer->source?->settings, 'warehouse_label', 'Основной').': '.$offer->formattedStockQuantity(),
                ];
            })->filter(function (array $row) use ($query, $categoryId, $brandId): bool {
                $product = $row['product'];
                $haystack = mb_strtolower(implode(' ', [
                    $product->name,
                    $product->sku,
                    $product->category?->name,
                    $product->brand?->name,
                ]));

                return ($query === '' || str_contains($haystack, mb_strtolower($query)))
                    && ($categoryId === 0 || (int) $product->category_id === $categoryId)
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

        $visibleProductIds = $mode === 'reference'
            ? $products->getCollection()->pluck('id')
            : $products->getCollection()->pluck('product.id');
        $priceRequests = ContactRequest::query()
            ->where('user_id', $user->id)
            ->where('source', 'b2b_price_request')
            ->whereIn('product_id', $visibleProductIds)
            ->latest('id')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        return view('pages.b2b-marketplace', compact(
            'products', 'mode', 'query', 'categoryId', 'brandId', 'sourceId', 'sort',
            'categories', 'brands', 'sources', 'orderableProductIds', 'priceRequests',
        ));
    }

    private function applyProductFilters($builder, string $query, int $categoryId, int $brandId): void
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
            $builder->where('category_id', $categoryId);
        }
        if ($brandId > 0) {
            $builder->where('brand_id', $brandId);
        }
    }

    private function paginate(Collection $items, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = max(1, $request->integer('page', 1));

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }
}
