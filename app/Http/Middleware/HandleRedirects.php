<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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

    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем ДО роутинга — не ждём 404
        $path = '/' . ltrim($request->getPathInfo(), '/');
        $path = rtrim($path, '/') ?: '/';

        if ($cityRedirect = $this->redirectCityAlias($request)) {
            return $cityRedirect;
        }

        if ($unknownCityRedirect = $this->redirectUnknownCityToBaseDomain($request)) {
            return $unknownCityRedirect;
        }

        // Исключаем служебные пути
        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        if ($percentRedirect = $this->redirectPercentPath($request, $path)) {
            return $percentRedirect;
        }

        if (preg_match('#^(.*)/page:(\d+)$#', $path, $matches)) {
            $basePath = rtrim($matches[1], '/') ?: '/';
            $query = $request->getQueryString();
            $target = $basePath . ($query ? '?' . $query : '');

            if ($target !== $path) {
                return redirect($target, 301);
            }
        }

        if (preg_match('#^(.+)/shop-default\.html$#', $path, $matches)) {
            $target = rtrim($matches[1], '/') ?: '/';
            $query = $request->getQueryString();

            return redirect($target . ($query ? '?' . $query : ''), 301);
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
                return redirect($target, 301);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        if ($legacyCategoryRedirect = $this->redirectLegacyCategoryPath($request, $path)) {
            return $legacyCategoryRedirect;
        }

        if ($legacyBrandCategoryRedirect = $this->redirectLegacyBrandCategoryPath($request, $path)) {
            return $legacyBrandCategoryRedirect;
        }

        if ($this->isCanonicalProductPath($path)) {
            return $next($request);
        }

        $redirect = DB::table('redirects')
            ->where('from_url', $path)
            ->where('is_active', 1)
            ->select('to_url', 'status_code')
            ->first();

        if ($redirect) {
            // Сохраняем query string если есть
            $query = $request->getQueryString();
            $target = $redirect->to_url . ($query ? '?' . $query : '');
            if ($this->redirectTargetLoopsToProductPath($path, (string) $redirect->to_url)) {
                return $next($request);
            }

            return redirect($target, $redirect->status_code ?? 301);
        }

        return $next($request);
    }

    private function shouldSkip(string $path): bool
    {
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

        return redirect($targetPath . ($query ? '?' . $query : ''), 301);
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

    private function isCanonicalProductPath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        $productSlug = basename($path);

        if ($productSlug === '') {
            return false;
        }

        $product = Product::query()
            ->where('slug', $productSlug)
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

        $target = $request->getScheme() . '://' . $canonical . '.' . $baseDomain . $request->getRequestUri();

        return redirect()->away($target, 301);
    }

    private function redirectUnknownCityToBaseDomain(Request $request): ?Response
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

        $target = $request->getScheme() . '://' . $baseDomain . $request->getRequestUri();

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

        return redirect('/' . $categorySlug . ($query ? '?' . $query : ''), 301);
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
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('is_archived', true))
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

            return redirect($targetPath . ($query ? '?' . $query : ''), 301);
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
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('is_archived', true))
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

        return redirect($targetPath . ($query ? '?' . $query : ''), 301);
    }

    private function normalizeLegacySlug(string $slug): string
    {
        $slug = str_replace(['(', ')', ',', '–', '—'], ['-', '-', '-', '-', '-'], $slug);
        $slug = preg_replace('/-+/', '-', $slug) ?: $slug;

        return trim($slug, '-');
    }
}
