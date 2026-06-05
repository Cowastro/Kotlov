<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем ДО роутинга — не ждём 404
        $path = '/' . ltrim($request->getPathInfo(), '/');
        $path = rtrim($path, '/') ?: '/';

        // Исключаем служебные пути
        if ($this->shouldSkip($path)) {
            return $next($request);
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
}
