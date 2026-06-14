<?php

namespace App\Http\Middleware;

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

        // Исключаем служебные пути
        if ($this->shouldSkip($path)) {
            return $next($request);
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
            '~^/otoplenie-parts/(.+)$~'                                  => '/pechi-kaminy/$1',
            '~^/pechi-kaminy-parts/(.+)$~'                               => '/pechi-kaminy/$1',
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

        if ($legacyProductRedirect = $this->redirectLegacyProductPath($request, $path)) {
            return $legacyProductRedirect;
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
