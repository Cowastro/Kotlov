<?php

namespace App\Providers;

use App\Database\RetryingMySqlConnector;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\WishlistItem;
use App\Services\B2bCatalogOfferResolver;
use App\Services\SupplierSyncJournalRecorder;
use App\Support\AdminRoleAccess;
use App\Support\Catalog\WaterHeaterCatalog;
use App\Support\AdminSurfaceRequest;
use App\Support\SchemaPresence;
use App\Support\TransactionalMailbox;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('db.connector.mysql', fn (): RetryingMySqlConnector => new RetryingMySqlConnector);
        $this->app->scoped(B2bCatalogOfferResolver::class);
        $this->app->afterResolving('mail.manager', fn ($manager) => TransactionalMailbox::configure($manager));
    }

    public function boot(SupplierSyncJournalRecorder $syncJournal): void
    {
        Gate::before(fn (\App\Models\User $user, string $ability, array $arguments): ?bool =>
            AdminRoleAccess::authorize($user, $ability, $arguments));

        if (app()->runningInConsole()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event) use ($syncJournal): void {
                $syncJournal->start($event->command, $event->input);
            });
            Event::listen(CommandFinished::class, function (CommandFinished $event) use ($syncJournal): void {
                $syncJournal->finish($event->command, $event->exitCode);
            });
        }

        View::composer([
            'partials.amerce-header',
            'partials.modals',
            'partials.product-card',
            'partials.product-card-list',
            'pages.product',
        ], function ($view): void {
            if (! Schema::hasTable('wishlist_items')) {
                $view->with([
                    'wishlistCount' => 0,
                    'wishlistProductIds' => [],
                ]);

                return;
            }

            $request = request();
            $state = $request->attributes->get('wishlist_state');

            if ($state === null) {
                $query = WishlistItem::query();

                if (auth()->check()) {
                    $query->where('user_id', auth()->id());
                } else {
                    $query->where('session_id', session()->getId());
                }

                $productIds = $query->pluck('product_id')
                    ->map(fn ($productId): int => (int) $productId)
                    ->values()
                    ->all();

                $state = [
                    'count' => count($productIds),
                    'product_ids' => $productIds,
                ];
                $request->attributes->set('wishlist_state', $state);
            }

            $view->with([
                'wishlistCount' => (int) $state['count'],
                'wishlistProductIds' => $state['product_ids'],
            ]);
        });

        // Пропускаем для реальных консольных команд (artisan/queue), но НЕ для
        // тестов: под `php artisan test` runningInConsole() == true, а вьюхи всё
        // равно рендерятся HTTP-запросами и требуют navCategories и т.п.
        if ((app()->runningInConsole() && ! app()->runningUnitTests()) || AdminSurfaceRequest::matches(request())) {
            return;
        }

        // Redirects, JSON and rejected crawl URLs must not open a database
        // connection just to prepare a menu that will never be rendered.
        View::composer(['pages.*', 'partials.*', 'layouts.amerce'], function (): void {
            $request = request();
            if (AdminSurfaceRequest::matches($request)
                || $request->attributes->get('storefront_shared_data_loaded')) {
                return;
            }
            if (app()->runningUnitTests() && ! Schema::hasTable('categories')) {
                return;
            }
            $request->attributes->set('storefront_shared_data_loaded', true);
            $navCategories = $this->getNavCategories();
            View::share('navCategories', $navCategories);
            View::share('navCategoryUrls', $this->getNavCategoryUrls($navCategories));
            View::share('navBrands', $this->getNavBrands());
            $citySelector = $this->getCitySelectorState();
            $cities = $citySelector['regions'];
            View::share('citySelectorRegions', $cities);
            View::share('citySelectorEnabled', $citySelector['enabled']);
            View::share('obl1', $cities['Минская']);
            View::share('obl2', $cities['Гомельская'] ?? []);
            View::share('obl3', $cities['Брестская'] ?? []);
            View::share('obl4', $cities['Гродненская'] ?? []);
            View::share('obl5', $cities['Могилёвская'] ?? []);
            View::share('obl6', $cities['Витебская'] ?? []);
        });
    }

    /** @return array{regions: array<string, array<int, array<string, string>>>, enabled: bool} */
    private function getCitySelectorState(): array
    {
        $regions = array_filter(
            $this->getCitiesByRegion(),
            fn (array $cities): bool => $cities !== [],
        );
        $cityCount = array_sum(array_map('count', $regions));

        return [
            'regions' => $regions,
            'enabled' => $cityCount > 1,
        ];
    }


private function getCitiesByRegion(): array
{
    $all = [
        // Минская область
        ['slug' => 'berezino',       'name' => 'Березино',      'region' => 'Минская'],
        ['slug' => 'borisov',        'name' => 'Борисов',        'region' => 'Минская'],
        ['slug' => 'vileyka',        'name' => 'Вилейка',        'region' => 'Минская'],
        ['slug' => 'volojin',        'name' => 'Воложин',        'region' => 'Минская'],
        ['slug' => 'dzerjinsk',      'name' => 'Дзержинск',      'region' => 'Минская'],
        ['slug' => 'jodino',         'name' => 'Жодино',         'region' => 'Минская'],
        ['slug' => 'zaslavl',        'name' => 'Заславль',       'region' => 'Минская'],
        ['slug' => 'kletsk',         'name' => 'Клецк',          'region' => 'Минская'],
        ['slug' => 'kopyil',         'name' => 'Копыль',         'region' => 'Минская'],
        ['slug' => 'krupki',         'name' => 'Крупки',         'region' => 'Минская'],
        ['slug' => 'logoysk',        'name' => 'Логойск',        'region' => 'Минская'],
        ['slug' => 'lyuban',         'name' => 'Любань',         'region' => 'Минская'],
        ['slug' => 'marina-gorka',   'name' => 'Марьина Горка',  'region' => 'Минская'],
        ['slug' => '',               'name' => 'Минск',          'region' => 'Минская'], // главный домен
        ['slug' => 'molodechno',     'name' => 'Молодечно',      'region' => 'Минская'],
        ['slug' => 'myadel',         'name' => 'Мядель',         'region' => 'Минская'],
        ['slug' => 'nesvij',         'name' => 'Несвиж',         'region' => 'Минская'],
        ['slug' => 'slutsk',         'name' => 'Слуцк',          'region' => 'Минская'],
        ['slug' => 'smolevichi',     'name' => 'Смолевичи',      'region' => 'Минская'],
        ['slug' => 'soligorsk',      'name' => 'Солигорск',      'region' => 'Минская'],
        ['slug' => 'staryie-dorogi', 'name' => 'Старые Дороги',  'region' => 'Минская'],
        ['slug' => 'stolbtsyi',      'name' => 'Столбцы',        'region' => 'Минская'],
        ['slug' => 'uzda',           'name' => 'Узда',           'region' => 'Минская'],
        ['slug' => 'cherven',        'name' => 'Червень',        'region' => 'Минская'],
        // Гомельская область
        ['slug' => 'buda-koshelevo', 'name' => 'Буда-Кошелёво', 'region' => 'Гомельская'],
        ['slug' => 'vasilevichi',    'name' => 'Василевичи',    'region' => 'Гомельская'],
        ['slug' => 'vetka',          'name' => 'Ветка',         'region' => 'Гомельская'],
        ['slug' => 'gomel',          'name' => 'Гомель',        'region' => 'Гомельская'],
        ['slug' => 'dobrush',        'name' => 'Добруш',        'region' => 'Гомельская'],
        ['slug' => 'elsk',           'name' => 'Ельск',         'region' => 'Гомельская'],
        ['slug' => 'jitkovichi',     'name' => 'Житковичи',     'region' => 'Гомельская'],
        ['slug' => 'jlobin',         'name' => 'Жлобин',        'region' => 'Гомельская'],
        ['slug' => 'kalinkovichi',   'name' => 'Калинковичи',   'region' => 'Гомельская'],
        ['slug' => 'mozyir',         'name' => 'Мозырь',        'region' => 'Гомельская'],
        ['slug' => 'narovlya',       'name' => 'Наровля',       'region' => 'Гомельская'],
        ['slug' => 'petrikov',       'name' => 'Петриков',      'region' => 'Гомельская'],
        ['slug' => 'rechitsa',       'name' => 'Речица',        'region' => 'Гомельская'],
        ['slug' => 'rogachev',       'name' => 'Рогачёв',       'region' => 'Гомельская'],
        ['slug' => 'svetlogorsk',    'name' => 'Светлогорск',   'region' => 'Гомельская'],
        ['slug' => 'turov',          'name' => 'Туров',         'region' => 'Гомельская'],
        ['slug' => 'hoyniki',        'name' => 'Хойники',       'region' => 'Гомельская'],
        ['slug' => 'chechersk',      'name' => 'Чечерск',       'region' => 'Гомельская'],
        // Брестская область
        ['slug' => 'baranovichi',    'name' => 'Барановичи',    'region' => 'Брестская'],
        ['slug' => 'beloozersk',     'name' => 'Белоозёрск',    'region' => 'Брестская'],
        ['slug' => 'bereza',         'name' => 'Берёза',        'region' => 'Брестская'],
        ['slug' => 'brest',          'name' => 'Брест',         'region' => 'Брестская'],
        ['slug' => 'vyisokoe',       'name' => 'Высокое',       'region' => 'Брестская'],
        ['slug' => 'gantsevichi',    'name' => 'Ганцевичи',     'region' => 'Брестская'],
        ['slug' => 'david-gorodok',  'name' => 'Давид-Городок', 'region' => 'Брестская'],
        ['slug' => 'drogichin',      'name' => 'Дрогичин',      'region' => 'Брестская'],
        ['slug' => 'jabinka',        'name' => 'Жабинка',       'region' => 'Брестская'],
        ['slug' => 'ivanovo',        'name' => 'Иваново',       'region' => 'Брестская'],
        ['slug' => 'ivatsevichi',    'name' => 'Ивацевичи',     'region' => 'Брестская'],
        ['slug' => 'kamenets',       'name' => 'Каменец',       'region' => 'Брестская'],
        ['slug' => 'kobrin',         'name' => 'Кобрин',        'region' => 'Брестская'],
        ['slug' => 'luninets',       'name' => 'Лунинец',       'region' => 'Брестская'],
        ['slug' => 'lyahovichi',     'name' => 'Ляховичи',      'region' => 'Брестская'],
        ['slug' => 'malorita',       'name' => 'Малорита',      'region' => 'Брестская'],
        ['slug' => 'mikashevichi',   'name' => 'Микашевичи',    'region' => 'Брестская'],
        ['slug' => 'pinsk',          'name' => 'Пинск',         'region' => 'Брестская'],
        ['slug' => 'prujanyi',       'name' => 'Пружаны',       'region' => 'Брестская'],
        ['slug' => 'stolin',         'name' => 'Столин',        'region' => 'Брестская'],
        // Гродненская область
        ['slug' => 'berezovka',      'name' => 'Берёзовка',     'region' => 'Гродненская'],
        ['slug' => 'volkovyisk',     'name' => 'Волковыск',     'region' => 'Гродненская'],
        ['slug' => 'grodno',         'name' => 'Гродно',        'region' => 'Гродненская'],
        ['slug' => 'dyatlovo',       'name' => 'Дятлово',       'region' => 'Гродненская'],
        ['slug' => 'ive',            'name' => 'Ивье',          'region' => 'Гродненская'],
        ['slug' => 'lida',           'name' => 'Лида',          'region' => 'Гродненская'],
        ['slug' => 'mostyi',         'name' => 'Мосты',         'region' => 'Гродненская'],
        ['slug' => 'novogrudok',     'name' => 'Новогрудок',    'region' => 'Гродненская'],
        ['slug' => 'ostrovets',      'name' => 'Островец',      'region' => 'Гродненская'],
        ['slug' => 'oshmyanyi',      'name' => 'Ошмяны',        'region' => 'Гродненская'],
        ['slug' => 'svisloch',       'name' => 'Свислочь',      'region' => 'Гродненская'],
        ['slug' => 'skidel',         'name' => 'Скидель',       'region' => 'Гродненская'],
        ['slug' => 'slonim',         'name' => 'Слоним',        'region' => 'Гродненская'],
        ['slug' => 'smorgon',        'name' => 'Сморгонь',      'region' => 'Гродненская'],
        ['slug' => 'schuchin',       'name' => 'Щучин',         'region' => 'Гродненская'],
        // Могилёвская область
        ['slug' => 'belyinichi',     'name' => 'Белыничи',      'region' => 'Могилёвская'],
        ['slug' => 'bobruysk',       'name' => 'Бобруйск',      'region' => 'Могилёвская'],
        ['slug' => 'byihov',         'name' => 'Быхов',         'region' => 'Могилёвская'],
        ['slug' => 'gorki',          'name' => 'Горки',         'region' => 'Могилёвская'],
        ['slug' => 'kirovsk',        'name' => 'Кировск',       'region' => 'Могилёвская'],
        ['slug' => 'klimovichi',     'name' => 'Климовичи',     'region' => 'Могилёвская'],
        ['slug' => 'klichev',        'name' => 'Кличев',        'region' => 'Могилёвская'],
        ['slug' => 'kostyukovichi',  'name' => 'Костюковичи',   'region' => 'Могилёвская'],
        ['slug' => 'krichev',        'name' => 'Кричев',        'region' => 'Могилёвская'],
        ['slug' => 'krugloe',        'name' => 'Круглое',       'region' => 'Могилёвская'],
        ['slug' => 'mogilev',        'name' => 'Могилёв',       'region' => 'Могилёвская'],
        ['slug' => 'mstislavl',      'name' => 'Мстиславль',    'region' => 'Могилёвская'],
        ['slug' => 'osipovichi',     'name' => 'Осиповичи',     'region' => 'Могилёвская'],
        ['slug' => 'slavgorod',      'name' => 'Славгород',     'region' => 'Могилёвская'],
        ['slug' => 'chausyi',        'name' => 'Чаусы',         'region' => 'Могилёвская'],
        ['slug' => 'cherikov',       'name' => 'Чериков',       'region' => 'Могилёвская'],
        ['slug' => 'shklov',         'name' => 'Шклов',         'region' => 'Могилёвская'],
        // Витебская область
        ['slug' => 'baran',          'name' => 'Барань',        'region' => 'Витебская'],
        ['slug' => 'braslav',        'name' => 'Браслав',       'region' => 'Витебская'],
        ['slug' => 'verhnedvinsk',   'name' => 'Верхнедвинск',  'region' => 'Витебская'],
        ['slug' => 'vitebsk',        'name' => 'Витебск',       'region' => 'Витебская'],
        ['slug' => 'glubokoe',       'name' => 'Глубокое',      'region' => 'Витебская'],
        ['slug' => 'gorodok',        'name' => 'Городок',       'region' => 'Витебская'],
        ['slug' => 'disna',          'name' => 'Дисна',         'region' => 'Витебская'],
        ['slug' => 'dokshitsyi',     'name' => 'Докшицы',       'region' => 'Витебская'],
        ['slug' => 'dubrovno',       'name' => 'Дубровно',      'region' => 'Витебская'],
        ['slug' => 'lepel',          'name' => 'Лепель',        'region' => 'Витебская'],
        ['slug' => 'mioryi',         'name' => 'Миоры',         'region' => 'Витебская'],
        ['slug' => 'novolukoml',     'name' => 'Новолукомль',   'region' => 'Витебская'],
        ['slug' => 'novopolotsk',    'name' => 'Новополоцк',    'region' => 'Витебская'],
        ['slug' => 'orsha',          'name' => 'Орша',          'region' => 'Витебская'],
        ['slug' => 'polotsk',        'name' => 'Полоцк',        'region' => 'Витебская'],
        ['slug' => 'postavyi',       'name' => 'Поставы',       'region' => 'Витебская'],
        ['slug' => 'senno',          'name' => 'Сенно',         'region' => 'Витебская'],
        ['slug' => 'tolochin',       'name' => 'Толочин',       'region' => 'Витебская'],
        ['slug' => 'chashniki',      'name' => 'Чашники',       'region' => 'Витебская'],
    ];

    $grouped = ['Минская' => [], 'Гомельская' => [], 'Брестская' => [],
                'Гродненская' => [], 'Могилёвская' => [], 'Витебская' => []];

    $activeCitySlugs = null;
    if (SchemaPresence::table('cities')) {
        $activeCities = City::query()->where('is_active', true);
        $activeCitySlugs = $activeCities->pluck('slug')->all();
    }

    foreach ($all as $city) {
        if ($city['slug'] !== ''
            && $activeCitySlugs !== null
            && ! in_array($city['slug'], $activeCitySlugs, true)) {
            continue;
        }

        $selectorSlug = $city['slug'] ?: 'minsk';
        $grouped[$city['region']][] = [
            'name'   => $city['name'],
            'slug'   => $selectorSlug,
            'active' => '',
        ];
    }

    return $grouped;
}

private function getNavBrands(): \Illuminate\Support\Collection
{
    return Cache::remember('nav:brands', 3600, function () {
        return Brand::active()
            ->orderBy('sort_order')
            ->get(['name', 'slug'])
            ->keyBy('slug');
    });
}

private function getNavCategories()
{
    return Cache::remember('nav:categories:orderable:v5', 3600, function () {
        // A category whose URL is the source of an active redirect must not be
        // linked from the menu: every such link costs crawlers and visitors an
        // extra 301 hop.
        $redirectedPaths = DB::table('redirects')
            ->where('is_active', 1)
            ->pluck('from_url')
            ->map(fn ($url) => '/' . trim((string) $url, '/'))
            ->flip();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('sort_order')
            ->get();

        // CatalogController redirects the legacy /gas category to the gas water
        // heater section whenever that section is active.
        if ($categories->contains('slug', WaterHeaterCatalog::GAS)) {
            $redirectedPaths->put('/gas', true);
        }

        $categories = $categories
            ->reject(fn (Category $category) => $redirectedPaths->has('/' . trim((string) $category->slug, '/')));

        $categoriesByParent = $categories->groupBy('parent_id');
        $directProductCounts = Product::query()
            ->orderable()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $branchCounts = [];
        $countBranchProducts = function (int $categoryId) use (&$countBranchProducts, &$branchCounts, $categoriesByParent, $directProductCounts): int {
            if (array_key_exists($categoryId, $branchCounts)) {
                return $branchCounts[$categoryId];
            }

            $count = (int) ($directProductCounts[$categoryId] ?? 0);

            foreach ($categoriesByParent->get($categoryId, collect()) as $child) {
                $count += $countBranchProducts((int) $child->id);
            }

            return $branchCounts[$categoryId] = $count;
        };

        $decorateTree = function (Category $category) use (&$decorateTree, $categoriesByParent, $countBranchProducts): Category {
            $category->products_count = $countBranchProducts((int) $category->id);

            $children = $categoriesByParent
                ->get($category->id, collect())
                ->values()
                ->map(fn (Category $child) => $decorateTree($child))
                ->filter(fn (Category $child) => $child->products_count > 0)
                ->values();

            $category->setRelation('children', $children);

            return $category;
        };

        return $categoriesByParent
            ->get(0, collect())
            ->sortBy('sort_order')
            ->values()
            ->map(fn (Category $category) => $decorateTree($category))
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values();
    });
}

private function getNavCategoryUrls($categories): array
{
    $urls = [];

    $walk = function ($items) use (&$walk, &$urls): void {
        foreach ($items as $category) {
            $slug = trim((string) $category->slug, '/');

            if ($slug !== '') {
                $urls['/' . $slug] = true;
            }

            $children = $category->relationLoaded('children')
                ? $category->children
                : collect();

            if ($children->isNotEmpty()) {
                $walk($children);
            }
        }
    };

    $walk($categories);

    return $urls;
}
}
