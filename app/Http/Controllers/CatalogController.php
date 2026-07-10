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
        $selectedBrandId = request('brand') ? (int) request('brand') : null;

        $category = Category::where('slug', $categorySlug)
            ->where('is_active', true)
            ->with('parent')
            ->first();

        // Для "Для дачи" показываем все товары из ветки "Печи"
        if (! $category) {
            $product = Product::where('slug', $categorySlug)
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('is_archived', true))
                ->with('category')
                ->first();

            if ($product && $product->category) {
                request()->attributes->set('allow_single_slug_product', true);

                return app(ProductController::class)->show($product->category->slug, $product->slug);
            }

            abort(404);
        }

        if ($category->slug === 'dlya-dachi') {
            $pechki = Category::where('slug', 'pechki')->first();
            if ($pechki) {
                $allCategoryIds = $this->collectCategoryAndDescendantIds($pechki->id);
            } else {
                $allCategoryIds = $this->collectCategoryAndDescendantIds($category->id);
            }
        } else {
            $allCategoryIds = $this->collectCategoryAndDescendantIds($category->id);
        }

        // Подкатегории для фильтра
        $subcategories = Category::where('parent_id', $category->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->each(function ($subcategory) use ($selectedBrandId) {
                $ids = $this->collectCategoryAndDescendantIds($subcategory->id);
                $subcategory->products_count = Product::query()
                    ->orderable()
                    ->whereIn('category_id', $ids)
                    ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId))
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
                $q->orderable()->whereIn('category_id', $activeCategoryIds)
            )
            ->withCount(['products' => fn($q) =>
                $q->orderable()->whereIn('category_id', $activeCategoryIds)
            ])
            ->orderBy('name')
            ->get();

        // На родительской категории без выбранной подкатегории range-фильтры
        // (мощность, площадь) не показываем — каждая подкатегория имеет свои диапазоны,
        // их объединение в один список бессмысленно для пользователя.
        $isParentView = $subcategories->isNotEmpty() && !request('subcategory');
        $rangeFilterNames = ['мощность', 'обогреваемая площадь', 'площадь обогрева'];

        // Атрибуты для фильтрации — дедупликация по имени
        // Одинаковые атрибуты (напр. "Мощность") могут быть привязаны к разным подкатегориям,
        // поэтому группируем по name и объединяем опции.
        // Также включаем атрибуты родительских категорий — они наследуются подкатегориями.
        // (Например, Толщина металла привязана к /dymohody, но нужна и на /shibery-dymohod)
        // Атрибуты с 0 товаров автоматически отфильтруются ниже.
        $ancestorCategoryIds = collect();
        $curr = $category;
        while ($curr && $curr->parent_id) {
            $ancestorCategoryIds->push($curr->parent_id);
            $curr = Category::find($curr->parent_id);
        }
        $attrCategoryIds = $activeCategoryIds->merge($ancestorCategoryIds)->unique();

        $rawAttributes = Attribute::where('in_filter', true)
            ->where('type', 'select')
            ->whereIn('category_id', $attrCategoryIds)
            ->with(['options' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Счётчики товаров по всем опциям одним групповым запросом —
        // раньше был отдельный COUNT на каждую опцию каждого фильтра (N+1)
        $optionCounts = ProductAttributeValue::query()
            ->whereIn('attribute_id', $rawAttributes->pluck('id'))
            ->whereNotNull('option_id')
            ->whereHas('product', fn($q) => $q
                ->orderable()
                ->whereIn('category_id', $activeCategoryIds)
                ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId))
            )
            ->groupBy('attribute_id', 'option_id')
            ->selectRaw('attribute_id, option_id, count(*) as cnt')
            ->get();

        $filterAttributes = $rawAttributes
            ->groupBy(fn($attr) => $this->normalizeFilterName($attr->name))
            ->map(function ($group) use ($optionCounts) {
                /** @var Attribute $primary */
                $primary = $group->first();
                $allAttrIds = $group->pluck('id')->all();

                $mergedOptions = $group
                    ->flatMap(fn($attr) => $attr->options)
                    ->groupBy(fn($option) => $this->normalizeFilterName($option->name))
                    ->map(function ($options) use ($allAttrIds, $optionCounts) {
                        $primaryOption = $options->sortBy('sort_order')->first();
                        $optionIds = $options->pluck('id')->all();
                        $productsCount = (int) $optionCounts
                            ->whereIn('attribute_id', $allAttrIds)
                            ->whereIn('option_id', $optionIds)
                            ->sum('cnt');

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

        // Скрываем range-фильтры на родительской странице (без выбранной подкатегории)
        if ($isParentView) {
            $filterAttributes = $filterAttributes
                ->reject(fn($attr) => in_array(
                    mb_strtolower(trim(preg_replace('/\s*\(.*\)/', '', $attr->name))),
                    $rangeFilterNames,
                    true
                ))
                ->values();
        }

        $priceRange = Product::query()
            ->orderable()
            ->whereIn('category_id', $activeCategoryIds)
            ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId))
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $priceMin = (int) floor($priceRange->min_price ?? 0);
        $priceMax = (int) ceil($priceRange->max_price ?? 10000);

        $allProductsCount = Product::query()
            ->orderable()
            ->whereIn('category_id', $allCategoryIds)
            ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId))
            ->count();

        // Запрос товаров
        $query = Product::query()
            ->orderable()
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

        // Город с поддомена (через middleware CitySubdomain)
        $sharedCityIn = view()->shared('cityIn');
        $cityIn       = $sharedCityIn ?: 'в Беларуси';
        $citySuffix   = ' ' . $cityIn;

        // Подставляем город в мета-теги из БД или генерируем автоматически
        // name_in уже содержит предлог «в» (напр. «в Борисове»)
        // Поэтому «в %city%» → cityIn, а одиночный %city% → только название (без «в»)
        $cityName = preg_replace('/^в\s+/u', '', $cityIn); // «Борисове» или «Беларуси»
        $replaceCityIn = function (?string $text) use ($cityIn, $cityName): ?string {
            if (!$text) return null;
            $text = str_replace('в %city%', $cityIn, $text);   // «в %city%» → «в Борисове»
            $text = str_replace('%city%', $cityName, $text);    // остаток «%city%» → «Борисове»
            return $text;
        };

        $category->name        = $replaceCityIn($category->name)        ?? $category->name;
        $category->h1          = $replaceCityIn($category->h1)          ?? $category->h1;
        $category->description = $replaceCityIn($category->description) ?? $category->description;

        $name      = $category->name;
        $nameLower = mb_strtolower($name);

        // Title: если старый > 70 символов — заменяем на короткий автошаблон
        $rawTitle = $replaceCityIn($category->meta_title);
        $title = ($rawTitle && mb_strlen($rawTitle) <= 70)
            ? $rawTitle
            : ($name . ' — купить ' . $cityIn . ' | KOTLOV');

        // Description: если > 180 символов — заменяем на короткий автошаблон
        $rawDesc = $replaceCityIn($category->meta_description);
        $description = ($rawDesc && mb_strlen($rawDesc) <= 180)
            ? $rawDesc
            : ('Купить ' . $nameLower . ' ' . $cityIn
                . '. Каталог ' . $allProductsCount . ' товаров.'
                . ' Доставка по Беларуси, гарантия, монтаж.');

        $keywords = $replaceCityIn($category->meta_keywords)
            ?: ($name . ', купить ' . $nameLower . ' ' . $cityIn . ', цена, каталог');

        $canonicalBase = 'https://' . request()->getHost();
        $canonical = $canonicalBase . '/' . $category->slug;

        // Schema.org BreadcrumbList
        $breadcrumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $canonicalBase . '/'],
        ];
        $pos = 2;
        if ($category->parent_id && $category->parent) {
            $parentSchemaName = trim((string) ($category->parent->name ?: $category->parent->slug));

            $breadcrumbs[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $parentSchemaName,
                'item'     => $canonicalBase . '/' . $category->parent->slug,
            ];
        }

        $categorySchemaName = trim((string) ($category->h1 ?: $category->name ?: $category->slug));

        $breadcrumbs[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => $categorySchemaName,
            'item'     => $canonical,
        ];

        $schemaJson = json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.catalog', compact(
            'category',
            'subcategories',
            'brands',
            'filterAttributes',
            'products',
            'priceMin',
            'priceMax',
            'totalCount',
            'allProductsCount',
            'title',
            'description',
            'keywords',
            'canonical',
            'schemaJson'
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
