<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\City;
use App\Models\InstallerProfile;
use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class HandleRedirects
{
    /**
     * Categories promoted as one national landing page. City-host copies split
     * ranking signals and can be selected by Google as the canonical instead.
     */
    private const PRIMARY_DOMAIN_CATEGORY_PATHS = [
        '/schetchiki-gaza',
    ];

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

    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем ДО роутинга — не ждём 404
        $path = '/' . ltrim($request->getPathInfo(), '/');
        $path = rtrim($path, '/') ?: '/';

        if ($cityRedirect = $this->redirectCityAlias($request)) {
            return $cityRedirect;
        }

        if ($cityCategoryRedirect = $this->redirectPrimaryCategoryToBaseDomain($request, $path)) {
            return $cityCategoryRedirect;
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
                $target = $redirect->to_url . ($query ? '?' . $query : '');
                if (! $this->redirectTargetLoopsToProductPath($path, (string) $redirect->to_url)) {
                    return $this->redirectToPath($request, $target, $redirect->status_code ?? 301);
                }
            }
        }

        if (preg_match('#^(.*)/page:(\d+)$#', $path, $matches)) {
            $basePath = rtrim($matches[1], '/') ?: '/';
            $query = $request->getQueryString();
            $target = $basePath . ($query ? '?' . $query : '');

            if ($target !== $path) {
                return $this->redirectToPath($request, $target);
            }
        }

        if (preg_match('#^(.+)/shop-default\.html$#', $path, $matches)) {
            $target = rtrim($matches[1], '/') ?: '/';
            $query = $request->getQueryString();

            return $this->redirectToPath($request, $target . ($query ? '?' . $query : ''));
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
            // ── Тепловые насосы ───────────────────────────────────────────────
            // /teplovye-nasosy → /teplovyie-nasosyi (старое написание без iye)
            '~^/teplovye-nasosy/(.+)$~'                                  => '/teplovyie-nasosyi/$1',
            '~^/teplovye-nasosy$~'                                       => '/teplovyie-nasosyi',
            '~^/akkumuliruyushhie-baki(/.*)?$~'                          => '/bufernye-emkosti$1',

            // ── /kotly ────────────────────────────────────────────────────────
            // /kotly/teplovyie-nasosyi → /teplovyie-nasosyi (старый URL тепловых насосов)
            '~^/kotly/teplovyie-nasosyi/(.+)$~'                          => '/teplovyie-nasosyi/$1',
            '~^/kotly/teplovyie-nasosyi$~'                               => '/teplovyie-nasosyi',
            // Старая вложенная страница пеллетных котлов больше не должна
            // оставаться пустым дублем актуальной плоской категории.
            '~^/kotly/tverdotoplivnye/kotly-na-pelletah$~'               => '/kotly-na-pelletah',
            '~^/tverdotoplivnye/kotly-na-pelletah$~'                     => '/kotly-na-pelletah',
            // /kotly/{cat}/{slug} → /{cat}/{slug}
            '~^/kotly/(gazovye|tverdotoplivnye|elektricheskie)/(.+)$~'  => '/$1/$2',
            '~^/kotly/(gazovye|tverdotoplivnye|elektricheskie)$~'        => '/$1',

            // ── /otoplenie ────────────────────────────────────────────────────
            // /otoplenie/{subcat}/{slug} → /{subcat}/{slug}  (3 сегмента — товар в подразделе)
            '~^/otoplenie/([a-z0-9][a-z0-9\-_]+)/(.+)$~'               => '/$1/$2',
            // /otoplenie/{subcat} → /{subcat}  (2 сегмента — страница подраздела)
            '~^/otoplenie/([a-z0-9][a-z0-9\-_]+)$~'                    => '/$1',

            // ── /kaminy ───────────────────────────────────────────────────────
            // /kaminy/{subcat}/{slug} → /{subcat}/{slug}  (topki/elektrokamini/oblicovki)
            '~^/kaminy/(topki|elektrokamini|oblicovki|bio-kaminy)/(.+)$~' => '/$1/$2',
            // /kaminy/{subcat} → /{subcat}  (страница подраздела)
            '~^/kaminy/(topki|elektrokamini|oblicovki|bio-kaminy)$~'    => '/$1',
            // /kaminy/{product-slug} → /topki/{slug}  (товар напрямую в /kaminy)
            '~^/kaminy/([a-z0-9][a-z0-9\-_\.]+)$~'                    => '/topki/$1',

            // ── /vodonagrevateli ──────────────────────────────────────────────
            // /vodonagrevateli/{subcat}/{slug} → /{subcat}/{slug}
            '~^/vodonagrevateli/([a-z0-9][a-z0-9\-_]+)/(.+)$~'        => '/$1/$2',
            // /vodonagrevateli/{subcat} → /{subcat}
            '~^/vodonagrevateli/([a-z0-9][a-z0-9\-_]+)$~'             => '/$1',

            // ── /dlya-bani ────────────────────────────────────────────────────
            // /dlya-bani/{slug} → /pechi-dlya-bani/{slug}
            '~^/dlya-bani/(.+)$~'                                       => '/pechi-dlya-bani/$1',

            // ── Пеллетные горелки ─────────────────────────────────────────────
            // /otoplenie-parts/pelletnyie-gorelki/{slug} → /pelletnye-gorelki/{slug}
            '~^/otoplenie-parts/pelletnyie-gorelki/(.+)$~'              => '/pelletnye-gorelki/$1',
            '~^/otoplenie-parts/pelletnyie-gorelki$~'                   => '/pelletnye-gorelki',
            // /pelletnyie-gorelki → /pelletnye-gorelki (старый slug с опечаткой)
            '~^/pelletnyie-gorelki/(.+)$~'                              => '/pelletnye-gorelki/$1',
            '~^/pelletnyie-gorelki$~'                                   => '/pelletnye-gorelki',

            // ── /pechi-kaminy-parts, /otoplenie-parts ─────────────────────────
            '~^/otoplenie-parts/(.+)$~'                                  => '/$1',
            '~^/pechi-kaminy-parts/(.+)$~'                               => '/$1',
        ];

        foreach ($legacyPatterns as $pattern => $replacement) {
            if (preg_match($pattern, $path)) {
                $newPath = preg_replace($pattern, $replacement, $path);
                $query   = $request->getQueryString();
                $target  = $newPath . ($query ? '?' . $query : '');
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

        return $this->redirectToPath($request, $targetPath . ($query ? '?' . $query : ''));
    }

    private function redirectTargetLoopsToProductPath(string $path, string $targetPath): bool
    {
        $path = '/' . trim($path, '/');
        $targetPath = '/' . trim(parse_url($targetPath, PHP_URL_PATH) ?: $targetPath, '/');

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

        $canonicalPath = '/' . $product->category->slug . '/' . $product->slug;

        return $path === $canonicalPath || $targetPath === $canonicalPath;
    }

    private function isCanonicalProductPath(Request $request, string $path): bool
    {
        $path = '/' . trim($path, '/');
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

        return $path === '/' . $product->category->slug . '/' . $product->slug;
    }

    private function redirectCityAlias(Request $request): ?Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.' . $baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.' . $baseDomain, '', $host);
        $canonical = self::CITY_ALIASES[$subdomain] ?? null;

        if (! $canonical || $canonical === $subdomain) {
            return null;
        }

        if (! City::findBySlug($canonical)) {
            return $this->redirectInactiveCityToBaseDomain($request);
        }

        $target = 'https://'.$canonical.'.'.$baseDomain.$request->getRequestUri();

        return redirect()->away($target, 301);
    }

    private function redirectInactiveCityToBaseDomain(Request $request): ?Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain', 'kotlov.by');

        if (! str_ends_with($host, '.' . $baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.' . $baseDomain, '', $host);

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

        if ($uses === \App\Http\Controllers\CatalogController::class.'@show') {
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

        if ($uses === \App\Http\Controllers\ProductController::class.'@show') {
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

        if ($uses === \App\Http\Controllers\BrandController::class.'@show') {
            $slug = strtolower((string) ($parameters['slug'] ?? ''));

            return Brand::query()->whereRaw('LOWER(slug) = ?', [$slug])->exists()
                ? '/brands/'.$slug
                : null;
        }

        if ($uses === \App\Http\Controllers\BlogController::class.'@show') {
            $slug = (string) ($parameters['slug'] ?? '');

            return BlogPost::published()->where('slug', $slug)->exists()
                ? '/blog/'.$slug
                : null;
        }

        if ($uses === \App\Http\Controllers\InstallerController::class.'@show') {
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

    private function redirectPrimaryCategoryToBaseDomain(Request $request, string $path): ?Response
    {
        if (! in_array($path, self::PRIMARY_DOMAIN_CATEGORY_PATHS, true)) {
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

        $query = $request->getQueryString();
        $target = 'https://'.$baseDomain.$path.($query ? '?'.$query : '');

        return redirect()->away($target, 301);
    }

    /**
     * Product data is identical on every city subdomain. Redirect canonical
     * product paths to the primary domain so search engines do not crawl the
     * whole catalogue once per city. Regional category and landing pages stay
     * on their city hosts for local search.
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

            $targetPath = '/' . $candidateSlug;
            if ($targetPath === $path) {
                return null;
            }

            $query = $request->getQueryString();

            return $this->redirectToPath($request, $targetPath . ($query ? '?' . $query : ''));
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

        $targetPath = '/' . $product->category->slug . '/' . $product->slug;

        if ($targetPath === $path) {
            return null;
        }

        $query = $request->getQueryString();

        return $this->redirectToPath($request, $targetPath . ($query ? '?' . $query : ''));
    }

    private function redirectToPath(Request $request, string $target, int $status = 301): Response
    {
        if (preg_match('#^https?://#i', $target)) {
            return redirect()->away($target, $status);
        }

        if ($this->isInactiveCityHost($request)) {
            $baseDomain = config('app.base_domain', 'kotlov.by');

            return redirect()->away(
                'https://'.$baseDomain.'/'.ltrim($target, '/'),
                $status,
            );
        }

        return redirect($target, $status);
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
