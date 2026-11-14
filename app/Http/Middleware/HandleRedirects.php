<?php

namespace App\Http\Middleware;

use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\ProductController;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\InstallerProfile;
use App\Models\PartnerBranch;
use App\Models\Product;
use Closure;
use App\Support\Catalog\BoilerCatalog;
use App\Support\Catalog\WaterHeaterCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class HandleRedirects
{
    private const CITY_ALIASES = [
        'rechitsa' => 'rechica',
        'volojin' => 'volozhin',
        'jitkovichi' => 'zhitkovichi',
        'jlobin' => 'zhlobin',
        'logoysk' => 'logojsk',
        'dzerjinsk' => 'dzerzhinsk',
        'jodino' => 'zhodino',
        'kopyil' => 'kopyl',
        'belyinichi' => 'belynichi',
        'polotsk' => 'polack',
        'novopolotsk' => 'novopolock',
        'mioryi' => 'miory',
        'nesvij' => 'nesvizh',
        'staryie-dorogi' => 'starye-dorogi',
        'stolbtsyi' => 'stolbcy',
        'volkovyisk' => 'volkovysk',
        'oshmyanyi' => 'oshnyany',
        'schuchin' => 'shchuchin',
        'byihov' => 'byhov',
        'dokshitsyi' => 'dokshicy',
        'prujanyi' => 'pruzhany',
    ];

    /**
     * Old URLs often pass through several rules (/kotly/gazovye/x/page:5 → /kotly/gazovye/x → /gazovye/x → /gazovye).
     * Follow the chain here and answer with the final address in one 301, so search engines move the old page's
     * signals straight to the live one. Only same-host hops are followed; the first status code is kept.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('catalog-icons/*')) {
            return $next($request);
        }
        $response = $this->resolve($request, $next);
        if (! $response->isRedirection() || ! $request->isMethod('GET')) {
            return $response;
        }

        $host = $request->getHost();
        $baseDomain = (string) config('app.base_domain', 'kotlov.by');
        $location = (string) $response->headers->get('Location');
        $seen = [$request->getUri() => true];
        $passed = new Response('', 299);
        for ($hop = 0; $hop < 5; $hop++) {
            $url = str_starts_with($location, '/') ? $request->getSchemeAndHttpHost().$location : $location;
            $hopHost = (string) parse_url($url, PHP_URL_HOST);
            if (isset($seen[$url]) || ($hopHost !== $host && ! str_ends_with('.'.$hopHost, '.'.$baseDomain))) {
                break;
            }
            $seen[$url] = true;
            $hopRequest = Request::create($url, 'GET', [], [], [], ['HTTPS' => $request->isSecure() ? 'on' : 'off']);
            // redirect('/path') builds the address from the URL generator's request — point it at this hop's host.
            app('url')->setRequest($hopRequest);
            try {
                $next = $this->resolve($hopRequest, fn () => $passed);
            } finally {
                app('url')->setRequest($request);
            }
            $nextLocation = $next !== $passed && $next->isRedirection() ? (string) $next->headers->get('Location') : '';
            if ($nextLocation === '') {
                break;
            }
            $location = $nextLocation;
        }
        $response->headers->set('Location', $location);

        return $response;
    }

    private function resolve(Request $request, Closure $next): Response
    {
        // Проверяем ДО роутинга — не ждём 404
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $path = rtrim($path, '/') ?: '/';

        if ($cityRedirect = $this->redirectCityAlias($request)) {
            return $cityRedirect;
        }

        if ($cityOwnedPageRedirect = $this->redirectCityOwnedPageToBaseDomain($request, $path)) {
            return $cityOwnedPageRedirect;
        }

        // Исключаем служебные пути
        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        if ($cityProductRedirect = $this->redirectCityProductToBaseDomain($request, $path)) {
            return $cityProductRedirect;
        }

        if ($percentRedirect = $this->redirectPercentPath($request, $path)) {
            return $percentRedirect;
        }

        // Exact redirect mappings must win over broad legacy-path rules.
        // Keep currently valid product URLs untouched even if an obsolete
        // redirect row with the same source still exists in the database.
        if (! $this->isCanonicalProductPath($request, $path)) {
            $redirect = DB::table('redirects')
                ->where('from_url', $path)
                ->where('is_active', 1)
                ->select('to_url', 'status_code')
                ->first();

            if ($redirect) {
                $query = $request->getQueryString();
                $target = $redirect->to_url.($query ? '?'.$query : '');
                if (! $this->redirectTargetLoopsToProductPath($path, (string) $redirect->to_url)) {
                    return $this->redirectToPath($request, $target, $redirect->status_code ?? 301);
                }
            }
        }

        if (preg_match('#^(.*)/page:(\d+)$#', $path, $matches)) {
            $basePath = rtrim($matches[1], '/') ?: '/';
            $query = $request->getQueryString();
            $target = $basePath.($query ? '?'.$query : '');

            if ($target !== $path) {
                return $this->redirectToPath($request, $target);
            }
        }

        if (preg_match('#^(.+)/shop-default\.html$#', $path, $matches)) {
            $target = rtrim($matches[1], '/') ?: '/';
            $query = $request->getQueryString();

            return $this->redirectToPath($request, $target.($query ? '?'.$query : ''));
        }

        // Resolve a legacy product by its slug before applying broad section
        // rewrites. Otherwise a valid old product can be sent to a guessed
        // category path and end in another 404.
        if ($legacyProductRedirect = $this->redirectLegacyProductPath($request, $path)) {
            return $legacyProductRedirect;
        }

        // ── Паттерн-редиректы для старого сайта kotlov.by ────────────────────
        //
        // Логика старого сайта (RoutMap.php):
        //   2 сегмента: /{section}/{product-slug}   → routeTwo  (товар напрямую в разделе)
        //   3 сегмента: /{section}/{subcat}/{slug}   → routeTree (товар в подразделе)
        //
        // Логика нового сайта:
        //   Разделы-агрегаторы (/otoplenie, /kaminy, /vodonagrevateli) не содержат товаров.
        //   Товары и подкатегории живут на уровень выше: /{subcat}/{slug}
        //
        // Порядок важен: более специфичные паттерны — выше.
        $legacyPatterns = [
            // Empty legacy aggregate was retired when the stove catalogue was
            // restructured. Preserve its accumulated signals on the live root.
            '~^/dlya-dachi$~' => '/pechki',

            // ── Тепловые насосы ───────────────────────────────────────────────
            // /teplovye-nasosy → /teplovyie-nasosyi (старое написание без iye)
            '~^/teplovye-nasosy/(.+)$~' => '/teplovyie-nasosyi/$1',
            '~^/teplovye-nasosy$~' => '/teplovyie-nasosyi',
            '~^/akkumuliruyushhie-baki(/.*)?$~' => '/bufernye-emkosti$1',

            // ── /kotly ────────────────────────────────────────────────────────
            // /kotly/teplovyie-nasosyi → /teplovyie-nasosyi (старый URL тепловых насосов)
            '~^/kotly/teplovyie-nasosyi/(.+)$~' => '/teplovyie-nasosyi/$1',
            '~^/kotly/teplovyie-nasosyi$~' => '/teplovyie-nasosyi',
            // Старая вложенная страница пеллетных котлов больше не должна
            // оставаться пустым дублем актуальной плоской категории.
            '~^/kotly/tverdotoplivnye/kotly-na-pelletah$~' => '/kotly-na-pelletah',
            '~^/tverdotoplivnye/kotly-na-pelletah$~' => '/kotly-na-pelletah',
            // /kotly/{cat}/{slug} → /{cat}/{slug}
            '~^/kotly/(gazovye|tverdotoplivnye|elektricheskie)/(.+)$~' => '/$1/$2',
            '~^/kotly/(gazovye|tverdotoplivnye|elektricheskie)$~' => '/$1',

            // ── /otoplenie ────────────────────────────────────────────────────
            // /otoplenie/{subcat}/{slug} → /{subcat}/{slug}  (3 сегмента — товар в подразделе)
            '~^/otoplenie/([a-z0-9][a-z0-9\-_]+)/(.+)$~' => '/$1/$2',
            // /otoplenie/{subcat} → /{subcat}  (2 сегмента — страница подраздела)
            '~^/otoplenie/([a-z0-9][a-z0-9\-_]+)$~' => '/$1',

            // ── /kaminy ───────────────────────────────────────────────────────
            // /kaminy/{subcat}/{slug} → /{subcat}/{slug}  (topki/elektrokamini/oblicovki)
            '~^/kaminy/(topki|elektrokamini|oblicovki|bio-kaminy)/(.+)$~' => '/$1/$2',
            // /kaminy/{subcat} → /{subcat}  (страница подраздела)
            '~^/kaminy/(topki|elektrokamini|oblicovki|bio-kaminy)$~' => '/$1',
            // /kaminy/{product-slug} → /topki/{slug}  (товар напрямую в /kaminy)
            '~^/kaminy/([a-z0-9][a-z0-9\-_\.]+)$~' => '/topki/$1',

            // ── /vodonagrevateli ──────────────────────────────────────────────
            // /vodonagrevateli/{subcat}/{slug} → /{subcat}/{slug}
            '~^/vodonagrevateli/([a-z0-9][a-z0-9\-_]+)/(.+)$~' => '/$1/$2',
            // /vodonagrevateli/{subcat} → /{subcat}
            '~^/vodonagrevateli/([a-z0-9][a-z0-9\-_]+)$~' => '/$1',

            // ── /dlya-bani ────────────────────────────────────────────────────
            // /dlya-bani/{slug} → /pechi-dlya-bani/{slug}
            '~^/dlya-bani/(.+)$~' => '/pechi-dlya-bani/$1',

            // ── Пеллетные горелки ─────────────────────────────────────────────
            // /otoplenie-parts/pelletnyie-gorelki/{slug} → /pelletnye-gorelki/{slug}
            '~^/otoplenie-parts/pelletnyie-gorelki/(.+)$~' => '/pelletnye-gorelki/$1',
            '~^/otoplenie-parts/pelletnyie-gorelki$~' => '/pelletnye-gorelki',
            // /pelletnyie-gorelki → /pelletnye-gorelki (старый slug с опечаткой)
            '~^/pelletnyie-gorelki/(.+)$~' => '/pelletnye-gorelki/$1',
            '~^/pelletnyie-gorelki$~' => '/pelletnye-gorelki',

            // ── /pechi-kaminy-parts, /otoplenie-parts ─────────────────────────
            '~^/otoplenie-parts/(.+)$~' => '/$1',
            '~^/pechi-kaminy-parts/(.+)$~' => '/$1',
        ];

        foreach ($legacyPatterns as $pattern => $replacement) {
            if (preg_match($pattern, $path)) {
                $newPath = preg_replace($pattern, $replacement, $path);
                $query = $request->getQueryString();
                $target = $newPath.($query ? '?'.$query : '');

                return $this->redirectToPath($request, $target);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        if ($legacyCategoryRedirect = $this->redirectLegacyCategoryPath($request, $path)) {
            return $legacyCategoryRedirect;
        }

        if ($legacyBrandCategoryRedirect = $this->redirectLegacyBrandCategoryPath($request, $path)) {
            return $legacyBrandCategoryRedirect;
        }

        // Run this after every known legacy redirect. An obsolete city URL
        // that still has a verified replacement must keep that replacement;
        // only unresolved city-host crawl noise should become 410 Gone.
        if ($inactiveCityResponse = $this->redirectInactiveCityToBaseDomain($request)) {
            return $inactiveCityResponse;
        }

        return $next($request);
    }

    private function shouldSkip(string $path): bool
    {
        if ($path === '/city/select' || str_starts_with($path, '/city/select/')) {
            return true;
        }
        if ($path === '/news' || str_starts_with($path, '/news/') || $path === '/regions/news') {
            return true;
        }
        // These are current storefront tools nested below the chimney category,
        // not legacy category/product paths. Let their explicit routes handle
        // them instead of collapsing them back to /dymohody.
        if (in_array($path, [
            '/dymohody/tis-standart-30',
            '/dymohody/tis-standart-30.csv',
            '/dymohody/tis-standart-50',
            '/dymohody/tis-standart-50.csv',
            '/dymohody/tis-ferrit-1-mm',
            '/dymohody/tis-ferrit-1-mm.csv',
        ], true)) {
            return true;
        }

        // Пропускаем admin, api, assets и livewire
        $prefixes = ['/admin', '/api', '/livewire', '/_debugbar', '/up'];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        // Пропускаем файлы со статическими расширениями
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array($ext, ['css', 'js', 'png', 'jpg', 'svg', 'ico', 'woff', 'woff2', 'map'])) {
            return true;
        }

        return false;
    }

    private function redirectPercentPath(Request $request, string $path): ?Response
    {
        if (! str_contains($path, '%')) {
            return null;
        }

        $targetPath = str_replace('%', '', $path);

        if ($targetPath === '' || $targetPath === $path) {
            return null;
        }

        $query = $request->getQueryString();

        return $this->redirectToPath($request, $targetPath.($query ? '?'.$query : ''));
    }

    private function redirectTargetLoopsToProductPath(string $path, string $targetPath): bool
    {
        $path = '/'.trim($path, '/');
        $targetPath = '/'.trim(parse_url($targetPath, PHP_URL_PATH) ?: $targetPath, '/');

        if ($path === $targetPath) {
            return true;
        }

        $sourceSlug = basename($path);
        $targetSlug = basename($targetPath);
        if ($sourceSlug === '' || $sourceSlug !== $targetSlug) {
            return false;
        }

        $product = Product::query()
            ->where('slug', $sourceSlug)
            ->with('category')
            ->first();

        if (! $product || ! $product->category) {
            return false;
        }

        $canonicalPath = '/'.$product->category->slug.'/'.$product->slug;

        return $path === $canonicalPath || $targetPath === $canonicalPath;
    }

    private function isCanonicalProductPath(Request $request, string $path): bool
    {
        $path = '/'.trim($path, '/');
        $productSlug = basename($path);

        if ($productSlug === '') {
            return false;
        }

        $product = Product::query()
            ->where('slug', $productSlug)
            ->when(! $this->canPreviewInactive($request), fn ($query) => $query
                ->where('is_active', true)
                ->where('is_archived', false))
            ->with('category')
            ->first();

        if (! $product || ! $product->category) {
            return false;
        }

        return $path === '/'.$product->category->slug.'/'.$product->slug;
    }

    private function redirectCityAlias(Request $request): ?Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.'.$baseDomain, '', $host);
        $canonical = self::CITY_ALIASES[$subdomain] ?? null;

        if (! $canonical || $canonical === $subdomain) {
            return null;
        }

        if (! City::findBySlug($canonical)) {
            return $this->redirectInactiveCityToBaseDomain($request);
        }

        if ($this->isPrimaryCatalogTarget($request->getRequestUri())) {
            return redirect()->away(
                'https://'.$baseDomain.$request->getRequestUri(),
                301,
            );
        }

        $target = 'https://'.$canonical.'.'.$baseDomain.$request->getRequestUri();

        return redirect()->away($target, 301);
    }

    private function redirectInactiveCityToBaseDomain(Request $request): ?Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.'.$baseDomain, '', $host);

        if (in_array($subdomain, ['www', 'new', 'admin'], true)) {
            return null;
        }

        if (City::findBySlug($subdomain)) {
            return null;
        }

        $targetPath = $this->primaryDomainTargetPath($request);
        if ($targetPath === null) {
            return response('Gone', 410, [
                'Cache-Control' => 'public, max-age=3600',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        $query = $request->getQueryString();
        $target = 'https://'.$baseDomain.$targetPath.($query ? '?'.$query : '');

        return redirect()->away($target, 301);
    }

    /**
     * Resolve an inactive city URL to a page that is known to exist on the
     * primary domain. Merely matching the final catch-all catalogue routes is
     * not enough: their model-backed parameters must also resolve.
     */
    private function primaryDomainTargetPath(Request $request): ?string
    {
        $baseDomain = config('app.base_domain', 'kotlov.by');
        $primaryRequest = Request::create(
            'https://'.$baseDomain.$request->getRequestUri(),
            $request->getMethod(),
        );

        try {
            $route = Route::getRoutes()->match($primaryRequest);
        } catch (HttpExceptionInterface) {
            return null;
        }

        $path = '/'.ltrim($primaryRequest->getPathInfo(), '/');
        $path = rtrim($path, '/') ?: '/';
        $uses = $route->getAction('uses');
        $parameters = $route->parameters();

        if ($uses === CatalogController::class.'@show') {
            $slug = (string) ($parameters['category'] ?? '');
            $category = Category::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first(['slug']);

            if ($category) {
                return '/'.$category->slug;
            }

            $product = Product::query()
                ->where('slug', $slug)
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhere('is_archived', true))
                ->with('category:id,slug')
                ->first(['id', 'category_id', 'slug']);

            return $product?->category
                ? '/'.$product->category->slug.'/'.$product->slug
                : null;
        }

        if ($uses === ProductController::class.'@show') {
            $slug = (string) ($parameters['product'] ?? '');

            $category = Category::query()->where('slug', $slug)->first(['id', 'parent_id', 'slug', 'is_active']);
            if ($category) {
                if ($category->is_active) {
                    return '/'.$category->slug;
                }

                $parentSlug = $category->parent?->slug;

                return $parentSlug ? '/'.$parentSlug : '/';
            }

            $product = Product::query()
                ->where('slug', $slug)
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhere('is_archived', true))
                ->with('category:id,slug')
                ->first(['id', 'category_id', 'slug']);

            return $product?->category
                ? '/'.$product->category->slug.'/'.$product->slug
                : null;
        }

        if ($uses === BrandController::class.'@show') {
            $slug = strtolower((string) ($parameters['slug'] ?? ''));

            return Brand::query()->whereRaw('LOWER(slug) = ?', [$slug])->exists()
                ? '/brands/'.$slug
                : null;
        }

        if ($uses === BlogController::class.'@show') {
            $slug = (string) ($parameters['slug'] ?? '');

            return BlogPost::published()->where('slug', $slug)->exists()
                ? '/blog/'.$slug
                : null;
        }

        if ($uses === InstallerController::class.'@show') {
            $slug = (string) ($parameters['slug'] ?? '');

            return InstallerProfile::query()
                ->where('slug', $slug)
                ->where('is_published', true)
                ->where('status', 'active')
                ->exists()
                    ? '/installers/'.$slug
                    : null;
        }

        // Parameterized public landing pages are constrained by the router.
        if ($route->getName() === 'heat-pumps.landing') {
            return $path;
        }

        // These two catch-all routes are explicit legacy redirects, not
        // arbitrary content routes.
        if (str_starts_with($route->uri(), 'pechi-dlya-bani/')
            || str_starts_with($route->uri(), 'otoplenie/')) {
            return $path;
        }

        // A fixed GET route is a verifiable primary-domain target. Other
        // parameterized application routes (tokens, order IDs, admin data)
        // are intentionally not treated as SEO redirect destinations.
        return $parameters === [] ? $path : null;
    }

    private function redirectCityOwnedPageToBaseDomain(Request $request, string $path): ?Response
    {
        if ($path === '/news' || str_starts_with($path, '/news/') || $path === '/regions/news') {
            return null;
        }
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.'.$baseDomain, '', $host);
        if ($subdomain === '' || in_array($subdomain, ['www', 'new', 'admin'], true)) {
            return null;
        }

        $city = City::findBySlug($subdomain);
        $hasPublicBranch = $city && PartnerBranch::query()->visibleIn($city)->exists();

        if ($hasPublicBranch && ($path === '/brands' || str_starts_with($path, '/brands/')
            || in_array($path, ['/catalog', '/robots.txt', '/sitemap.xml'], true))) {
            return null;
        }

        $targetPath = null;
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (in_array($path, ['/catalog', '/brands', '/robots.txt', '/sitemap.xml'], true)) {
            $targetPath = $path;
        } elseif (count($segments) === 2 && $segments[0] === 'brands') {
            $brandSlug = strtolower(rawurldecode($segments[1]));
            if (Brand::query()->whereRaw('LOWER(slug) = ?', [$brandSlug])->exists()) {
                $targetPath = '/brands/'.$brandSlug;
            }
        } elseif (count($segments) <= 3 && $segments !== []) {
            $categorySlug = rawurldecode((string) end($segments));
            $category = Category::query()
                ->where('slug', $categorySlug)
                ->where('is_active', true)
                ->first(['slug']);

            if ($category) {
                if ($hasPublicBranch) {
                    return null;
                }

                $targetPath = '/'.$category->slug;
            }
        }

        if ($targetPath === null) {
            return null;
        }

        $query = $request->getQueryString();
        $target = 'https://'.$baseDomain.$targetPath.($query ? '?'.$query : '');

        return redirect()->away($target, 301);
    }

    /**
     * Unpublished city hosts keep the legacy redirect. Published partner shops
     * serve shared cards locally; ProductController retains the main canonical.
     */
    private function redirectCityProductToBaseDomain(Request $request, string $path): ?Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.'.$baseDomain, '', $host);
        if ($subdomain === '' || in_array($subdomain, ['www', 'new', 'admin'], true)) {
            return null;
        }

        if ($this->hasPublishedCityStorefront($request)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) !== 2) {
            return null;
        }

        $product = Product::query()
            ->where('slug', rawurldecode($segments[1]))
            ->when(! $this->canPreviewInactive($request), fn ($query) => $query
                ->where(fn ($productQuery) => $productQuery
                    ->where('is_active', true)
                    ->orWhere('is_archived', true)))
            ->with('category:id,slug')
            ->first();

        if (! $product?->category) {
            return null;
        }

        $canonicalPath = '/'.$product->category->slug.'/'.$product->slug;
        if ($path !== $canonicalPath) {
            return null;
        }

        $query = $request->getQueryString();
        $target = 'https://'.$baseDomain.$canonicalPath.($query ? '?'.$query : '');

        return redirect()->away($target, 301);
    }

    private function redirectLegacyBrandCategoryPath(Request $request, string $path): ?Response
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) !== 2) {
            return null;
        }

        [$brandSlug, $categorySlug] = $segments;

        $brandExists = Brand::query()
            ->whereRaw('LOWER(slug) = ?', [strtolower($brandSlug)])
            ->exists();

        if (! $brandExists) {
            return null;
        }

        $categoryExists = Category::query()
            ->where('slug', $categorySlug)
            ->where('is_active', true)
            ->exists();

        if (! $categoryExists) {
            return null;
        }

        $query = $request->getQueryString();

        return $this->redirectToPath($request, '/'.$categorySlug.($query ? '?'.$query : ''));
    }

    /**
     * Collapse legacy nested catalogue paths to the current flat category URL.
     *
     * The old platform exposed categories and products through paths such as
     * /nasosy/poverhnostnyie/tsentrobejnye and
     * /nasosy/pogrujnye/removed-product. On the current platform categories
     * live at /{category}. If an old product no longer exists, redirecting to
     * its nearest active category is more useful than multiplying 404 pages
     * across every city subdomain.
     */
    private function redirectLegacyCategoryPath(Request $request, string $path): ?Response
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 2 || in_array($segments[0], [
            'admin', 'api', 'blog', 'installers', 'storage', 'images', 'proxy-image',
        ], true)) {
            return null;
        }

        if (count($segments) === 2 && (
            (BoilerCatalog::LANDINGS[$segments[0]]['slug'] ?? null) === $segments[1]
            || ($segments[0] === 'electric' && isset(WaterHeaterCatalog::LANDINGS[$segments[1]]))
        )) {
            return null;
        }

        // A canonical product URL also contains a category segment. Do not let
        // the legacy category fallback collapse a valid product page back to
        // /{category}; the product controller must handle that request.
        $lastSegment = rawurldecode((string) end($segments));
        $isProductPath = Product::query()
            ->where('slug', $lastSegment)
            ->when(! $this->canPreviewInactive($request), fn ($query) => $query
                ->where(fn ($productQuery) => $productQuery
                    ->where('is_active', true)
                    ->orWhere('is_archived', true)))
            ->exists();

        if ($isProductPath) {
            return null;
        }

        $candidateSlugs = array_reverse($segments);
        $categories = Category::query()
            ->whereIn('slug', $candidateSlugs)
            ->where('is_active', true)
            ->get(['slug'])
            ->keyBy('slug');

        foreach ($candidateSlugs as $candidateSlug) {
            if (! $categories->has($candidateSlug)) {
                continue;
            }

            $targetPath = '/'.$candidateSlug;
            if ($targetPath === $path) {
                return null;
            }

            $query = $request->getQueryString();

            return $this->redirectToPath($request, $targetPath.($query ? '?'.$query : ''));
        }

        return null;
    }

    private function redirectLegacyProductPath(Request $request, string $path): ?Response
    {
        if (str_starts_with($path, '/images/') || str_starts_with($path, '/storage/')) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 2) {
            return null;
        }

        $legacySlug = rawurldecode((string) end($segments));
        $normalizedSlug = $this->normalizeLegacySlug($legacySlug);

        $candidates = collect([
            $legacySlug,
            $normalizedSlug,
            Str::slug(str_replace('_', '-', $legacySlug)),
            Str::slug($legacySlug),
        ])->filter()->unique()->values();

        $product = Product::query()
            ->whereIn('slug', $candidates)
            ->when(! $this->canPreviewInactive($request), fn ($query) => $query
                ->where(fn ($productQuery) => $productQuery
                    ->where('is_active', true)
                    ->orWhere('is_archived', true)))
            ->with('category')
            ->first();

        if (! $product) {
            return null;
        }

        if (! $product->category) {
            Log::warning('Legacy product URL matched product without category', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'legacy_path' => $path,
                'url' => $request->fullUrl(),
            ]);

            return null;
        }

        $targetPath = '/'.$product->category->slug.'/'.$product->slug;

        if ($targetPath === $path) {
            return null;
        }

        $query = $request->getQueryString();

        return $this->redirectToPath($request, $targetPath.($query ? '?'.$query : ''));
    }

    private function redirectToPath(Request $request, string $target, int $status = 301): Response
    {
        if (preg_match('#^https?://#i', $target)) {
            return redirect()->away($target, $status);
        }

        if ($this->isCityHost($request)
            && ($this->isInactiveCityHost($request)
                || (! $this->hasPublishedCityStorefront($request) && $this->isPrimaryCatalogTarget($target)))) {
            $baseDomain = config('app.base_domain', 'kotlov.by');

            return redirect()->away(
                'https://'.$baseDomain.'/'.ltrim($target, '/'),
                $status,
            );
        }

        return redirect($target, $status);
    }

    private function hasPublishedCityStorefront(Request $request): bool
    {
        if (! $this->isCityHost($request)) {
            return false;
        }

        $baseDomain = config('app.base_domain', 'kotlov.by');
        $slug = substr($request->getHost(), 0, -strlen('.'.$baseDomain));
        $city = City::findBySlug($slug);

        return $city && PartnerBranch::query()->visibleIn($city)->exists();
    }

    private function isCityHost(Request $request): bool
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return false;
        }

        $subdomain = str_replace('.'.$baseDomain, '', $host);

        return $subdomain !== '' && ! in_array($subdomain, ['www', 'new', 'admin'], true);
    }

    private function isPrimaryCatalogTarget(string $target): bool
    {
        $path = '/'.trim((string) (parse_url($target, PHP_URL_PATH) ?: '/'), '/');
        $path = $path === '//' ? '/' : $path;

        if (in_array($path, ['/catalog', '/brands'], true)) {
            return true;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) === 2 && $segments[0] === 'brands') {
            return Brand::query()
                ->whereRaw('LOWER(slug) = ?', [strtolower(rawurldecode($segments[1]))])
                ->exists();
        }

        if ($segments === [] || count($segments) > 3) {
            return false;
        }

        $lastSlug = rawurldecode((string) end($segments));
        if (Category::query()
            ->where('slug', $lastSlug)
            ->where('is_active', true)
            ->exists()) {
            return true;
        }

        return count($segments) >= 2
            && Product::query()
                ->where('slug', $lastSlug)
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhere('is_archived', true))
                ->whereHas('category')
                ->exists();
    }

    private function isInactiveCityHost(Request $request): bool
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.'.$baseDomain)) {
            return false;
        }

        $subdomain = str_replace('.'.$baseDomain, '', $host);

        return ! in_array($subdomain, ['www', 'new', 'admin'], true)
            && ! City::findBySlug($subdomain);
    }

    private function canPreviewInactive(Request $request): bool
    {
        // This middleware is appended globally and runs before the web guard
        // has restored the authenticated user from the session.  It only has
        // to avoid collapsing a requested preview URL back to its category;
        // ProductController performs the actual manager/active-user check and
        // returns 404 to everyone else.
        return $request->boolean('admin-preview');
    }

    private function normalizeLegacySlug(string $slug): string
    {
        $slug = str_replace(['(', ')', ',', '–', '—'], ['-', '-', '-', '-', '-'], $slug);
        $slug = preg_replace('/-+/', '-', $slug) ?: $slug;

        return trim($slug, '-');
    }
}
