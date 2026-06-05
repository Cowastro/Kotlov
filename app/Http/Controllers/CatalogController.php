<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CatalogController extends Controller
{
    public function show(string $categorySlug)
    {
        $category = Category::where('slug', $categorySlug)
            ->where('is_active', true)
            ->with('parent')
            ->firstOrFail();

        // Все ID подкатегорий включая саму категорию
        $allCategoryIds = $this->collectCategoryAndDescendantIds($category->id);

        // Подкатегории для фильтра
        $subcategories = Category::where('parent_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function ($subcategory) {
                $ids = $this->collectCategoryAndDescendantIds($subcategory->id);
                $subcategory->products_count = Product::where('is_active', true)
                    ->whereIn('category_id', $ids)
                    ->count();
            })
            ->filter(fn($subcategory) => $subcategory->products_count > 0)
            ->values();

        // Если выбрана подкатегория
        $activeCategoryIds = $allCategoryIds;
        if (request('subcategory')) {
            $sub = Category::where('slug', request('subcategory'))
                ->where('is_active', true)
                ->first();

            if ($sub && $allCategoryIds->contains($sub->id)) {
                $activeCategoryIds = $this->collectCategoryAndDescendantIds($sub->id);
            }
        }

        // Бренды с количеством товаров в категории
        $brands = Brand::whereHas('products', fn($q) =>
                $q->where('is_active', true)->whereIn('category_id', $activeCategoryIds)
            )
            ->withCount(['products' => fn($q) =>
                $q->where('is_active', true)->whereIn('category_id', $activeCategoryIds)
            ])
            ->orderBy('name')
            ->get();

        // Атрибуты для фильтрации — дедупликация по имени
        // Одинаковые атрибуты (напр. "Мощность") могут быть привязаны к разным подкатегориям,
        // поэтому группируем по name и объединяем опции
        $rawAttributes = Attribute::where('in_filter', true)
            ->where('type', 'select')
            ->whereIn('category_id', $activeCategoryIds)
            ->with(['options' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $filterAttributes = $rawAttributes
            ->groupBy(fn($attr) => $this->normalizeFilterName($attr->name))
            ->map(function ($group) use ($activeCategoryIds) {
                /** @var Attribute $primary */
                $primary = $group->first();
                $allAttrIds = $group->pluck('id')->all();

                $mergedOptions = $group
                    ->flatMap(fn($attr) => $attr->options)
                    ->groupBy(fn($option) => $this->normalizeFilterName($option->name))
                    ->map(function ($options) use ($allAttrIds, $activeCategoryIds) {
                        $primaryOption = $options->sortBy('sort_order')->first();
                        $optionIds = $options->pluck('id')->all();
                        $productsCount = ProductAttributeValue::whereIn('attribute_id', $allAttrIds)
                            ->whereIn('option_id', $optionIds)
                            ->whereHas('product', fn($q) => $q
                                ->where('is_active', true)
                                ->whereIn('category_id', $activeCategoryIds)
                            )
                            ->count();

                        if ($productsCount === 0) {
                            return null;
                        }

                        $dto = new \stdClass();
                        $dto->id = $primaryOption->id;
                        $dto->name = trim($primaryOption->name);
                        $dto->sort_order = $primaryOption->sort_order;
                        $dto->all_ids = $optionIds;
                        $dto->products_count = $productsCount;

                        return $dto;
                    })
                    ->filter()
                    ->sortBy('sort_order')
                    ->values();

                if ($mergedOptions->isEmpty()) {
                    return null;
                }

                $dto = new \stdClass();
                $dto->id = $primary->id;
                $dto->name = trim($primary->name);
                $dto->suffix = $primary->suffix;
                $dto->type = $primary->type;
                $dto->options = $mergedOptions;
                $dto->all_ids = $allAttrIds;
                $dto->option_id_map = $mergedOptions->mapWithKeys(fn($option) => [
                    $option->id => $option->all_ids,
                ])->all();

                return $dto;
            })
            ->filter()
            ->values();

        if ($brands->isNotEmpty()) {
            $filterAttributes = $filterAttributes
                ->reject(fn($attr) => in_array($this->normalizeFilterName($attr->name), ['производитель', 'бренд'], true))
                ->values();
        }

        $priceRange = Product::where('is_active', true)
            ->whereIn('category_id', $activeCategoryIds)
            ->where('price', '>', 0)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $priceMin = (int) floor($priceRange->min_price ?? 0);
        $priceMax = (int) ceil($priceRange->max_price ?? 10000);

        $allProductsCount = Product::where('is_active', true)
            ->whereIn('category_id', $allCategoryIds)
            ->count();

        // Запрос товаров
        $query = Product::where('is_active', true)
            ->whereIn('category_id', $activeCategoryIds)
            ->with(['category', 'brand']);

        // Фильтр по цене
        if (request('price_min')) {
            $query->where('price', '>=', request('price_min'));
        }
        if (request('price_max')) {
            $query->where('price', '<=', request('price_max'));
        }

        // Фильтр по наличию
        if (request('in_stock') == '1') {
            $query->where('in_stock', true);
        }

        // Фильтр по бренду
        if (request('brand')) {
            $query->where('brand_id', request('brand'));
        }

        // Фильтр по атрибутам
        // request('attr') содержит id первичного атрибута → ищем по всем его дублям
        if (request('attr')) {
            // Строим карту: первичный id → все id дублей (включая сам)
            $attrMap = $filterAttributes->mapWithKeys(fn($attr) => [
                $attr->id => $attr,
            ]);

            foreach (request('attr') as $attrId => $optionIds) {
                if (!empty($optionIds)) {
                    $attr = $attrMap->get((int) $attrId);
                    $allAttrIds = $attr?->all_ids ?? [(int) $attrId];
                    $allOptionIds = collect((array) $optionIds)
                        ->flatMap(fn($optionId) => $attr?->option_id_map[(int) $optionId] ?? [(int) $optionId])
                        ->unique()
                        ->values()
                        ->all();

                    $query->whereHas('allAttributeValues', function ($q) use ($allAttrIds, $allOptionIds) {
                        $q->whereIn('attribute_id', $allAttrIds)
                          ->whereIn('option_id', $allOptionIds);
                    });
                }
            }
        }

        // Сортировка
        switch (request('sort')) {
            case 'price_asc':
                $query->orderBy('price');
                break;
            case 'price_desc':
                $query->orderByDesc('price');
                break;
            case 'name_asc':
                $query->orderBy('name');
                break;
            case 'rating':
                $query->orderByDesc('rating');
                break;
            case 'new':
                $query->orderByDesc('is_new')->orderByDesc('id');
                break;
            default:
                $query->orderByDesc('is_featured')->orderByDesc('rating');
        }

        $totalCount = $query->count();
        $products = $query->paginate(24)->withQueryString();

        return view('pages.catalog', compact(
            'category',
            'subcategories',
            'brands',
            'filterAttributes',
            'products',
            'priceMin',
            'priceMax',
            'totalCount',
            'allProductsCount'
        ));
    }

    private function collectCategoryAndDescendantIds(int $categoryId): Collection
    {
        $categories = Category::where('is_active', true)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        $ids = collect([$categoryId]);
        $queue = [$categoryId];

        while ($queue) {
            $parentId = array_shift($queue);

            foreach ($categories->get($parentId, collect()) as $child) {
                $ids->push($child->id);
                $queue[] = $child->id;
            }
        }

        return $ids->unique()->values();
    }

    private function normalizeFilterName(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
