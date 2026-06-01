<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function show(string $categorySlug)
    {
        $category = Category::where('slug', $categorySlug)
            ->where('is_active', true)
            ->with('parent')
            ->firstOrFail();

        // Все ID подкатегорий включая саму категорию
        $subcategoryIds = Category::where('parent_id', $category->id)
            ->where('is_active', true)
            ->pluck('id');

        $allCategoryIds = $subcategoryIds->prepend($category->id);

        // Подкатегории для фильтра
        $subcategories = Category::where('parent_id', $category->id)
            ->where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        // Если выбрана подкатегория
        $activeCategoryIds = $allCategoryIds;
        if (request('subcategory')) {
            $sub = Category::where('slug', request('subcategory'))->first();
            if ($sub) {
                $activeCategoryIds = collect([$sub->id]);
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
            ->whereIn('category_id', $allCategoryIds)
            ->with(['options' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Группируем по имени, оставляем первый атрибут, мержим опции из дублей
        $filterAttributes = $rawAttributes
            ->groupBy('name')
            ->map(function ($group) {
                /** @var Attribute $primary */
                $primary = $group->first();

                // Собираем все опции со всех дублей, дедуплицируем по name
                $mergedOptions = $group
                    ->flatMap(fn($attr) => $attr->options)
                    ->unique('name')
                    ->sortBy('sort_order')
                    ->values();

                // Создаём простой объект чтобы не трогать Eloquent relations
                $dto = new \stdClass();
                $dto->id      = $primary->id;
                $dto->name    = $primary->name;
                $dto->suffix  = $primary->suffix;
                $dto->type    = $primary->type;
                $dto->options = $mergedOptions;
                $dto->all_ids = $group->pluck('id')->all();

                return $dto;
            })
            ->values();

        // Диапазон цен
        $priceRange = Product::where('is_active', true)
            ->whereIn('category_id', $activeCategoryIds)
            ->where('price', '>', 0)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $priceMin = (int) floor($priceRange->min_price ?? 0);
        $priceMax = (int) ceil($priceRange->max_price ?? 10000);

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
            $attrIdMap = $filterAttributes->mapWithKeys(fn($attr) => [
                $attr->id => $attr->all_ids ?? [$attr->id],
            ]);

            foreach (request('attr') as $attrId => $optionIds) {
                if (!empty($optionIds)) {
                    $allAttrIds = $attrIdMap->get((int) $attrId, [(int) $attrId]);
                    $query->whereHas('attributeValues', function ($q) use ($allAttrIds, $optionIds) {
                        $q->whereIn('attribute_id', $allAttrIds)
                          ->whereIn('option_id', (array) $optionIds);
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
            'totalCount'
        ));
    }
}