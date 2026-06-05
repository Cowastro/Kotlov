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
        // Старый сайт использовал 3 сегмента: /kotly/{cat}/{slug}
        // Новый сайт: /{cat}/{slug}
        $legacyPatterns = [
            // /kotly/{cat}/{slug} → /{cat}/{slug}
            '~^/kotly/(gazovye|tverdotoplivnye|elektricheskie)/(.+)$~' => '/$1/$2',
            '~^/kotly/(gazovye|tverdotoplivnye|elektricheskie)$~'       => '/$1',
            // /dlya-bani/{slug} → /pechi-dlya-bani/{slug}
            '~^/dlya-bani/(.+)$~'                                       => '/pechi-dlya-bani/$1',
            // /otoplenie-parts/{slug} → /pechi-kaminy/{slug}
            '~^/otoplenie-parts/(.+)$~'                                 => '/pechi-kaminy/$1',
            // /pechi-kaminy-parts/{slug} → /pechi-kaminy/{slug}
            '~^/pechi-kaminy-parts/(.+)$~'                              => '/pechi-kaminy/$1',
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
