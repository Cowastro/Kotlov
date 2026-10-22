<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\ChimneyDiameterQuickFilter;
use App\Services\SeoMetadataBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CatalogController extends Controller
{
    private const PELLET_POWER_RANGES = [
        'up-to-25' => ['label' => 'до 25 кВт', 'min' => null, 'max' => 25],
        '26-50' => ['label' => '26–50 кВт', 'min' => 25, 'max' => 50],
        '51-100' => ['label' => '51–100 кВт', 'min' => 50, 'max' => 100],
        'over-100' => ['label' => 'свыше 100 кВт', 'min' => 100, 'max' => null],
    ];

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
            ->orderBy('name')
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
            ->filter(fn ($subcategory) => $subcategory->products_count > 0)
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
        $brands = Brand::whereHas('products', fn ($q) => $q->orderable()->whereIn('category_id', $activeCategoryIds)
        )
            ->withCount(['products' => fn ($q) => $q->orderable()->whereIn('category_id', $activeCategoryIds),
            ])
            ->orderBy('name')
            ->get();

        // На родительской категории без выбранной подкатегории range-фильтры
        // (мощность, площадь) не показываем — каждая подкатегория имеет свои диапазоны,
        // их объединение в один список бессмысленно для пользователя.
        $isParentView = $subcategories->isNotEmpty() && ! request('subcategory');
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

        $pelletPowerAttributeIds = collect();
        $pelletPowerRanges = collect();
        $isPelletPowerCatalog = in_array($category->slug, ['pelletnye-gorelki', 'kotly-na-pelletah'], true);
        if ($isPelletPowerCatalog) {
            $pelletPowerAttributeIds = Attribute::query()
                ->where('type', 'value')
                ->whereHas('values', fn ($valueQuery) => $valueQuery
                    ->whereHas('product', fn ($productQuery) => $productQuery
                        ->orderable()
                        ->whereIn('category_id', $activeCategoryIds)
                    )
                )
                ->get(['id', 'name'])
                ->filter(fn (Attribute $attribute) => in_array(
                    $this->normalizeFilterName($attribute->name),
                    ['номинальная мощность', 'мощность номинальная', 'мощность', 'мощность, квт', 'мощность (квт)'],
                    true
                ))
                ->pluck('id')
                ->values();

            if ($pelletPowerAttributeIds->isNotEmpty()) {
                $pelletPowerRanges = collect(self::PELLET_POWER_RANGES)
                    ->map(function (array $range, string $key) use ($activeCategoryIds, $selectedBrandId, $pelletPowerAttributeIds) {
                        $countQuery = Product::query()
                            ->orderable()
                            ->whereIn('category_id', $activeCategoryIds)
                            ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId));

                        $this->applyPelletPowerRange($countQuery, $pelletPowerAttributeIds, $key);

                        return (object) [
                            'key' => $key,
                            'label' => $range['label'],
                            'products_count' => $countQuery->count(),
                        ];
                    })
                    ->filter(fn (object $range) => $range->products_count > 0)
                    ->values();
            }
        }

        $rawAttributes = Attribute::where('in_filter', true)
            ->where('type', 'select')
            ->whereIn('category_id', $attrCategoryIds)
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Счётчики товаров по всем опциям одним групповым запросом —
        // раньше был отдельный COUNT на каждую опцию каждого фильтра (N+1)
        $optionCounts = ProductAttributeValue::query()
            ->whereIn('attribute_id', $rawAttributes->pluck('id'))
            ->whereNotNull('option_id')
            ->whereHas('product', fn ($q) => $q
                ->orderable()
                ->whereIn('category_id', $activeCategoryIds)
                ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId))
            )
            ->groupBy('attribute_id', 'option_id')
            ->selectRaw('attribute_id, option_id, count(*) as cnt')
            ->get();

        $filterAttributes = $rawAttributes
            ->groupBy(fn ($attr) => $this->normalizeFilterName($attr->name))
            ->map(function ($group) use ($optionCounts) {
                /** @var Attribute $primary */
                $primary = $group->first();
                $allAttrIds = $group->pluck('id')->all();

                $mergedOptions = $group
                    ->flatMap(fn ($attr) => $attr->options)
                    ->groupBy(fn ($option) => $this->normalizeFilterName($option->name))
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

                        $dto = new \stdClass;
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

                $dto = new \stdClass;
                $dto->id = $primary->id;
                $dto->name = trim($primary->name);
                $dto->suffix = $this->visibleFilterSuffix($dto->name, $primary->suffix);
                $dto->type = $primary->type;
                $dto->options = $mergedOptions;
                $dto->all_ids = $allAttrIds;
                $dto->option_id_map = $mergedOptions->mapWithKeys(fn ($option) => [
                    $option->id => $option->all_ids,
                ])->all();

                return $dto;
            })
            ->filter()
            ->values();

        if ($brands->isNotEmpty()) {
            $filterAttributes = $filterAttributes
                ->reject(fn ($attr) => in_array($this->normalizeFilterName($attr->name), ['производитель', 'бренд'], true))
                ->values();
        }

        // Скрываем range-фильтры на родительской странице (без выбранной подкатегории)
        if ($isParentView) {
            $filterAttributes = $filterAttributes
                ->reject(fn ($attr) => in_array(
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
            ->withSupplierChannels()
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

        if ($isPelletPowerCatalog && request('power')) {
            $this->applyPelletPowerRange(
                $query,
                $pelletPowerAttributeIds,
                (string) request('power')
            );
        }

        // Фильтр по атрибутам
        // request('attr') содержит id первичного атрибута → ищем по всем его дублям
        if (request('attr')) {
            // Строим карту: первичный id → все id дублей (включая сам)
            $attrMap = $filterAttributes->mapWithKeys(fn ($attr) => [
                $attr->id => $attr,
            ]);

            foreach (request('attr') as $attrId => $optionIds) {
                if (! empty($optionIds)) {
                    $attr = $attrMap->get((int) $attrId);
                    $allAttrIds = $attr?->all_ids ?? [(int) $attrId];
                    $allOptionIds = collect((array) $optionIds)
                        ->flatMap(fn ($optionId) => $attr?->option_id_map[(int) $optionId] ?? [(int) $optionId])
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

        $chimneyDiameterGroups = collect();
        $monoChimneyDiameterGroups = collect();
        $chimneySteelGroups = collect();
        if ($category->slug === 'dymohody' || $category->parent?->slug === 'dymohody') {
            $chimneyDiameterGroups = app(ChimneyDiameterQuickFilter::class)->groups($query);
            $monoChimneyDiameterGroups = app(ChimneyDiameterQuickFilter::class)->groups($query, mono: true);
            if (request()->filled('chimney_diameter')) {
                $query->whereIn('products.id', $chimneyDiameterGroups->get((string) request('chimney_diameter'), collect()));
            }
            if (request()->filled('mono_diameter')) {
                $query->whereIn('products.id', $monoChimneyDiameterGroups->get((string) request('mono_diameter'), collect()));
            }
            $chimneySteelGroups = app(ChimneyDiameterQuickFilter::class)->steelGroups($query);
            if (request()->filled('chimney_steel')) {
                $query->whereIn('products.id', $chimneySteelGroups->get(strtoupper((string) request('chimney_steel')), collect()));
            }
        }

        // Сортировка
        $isChimneyCatalog = $category->slug === 'dymohody'
            || $category->parent?->slug === 'dymohody';
        $isStoveOrFireplaceCatalog = in_array($category->slug, ['pechki', 'kaminy'], true)
            || in_array($category->parent?->slug, ['pechki', 'kaminy'], true);
        $isPipesCatalog = $category->slug === 'truby-i-fitingi'
            || $category->parent?->slug === 'truby-i-fitingi';

        // Confirmed stock is the primary catalogue order for every user-selected
        // sort. Price, popularity, name and novelty only sort within each group.
        $query->prioritizeConfirmedStock();

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
                $query->orderByDesc('rating')
                    ->orderByDesc('reviews_count')
                    ->orderByDesc('popularity_score')
                    ->orderByDesc('views_count')
                    ->orderByDesc('id');
                break;
            case 'new':
                $query->orderByDesc('is_new')->orderByDesc('id');
                break;
            default:
                $priorityBrandId = match (true) {
                    $category->slug === 'teplovyie-nasosyi' => Brand::query()
                        ->where('slug', 'kotlov-ge')
                        ->value('id'),
                    $isChimneyCatalog => $brands->firstWhere('slug', 'teplov-i-suhov')?->id,
                    $isPipesCatalog => $brands->firstWhere('slug', 'varmega')?->id,
                    default => null,
                };

                $catalogStockAlreadyOrdered = false;

                if (in_array($category->slug, ['pechki', 'pechi-kaminy'], true)) {
                    $query->orderByDesc('in_stock')
                        ->prioritizeNamePatterns([
                            '%Печь%',
                            '%печь%',
                            '%Плита на твердом топливе%',
                            '%плита на твердом топливе%',
                        ]);
                    $catalogStockAlreadyOrdered = true;
                }

                if ($category->slug === 'kotly-na-pelletah') {
                    $query->pelletBoilerCatalogOrder();
                    $catalogStockAlreadyOrdered = true;
                }

                if (in_array($category->slug, ['pechki', 'kaminy'], true) && ! request('subcategory')) {
                    $accessoryCategorySlugs = $category->slug === 'pechki'
                        ? ['pechnoe-i-kaminnoe-lite']
                        : ['aksessuary-kaminy'];
                    $accessoryCategoryIds = Category::query()
                        ->whereIn('slug', $accessoryCategorySlugs)
                        ->whereIn('id', $allCategoryIds)
                        ->pluck('id')
                        ->flatMap(fn (int $categoryId) => $this->collectCategoryAndDescendantIds($categoryId))
                        ->unique()
                        ->values()
                        ->all();

                    if (! $catalogStockAlreadyOrdered) {
                        $query->orderByDesc('in_stock');
                        $catalogStockAlreadyOrdered = true;
                    }

                    $query->deprioritizeCategories($accessoryCategoryIds);
                }

                if ($category->slug === 'tverdotoplivnye') {
                    $residentialAreaOptionIds = $filterAttributes
                        ->first(fn ($attribute) => $this->normalizeFilterName($attribute->name) === 'обогреваемая площадь (m2)')
                        ?->options
                        ?->filter(fn ($option) => in_array(
                            $this->normalizeFilterName($option->name),
                            ['до 150 м²', '150–300 м²'],
                            true
                        ))
                        ->pluck('all_ids')
                        ->flatten()
                        ->unique()
                        ->values()
                        ->all() ?? [];

                    $query->prioritizeAttributeOptions($residentialAreaOptionIds);
                }

                $query->catalogDefaultOrder(
                    $priorityBrandId,
                    ! $catalogStockAlreadyOrdered && ($isChimneyCatalog
                        || $isStoveOrFireplaceCatalog
                        || $isPipesCatalog
                        || in_array($category->slug, ['tverdotoplivnye', 'bufernye-emkosti', 'kosvennye'], true))
                );
        }

        $totalCount = $query->count();
        $products = $query->paginate(24)->withQueryString();

        // Город с поддомена (через middleware CitySubdomain)
        $sharedCityIn = view()->shared('cityIn');
        $cityIn = $sharedCityIn ?: 'в Беларуси';
        $citySuffix = ' '.$cityIn;

        // Подставляем город в мета-теги из БД или генерируем автоматически
        // name_in уже содержит предлог «в» (напр. «в Борисове»)
        // Поэтому «в %city%» → cityIn, а одиночный %city% → только название (без «в»)
        $cityName = preg_replace('/^в\s+/u', '', $cityIn); // «Борисове» или «Беларуси»
        $replaceCityIn = function (?string $text) use ($cityIn, $cityName): ?string {
            if (! $text) {
                return null;
            }
            $text = str_replace('в %city%', $cityIn, $text);   // «в %city%» → «в Борисове»
            $text = str_replace('%city%', $cityName, $text);    // остаток «%city%» → «Борисове»

            return $text;
        };

        $category->name = $replaceCityIn($category->name) ?? $category->name;
        $category->h1 = $replaceCityIn($category->h1) ?? $category->h1;
        $category->description = $replaceCityIn($category->description) ?? $category->description;

        $name = $category->name;
        $nameLower = mb_strtolower($name);

        // Title: если старый > 70 символов — заменяем на короткий автошаблон
        $seo = app(SeoMetadataBuilder::class);
        $title = $seo->categoryTitle(
            $category->slug,
            $name,
            $cityIn,
            $category->meta_title,
        );

        // Description: если > 180 символов — заменяем на короткий автошаблон
        $rawDesc = $replaceCityIn($category->meta_description);
        $autoDescription = 'Купить '.$nameLower.' '.$cityIn
                .'. Каталог '.$allProductsCount.' товаров.'
                .' Доставка по Беларуси, гарантия, монтаж.';
        $description = $seo->description(
            $rawDesc && mb_strlen($rawDesc) <= SeoMetadataBuilder::DESCRIPTION_LIMIT ? $rawDesc : null,
            $autoDescription
        );

        $keywords = $replaceCityIn($category->meta_keywords)
            ?: ($name.', купить '.$nameLower.' '.$cityIn.', цена, каталог');

        $canonicalBase = $category->slug === 'schetchiki-gaza'
            ? 'https://'.config('app.base_domain', 'kotlov.by')
            : 'https://'.request()->getHost();
        $canonical = $canonicalBase.'/'.$category->slug;

        // Schema.org BreadcrumbList
        $breadcrumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $canonicalBase.'/'],
        ];
        $pos = 2;
        if ($category->parent_id && $category->parent) {
            $parentSchemaName = trim((string) ($category->parent->name ?: $category->parent->slug));

            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $parentSchemaName,
                'item' => $canonicalBase.'/'.$category->parent->slug,
            ];
        }

        $categorySchemaName = trim((string) ($category->h1 ?: $category->name ?: $category->slug));

        $breadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => $pos,
            'name' => $categorySchemaName,
            'item' => $canonical,
        ];

        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        ];

        $heatPumpArticles = collect();
        $pelletBurnerFaq = collect();
        $gasMeterFaq = collect();
        $heatFluidFaq = collect();
        $pelletComparisonProducts = collect();
        $schemaNodes = [$breadcrumbSchema];

        if ($category->slug === 'schetchiki-gaza') {
            $gasMeterFaq = $this->gasMeterFaqItems();

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Газовые счетчики для квартиры и частного дома',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $gasMeterFaq->map(fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ])->values()->all(),
            ];
        }

        if ($category->slug === 'teplonositeli') {
            $heatFluidFaq = $this->heatFluidFaqItems();

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $heatFluidFaq->map(fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ])->values()->all(),
            ];
        }

        if ($category->slug === 'teplovyie-nasosyi') {
            $articleOrder = [
                'postuplenie-teplovyh-nasosov-kotlov-na-sklad',
                'kak-vybrat-teplovoy-nasos',
                'teplovye-nasosy-ge-r290-vysokotemperaturnye',
                'montazh-teplovogo-nasosa-kotlov-ge-10-kvt-r290-smolevichskiy-rayon',
                'teplovoy-nasos-kotlov-ge-24-kvt-r32-nareyki',
                'teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok',
            ];

            $heatPumpArticles = BlogPost::published()
                ->whereIn('slug', $articleOrder)
                ->get()
                ->sortBy(fn (BlogPost $post) => array_search($post->slug, $articleOrder, true))
                ->values();

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Тепловые насосы воздух-вода',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $this->heatPumpFaqSchema(),
            ];
        }

        if ($category->slug === 'pelletnye-gorelki') {
            $pelletBurnerFaq = $this->pelletBurnerFaqItems();
            $comparisonOrder = [
                'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-ea140',
                'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3',
                'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt',
            ];
            $pelletComparisonProducts = Product::query()
                ->orderable()
                ->whereIn('slug', $comparisonOrder)
                ->with(['category', 'brand'])
                ->get()
                ->sortBy(fn (Product $product) => array_search($product->slug, $comparisonOrder, true))
                ->values();

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Пеллетные горелки',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];

            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $pelletBurnerFaq->map(fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ])->values()->all(),
            ];
        }

        if ($category->slug === 'kotly-na-pelletah') {
            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Пеллетные котлы',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];
        }

        if ($category->slug === 'tverdotoplivnye') {
            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => 'Твердотопливные котлы',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];
        }

        if (in_array($category->slug, ['gazovye', 'elektricheskie'], true)) {
            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $category->slug === 'gazovye' ? 'Газовые котлы' : 'Электрические котлы',
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];
        }

        $stoveSchemaNames = [
            'pechki' => 'Печи для дома и дачи',
            'pechi-kaminy' => 'Печи-камины',
            'pechi' => 'Дровяные печи',
            'peci-drovianye-otopitelnye' => 'Дровяные отопительные печи',
        ];

        if (isset($stoveSchemaNames[$category->slug])) {
            $schemaNodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $stoveSchemaNames[$category->slug],
                'numberOfItems' => $products->count(),
                'itemListElement' => $products->values()->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => 'https://kotlov.by/'.$product->category->slug.'/'.$product->slug,
                ])->all(),
            ];
        }

        $schemaJson = json_encode(
            count($schemaNodes) === 1 ? $schemaNodes[0] : $schemaNodes,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $installerRecruitment = $this->installerRecruitmentContext($category);
        $catalogIntro = match ($category->slug) {
            'tverdotoplivnye' => 'Твердотопливные котлы для отопления дома на дровах, угле и пеллетах. Подберите модель по мощности, площади обогрева и типу топлива.',
            'kotly-na-pelletah' => 'Пеллетные котлы с автоматической подачей топлива для дома и коммерческих объектов. Сравните мощность, объём бункера, автоматику и доступность сервисного обслуживания.',
            'gazovye' => 'Газовые котлы для отопления и горячего водоснабжения. Подберите одноконтурную или двухконтурную модель по мощности, способу установки и типу камеры сгорания.',
            'elektricheskie' => 'Электрические котлы для основного или резервного отопления дома. Сравните мощность, площадь обогрева, число фаз и возможности автоматики.',
            'electric' => 'Электрические накопительные и проточные водонагреватели для квартиры, дома и дачи. Сравните объём, мощность, форму бака и способ установки.',
            'vodonagrevateli' => 'Водонагреватели для дома и квартиры: электрические, газовые, косвенные и комбинированные модели. Выберите тип нагрева, объём и способ установки.',
            'bufernye-emkosti' => 'Буферные ёмкости и теплоаккумуляторы для котлов и систем отопления. Сравните модели по объёму, конструкции и наличию теплообменника.',
            'kosvennye' => 'Бойлеры косвенного нагрева для горячего водоснабжения от котла или теплового насоса. Сравните модели по объёму, установке, материалу бака и числу теплообменников.',
            'pechki' => 'Отопительные и дровяные печи для дома и дачи. Сравните модели по мощности, площади обогрева, материалу и диаметру дымохода.',
            'pechi-kaminy' => 'Печи-камины для отопления дома с обзором пламени. Выбирайте по мощности, материалу, наличию варочной панели и подключению дымохода.',
            'pechi' => 'Дровяные печи для отопления дома, дачи и мастерской. Подберите печь по мощности, объёму помещения, материалу и диаметру дымохода.',
            'peci-drovianye-otopitelnye' => 'Дровяные отопительные печи для дома, дачи и мастерской. Сравните модели по мощности, площади обогрева, материалу и диаметру дымохода.',
            'kaminy' => 'Камины для дома: каминные топки, электрокамины, порталы и аксессуары. Сравните тип, размер, мощность и вариант монтажа.',
            'topki' => 'Каминные топки из стали и чугуна с прямым, угловым и трёхсторонним стеклом. Сравните ширину, мощность, материал и тип открывания дверцы.',
            'dymohody' => 'Дымоходы из нержавеющей стали для котлов, печей и каминов. Моно- и сэндвич-системы, крепления и фасонные элементы с подбором по диаметру.',
            'dymohody-mono' => 'Одностенные дымоходы Моно для прокладки внутри отапливаемых помещений и гильзования каналов. Сравните диаметр, марку и толщину стали.',
            'dymohody-sendvich' => 'Утеплённые сэндвич-дымоходы для наружных участков и проходов через перекрытия. Подберите систему по диаметру, толщине стали и слою изоляции.',
            'truby-i-fitingi' => 'Трубы и фитинги для систем отопления, водоснабжения и тёплого пола. Сравните материал, диаметр, тип соединения и рабочее давление.',
            'truby-iz-sshitogo-polietilena' => 'Трубы из сшитого полиэтилена для отопления и водяного тёплого пола. Подберите диаметр, толщину стенки и длину бухты.',
            'rezbovye-fitingi' => 'Резьбовые фитинги для разъёмных соединений труб: муфты, угольники, тройники и переходники. Сравните размер резьбы и материал.',
            'vodyanoy-teplyy-pol' => 'Комплектующие для водяного тёплого пола: трубы, фитинги и элементы подключения. Подберите совместимые компоненты системы.',
            'teplonositeli' => 'Теплоносители (антифризы) для систем отопления на этиленгликоле и пропиленгликоле: готовые растворы −30 и −65 °C и концентраты в канистрах 10 и 20 кг. Ниже — как выбрать основу и рассчитать нужный объём.',
            'schetchiki-gaza' => 'Газовые счетчики для квартиры и частного дома: диафрагменные и ультразвуковые модели БелОМО. Сравните типоразмер, направление потока и монтажную длину.',
            'press-fitingi' => 'Пресс-фитинги для быстрых неразъёмных соединений труб. Сравните диаметр, профиль прессования, материал и назначение.',
            'kompressionnye-fitingi' => 'Компрессионные фитинги для труб отопления и водоснабжения. Подберите тип соединения, диаметр и материал корпуса.',
            'krepleniya-dlya-trub' => 'Крепления для надёжной фиксации труб: клипсы, хомуты и опоры. Сравните диаметр, материал и способ монтажа.',
            'bani-i-sauny' => 'Печи и оборудование для бани и сауны. Подберите банную печь по объёму парной, материалу, типу каменки и выносу топки.',
            'drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye' => 'Дровяные печи для бани и сауны. Сравните модели по объёму парной, материалу, типу каменки и конструкции топочного канала.',
            default => null,
        };

        $catalogSpotlight = null;
        $catalogNavigationGroups = collect();

        if ($category->slug === 'komplektuyushhie-dlya-otopleniya') {
            $catalogNavigationGroups = $this->heatingAccessoriesNavigationGroups($selectedBrandId);
        }
        if ($category->slug === 'dymohody') {
            $teplovBrand = $brands->firstWhere('slug', 'teplov-i-suhov');

            if ($teplovBrand) {
                $catalogSpotlight = [
                    'eyebrow' => 'Основной ассортимент',
                    'title' => 'Дымоходы «Теплов и Сухов»',
                    'text' => 'Моно, сэндвич, переходы, ревизии и крепления одной модульной системы. Фильтр покажет весь доступный ассортимент бренда.',
                    'url' => url('/dymohody').'?brand='.$teplovBrand->id,
                    'button' => 'Все товары бренда',
                ];
            }
        }

        if ($category->slug === 'truby-i-fitingi') {
            $varmegaBrand = $brands->firstWhere('slug', 'varmega');

            if ($varmegaBrand) {
                $catalogSpotlight = [
                    'eyebrow' => 'Основной ассортимент',
                    'title' => 'Трубы и фитинги Varmega',
                    'text' => 'Пресс-фитинги, резьбовые и компрессионные соединения, трубы и комплектующие одной совместимой системы.',
                    'url' => url('/truby-i-fitingi').'?brand='.$varmegaBrand->id,
                    'button' => 'Все товары бренда',
                ];
            }
        }

        if ($category->slug === 'schetchiki-gaza') {
            $belomoBrand = $brands->firstWhere('slug', 'belomo');

            if ($belomoBrand) {
                $catalogSpotlight = [
                    'eyebrow' => 'Белорусский производитель',
                    'title' => 'Газовые счетчики БелОМО',
                    'text' => 'Бытовые диафрагменные СГД и СГМН, а также ультразвуковые СКАТ. Подберите исполнение по расходу газа, направлению потока и монтажной длине.',
                    'url' => url('/brands/belomo'),
                    'button' => 'Каталог БелОМО',
                ];
            }
        }

        if ($category->slug === 'bani-i-sauny') {
            $saunaStoves = $subcategories->first(
                fn (Category $subcategory) => in_array(
                    $subcategory->slug,
                    ['drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye'],
                    true
                )
            );

            if ($saunaStoves) {
                $catalogSpotlight = [
                    'eyebrow' => 'Главный раздел',
                    'title' => 'Печи для бани',
                    'text' => 'Дровяные и чугунные банные печи для парных разного объёма. Сравните материал, тип каменки и конструкцию топочного канала.',
                    'url' => url('/'.$saunaStoves->slug),
                    'button' => 'Смотреть банные печи',
                ];
            }
        }

        // Баннер акции ЭкоКамин в разделах каминов и топок.
        $ecokaminPromo = in_array($category->slug, ['kaminy', 'pechi-kaminy', 'topki'], true) || $category->parent?->slug === 'kaminy'
            ? \App\Support\Promotions\EcokaminPromotion::showcase()
            : null;
        // Свой вариант баннера: в топках — про топки Дельта, в печах-каминах — про камины.
        $ecokaminPromoVariant = match (true) {
            ! $ecokaminPromo => 'all',
            $category->slug === 'topki' || $category->parent?->slug === 'topki' => 'delta',
            $category->slug === 'pechi-kaminy' || $category->parent?->slug === 'pechi-kaminy' => 'fireplaces',
            default => 'all',
        };
        [$hitsKey, $discountKey] = match ($ecokaminPromoVariant) {
            'delta' => ['deltaHits', 'deltaMaxDiscount'],
            'fireplaces' => ['fireplaceHits', 'fireplaceMaxDiscount'],
            default => ['hits', 'maxDiscount'],
        };
        $ecokaminPromoHits = $ecokaminPromo[$hitsKey] ?? collect();
        $ecokaminPromoMaxDiscount = $ecokaminPromo[$discountKey] ?? 0;

        return view('pages.catalog', compact(
            'ecokaminPromoHits',
            'ecokaminPromoMaxDiscount',
            'ecokaminPromoVariant',
            'category',
            'subcategories',
            'brands',
            'filterAttributes',
            'chimneyDiameterGroups',
            'monoChimneyDiameterGroups',
            'chimneySteelGroups',
            'products',
            'priceMin',
            'priceMax',
            'totalCount',
            'allProductsCount',
            'title',
            'description',
            'keywords',
            'canonical',
            'schemaJson',
            'heatPumpArticles',
            'pelletBurnerFaq',
            'gasMeterFaq',
            'heatFluidFaq',
            'pelletPowerRanges',
            'pelletComparisonProducts',
            'installerRecruitment',
            'catalogIntro',
            'catalogSpotlight',
            'catalogNavigationGroups'
        ));
    }

    private function heatingAccessoriesNavigationGroups(?int $selectedBrandId): Collection
    {
        $definitions = [
            [
                'title' => 'Газовое оборудование и безопасность',
                'icon' => 'icon-ShieldCheck',
                'description' => 'Счётчики газа, контроль загазованности, газовые шланги и регуляторы давления.',
                'slugs' => [
                    'schetchiki-gaza',
                    'signalizatory-zagazovannosti',
                    'gazovye-shlangi',
                    'regulyatoryi-davleniya-gaza',
                ],
            ],
            [
                'title' => 'Обвязка котельной и арматура',
                'icon' => 'icon-GitDiff',
                'description' => 'Гидравлическая обвязка, распределение теплоносителя, защитная, радиаторная и смесительная арматура.',
                'slugs' => [
                    'gidravlicheskie-razdeliteli-i-kollektory',
                    'grebenki',
                    'gruppy-bystrogo-montazha-kotelnyx',
                    'montajnyie-komplektyi',
                    'predokhranitelnaya-i-reguliruyushchaya-armatura',
                    'radiatornaya-armatura',
                    'smesitelnaya-armatura',
                    'smesitelnye-klapany-i-uzly',
                    'kollektornye-shkafy',
                ],
            ],
            [
                'title' => 'Автоматика, питание и контроль',
                'icon' => 'icon-Devices',
                'description' => 'Управление отоплением, датчики и оборудование для стабильного электропитания.',
                'slugs' => [
                    'regulyatory',
                    'datchiki',
                    'stabilizatory-napryazheniya',
                    'istochniki-besperebojnogo-pitaniya',
                    'avtotransformatory-latr',
                ],
            ],
            [
                'title' => 'Баки, теплоносители и фильтрация',
                'icon' => 'icon-Package',
                'description' => 'Мембранные баки, жидкости для систем отопления и фильтры.',
                'slugs' => [
                    'membrannye-baki',
                    'teplonositeli',
                    'filtry',
                ],
            ],
            [
                'title' => 'Монтаж и подключение',
                'icon' => 'icon-PuzzlePiece',
                'description' => 'Готовые комплекты, подводка, воздуховоды и инструмент для монтажа.',
                'slugs' => [
                    'komplekty-podklyucheniya',
                    'gibkaya-podvodka-dlya-vody',
                    'zhestkaya-podvodka-dlya-vody',
                    'gibkie-vozduhovody',
                    'instrumenty-dlya-montazha',
                ],
            ],
            [
                'title' => 'Нагрев и солнечные системы',
                'icon' => 'icon-Sparkle',
                'description' => 'Электрические ТЭНы и оборудование для использования солнечной энергии.',
                'slugs' => [
                    'elektricheskie-teny-dlya-otopleniya',
                    'solnechnye-kollektory',
                ],
            ],
        ];

        $slugs = collect($definitions)->pluck('slugs')->flatten()->unique()->values();
        $categories = Category::query()
            ->whereIn('slug', $slugs)
            ->where('is_active', true)
            ->get()
            ->keyBy('slug');

        return collect($definitions)->map(function (array $group) use ($categories, $selectedBrandId): array {
            $items = collect($group['slugs'])->map(function (string $slug) use ($categories, $selectedBrandId) {
                $subcategory = $categories->get($slug);
                if (! $subcategory) {
                    return null;
                }

                $ids = $this->collectCategoryAndDescendantIds($subcategory->id);
                $subcategory->products_count = Product::query()
                    ->orderable()
                    ->whereIn('category_id', $ids)
                    ->when($selectedBrandId, fn ($query) => $query->where('brand_id', $selectedBrandId))
                    ->count();

                return $subcategory->products_count > 0 ? $subcategory : null;
            })->filter()->values();

            return [
                'title' => $group['title'],
                'icon' => $group['icon'],
                'description' => $group['description'],
                'items' => $items,
            ];
        })->filter(fn (array $group): bool => $group['items']->isNotEmpty())->values();
    }

    private function heatFluidFaqItems(): Collection
    {
        return collect([
            [
                'question' => 'Какой теплоноситель лучше: на этиленгликоле или пропиленгликоле?',
                'answer' => 'Этиленгликоль дешевле, у него ниже вязкость и выше теплоотдача, но он токсичен — его применяют только в закрытых системах без контакта с питьевой водой. Пропиленгликоль нетоксичен: его выбирают для двухконтурных котлов, систем с бойлером косвенного нагрева, открытых систем и пищевых объектов.',
            ],
            [
                'question' => 'Что выбрать: готовый раствор −30 °C, −65 °C или концентрат?',
                'answer' => 'Готовый −30 °C заливают без разбавления — это вариант для большинства домов Беларуси. Состав −65 °C берут для домов, которые зимой остаются без отопления, либо разбавляют водой до нужной температуры. Концентрат выгоднее всего: из 10 кг получается около 20 кг теплоносителя −30 °C, но разбавлять его нужно точно по таблице производителя дистиллированной водой.',
            ],
            [
                'question' => 'Сколько теплоносителя нужно для системы отопления?',
                'answer' => 'Сложите объём котла, радиаторов, труб и около трети объёма расширительного бака. Ориентиры: секция алюминиевого радиатора — 0,2–0,45 л, стальной радиатор 500×1000 тип 22 — 5–6 л, 10 м трубы 20 мм — около 2 л. Типичная система дома 100–150 м² вмещает 80–150 л. Помните, что в канистре 20 кг около 18–19 л, и берите запас 10–15 %.',
            ],
            [
                'question' => 'Можно ли смешивать теплоносители разных марок или доливать воду?',
                'answer' => 'Смешивать разные марки и составы на разной основе не рекомендуется: пакеты присадок могут конфликтовать и выпадать в осадок. Доливать воду в готовый раствор нельзя — повысится температура замерзания. Для доливки используйте тот же теплоноситель.',
            ],
            [
                'question' => 'Подходит ли антифриз для любого котла?',
                'answer' => 'Большинство газовых, твердотопливных, ТЭНовых и индукционных котлов допускают гликолевые теплоносители, но это нужно проверить в паспорте котла: некоторые производители требуют снижать мощность или запрещают антифриз. Электродные (ионные) котлы работают только со специальными теплоносителями с подходящей электропроводностью.',
            ],
            [
                'question' => 'Как часто нужно менять теплоноситель?',
                'answer' => 'Качественный теплоноситель сохраняет свойства 5–10 лет в зависимости от состава и условий эксплуатации. Раз в сезон проверяйте цвет, прозрачность и pH: помутнение, осадок, резкий запах или pH ниже 7 — признаки того, что присадки выработаны и жидкость пора заменить.',
            ],
        ]);
    }

    private function gasMeterFaqItems(): Collection
    {
        return collect([
            [
                'question' => 'Как выбрать газовый счетчик для квартиры?',
                'answer' => 'Сверьте максимальный суммарный расход газовых приборов, направление потока, межосевое расстояние и требования проекта газоснабжения. Для одной плиты часто рассматривают G1,6 или G2,5, но окончательное исполнение должен подтвердить проектировщик или газоснабжающая организация.',
            ],
            [
                'question' => 'Чем отличаются счетчики G1,6, G2,5, G4, G6 и G10?',
                'answer' => 'Маркировка обозначает типоразмер и диапазон расхода. Чем больше суммарное потребление подключенных приборов, тем выше требуется пропускная способность. Выбирать счетчик только по названию класса нельзя — ориентируйтесь на паспортные расходы оборудования и проект.',
            ],
            [
                'question' => 'Как определить левое или правое исполнение?',
                'answer' => 'Исполнение выбирают по направлению движения газа через счетчик и расположению входного патрубка. Перед заказом сравните направление стрелки, схему подключения и монтажную длину 110, 200 или 250 мм со старым прибором или проектом.',
            ],
            [
                'question' => 'Можно ли самостоятельно заменить счетчик газа?',
                'answer' => 'Нет. Монтаж, пломбирование и ввод прибора учета выполняются в установленном порядке уполномоченной организацией. До покупки согласуйте модель и комплект документов с обслуживающей газоснабжающей организацией.',
            ],
            [
                'question' => 'Есть ли у газовых счетчиков документы для установки в Беларуси?',
                'answer' => 'Для конкретной модификации необходимо проверить паспорт, сведения о поверке и документы о соответствии. Запросите комплект документов у менеджера до оплаты: требования могут различаться по модели и объекту.',
            ],
        ]);
    }

    private function visibleFilterSuffix(string $name, ?string $suffix): ?string
    {
        $suffix = trim((string) $suffix);

        if ($suffix === '') {
            return null;
        }

        $quotedSuffix = preg_quote($suffix, '/');
        $suffixAlreadyInName = preg_match(
            '/(?:\(\s*'.$quotedSuffix.'\s*\)|,\s*'.$quotedSuffix.')\s*$/ui',
            trim($name)
        ) === 1;

        return $suffixAlreadyInName ? null : $suffix;
    }

    private function installerRecruitmentContext(Category $category): ?array
    {
        $root = $category;

        while ($root->parent_id) {
            $parent = Category::query()->find($root->parent_id);

            if (! $parent) {
                break;
            }

            $root = $parent;
        }

        return match ($root->slug) {
            'kotly' => ['service' => 'монтажом котлов', 'category' => 'Котлы'],
            'dymohody' => ['service' => 'монтажом дымоходов', 'category' => 'Дымоходы'],
            'pechki', 'kaminy' => ['service' => 'монтажом печей и каминов', 'category' => 'Печи и камины'],
            'komplektuyushhie-dlya-otopleniya' => ['service' => 'монтажом систем отопления', 'category' => 'Отопление'],
            default => null,
        };
    }

    private function heatPumpFaqSchema(): array
    {
        $items = [
            'Как подобрать мощность теплового насоса?' => 'Мощность подбирают по расчётным теплопотерям здания, а не только по площади. Учитывают утепление, окна, вентиляцию, температуру воздуха зимой, отопительные приборы, горячее водоснабжение и доступную электрическую мощность.',
            'Подходит ли тепловой насос для тёплого пола?' => 'Да. Водяной тёплый пол работает с низкой температурой подачи и создаёт благоприятные условия для эффективной работы теплового насоса.',
            'Можно ли подключить тепловой насос к радиаторам?' => 'Можно, если проверить теплоотдачу радиаторов при расчётной температуре подачи. Для высокотемпературных систем рассматривают увеличенные радиаторы или модели на R290.',
            'Чем отличаются тепловые насосы R32 и R290?' => 'R32 хорошо подходит для современных низкотемпературных систем. Линейка KOTLOV GE на R290 рассчитана в том числе на более высокую температуру подачи, поэтому её рассматривают для радиаторов и горячего водоснабжения.',
            'Нужен ли резервный источник отопления?' => 'Решение принимают по теплопотерям, расчётной температуре региона и требованиям к надёжности. Резервный котёл или встроенный электрический нагреватель может покрывать пики нагрузки и использоваться во время обслуживания.',
            'От чего зависит расход электричества?' => 'От температуры наружного воздуха и подачи, теплопотерь дома, режима горячего водоснабжения, настройки автоматики и качества монтажа. Чем ниже требуемая температура воды, тем выше потенциальная эффективность системы.',
        ];

        return collect($items)->map(fn (string $answer, string $question) => [
            '@type' => 'Question',
            'name' => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $answer,
            ],
        ])->values()->all();
    }

    private function pelletBurnerFaqItems(): Collection
    {
        $items = [
            'Как подобрать мощность пеллетной горелки?' => 'Мощность подбирают по теплопотерям здания и рабочему диапазону котла. Горелка должна уверенно покрывать расчётную нагрузку, но не работать постоянно на минимальной мощности.',
            'Можно ли установить пеллетную горелку в существующий котёл?' => 'Во многих твердотопливных котлах это возможно после проверки размеров топки, дверцы, теплообменника, тяги дымохода и места для шнека. Совместимость нужно подтвердить до покупки.',
            'Что входит в комплект автоматизации?' => 'Комплектация зависит от модели. Обычно система включает горелку, контроллер, вентилятор, шнек подачи и датчики. Бункер, защита от обратного пламени и дополнительная автоматика могут поставляться отдельно.',
            'Как качество пеллет влияет на работу?' => 'Зольность, влажность и фракция влияют на стабильность горения, расход топлива и частоту очистки. Настройки автоматики корректируют под конкретное топливо.',
            'Как часто нужно обслуживать горелку?' => 'Периодичность зависит от качества пеллет, режима работы и конструкции горелки. Необходимо регулярно очищать зону горения и теплообменник, проверять подачу топлива и состояние дымохода.',
            'Можно ли заказать подбор и монтаж?' => 'Да. Специалист KOTLOV проверит котёл, требуемую мощность, дымоход и компоновку котельной, после чего предложит совместимый комплект и вариант монтажа.',
        ];

        return collect($items)->map(fn (string $answer, string $question) => [
            'question' => $question,
            'answer' => $answer,
        ])->values();
    }

    private function applyPelletPowerRange($query, Collection $attributeIds, string $rangeKey): void
    {
        $range = self::PELLET_POWER_RANGES[$rangeKey] ?? null;

        if (! $range || $attributeIds->isEmpty()) {
            return;
        }

        $query->whereHas('allAttributeValues', function ($attributeQuery) use ($attributeIds, $range) {
            $attributeQuery->whereIn('attribute_id', $attributeIds);
            $numericValue = "CAST(REPLACE(TRIM(SUBSTRING_INDEX(value, '-', -1)), ',', '.') AS DECIMAL(10,2))";

            if ($range['min'] !== null) {
                $attributeQuery->whereRaw($numericValue.' > ?', [$range['min']]);
            }
            if ($range['max'] !== null) {
                $attributeQuery->whereRaw($numericValue.' <= ?', [$range['max']]);
            }
        });
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
