<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Импортирует SEO-редиректы со старого kotlov.by.
 *
 * Анализ RoutMap.php старого сайта показал что структура URL
 * практически совпадает с новым сайтом:
 *   /kotly, /kotly/gazovye, /kotly/gazovye/slug — те же
 *
 * Реальные расхождения минимальны:
 *   - /catalog → /catalog (страница всех категорий)
 *   - /teplovyie-nasosyi → /teplovye-nasosy (слаг изменён)
 *   - /kotly/inzhenernye-resheniya → 404 (убрана страница)
 *   - Статичные страницы: /rassrochka, /vozvrat-i-obmen, /oplata, /requisites → /about
 *   - /vakansii → /about (нет страницы)
 *   - /comments → /reviews
 *
 * Запуск: php artisan db:seed --class=RedirectsFromSitemapSeeder
 */
class RedirectsFromSitemapSeeder extends Seeder
{
    /**
     * Точные маппинги URL (старый → новый).
     * Приоритет выше чем у префиксных правил.
     */
    private array $exactMap = [
        // Слаг изменился
        '/teplovyie-nasosyi'                    => '/teplovye-nasosy',

        // Водонагреватели были дочерней котлов в старом роутере — теперь корневые
        // (в sitemap уже /vodonagrevateli — совпадает, редирект не нужен)

        // Статичные страницы старого сайта
        '/rassrochka'                           => '/dostavka',
        '/vozvrat-i-obmen'                      => '/dostavka',
        '/oplata'                               => '/dostavka',
        '/requisites'                           => '/about',
        '/vakansii'                             => '/about',
        '/kontacti'                             => '/contacts',
        '/politika'                             => '/privacy',
        '/comments'                             => '/reviews',
        '/sosed'                                => '/',
        '/franchise'                            => '/partners',
        '/service'                              => '/installers',
        '/houses'                               => '/',
        '/remake'                               => '/',

        // Спецстраницы котлов
        '/kotly/inzhenernye-resheniya'          => '/kotly',
        '/bosch/bosch-gaz'                      => '/brands',
        '/invicta/promo'                        => '/brands',

        // Ajax и технические — на главную
        '/ajax/search'                          => '/search',
    ];

    /**
     * Префиксные маппинги (старый префикс → новый префикс).
     * Применяются если точного совпадения нет.
     * Порядок важен — более специфичные первыми.
     */
    private array $prefixMap = [
        // Пагинация старого формата /category/page:N → /category?page=N
        // Обрабатывается отдельно в mapUrl()

        // Старые URL которые не совпадают с новыми
        '/teplovyie-nasosyi/'   => '/teplovye-nasosy/',
        '/otoplenie-parts/'     => '/otoplenie/',
        '/pechi-kaminy-parts/'  => '/kaminy/',
    ];

    /**
     * URL которые не нужно редиректить (совпадают в обоих сайтах).
     * Перечислены для документации — сидер их пропустит автоматически.
     */
    private array $sameUrls = [
        '/', '/about', '/dostavka', '/brands', '/catalog',
        '/kotly', '/vodonagrevateli', '/kaminy', '/dlya-bani',
        '/reviews', '/search', '/contacts', '/privacy',
        // и все /kotly/gazovye/slug-товара — структура та же
    ];

    public function run(): void
    {
        $this->command->info('Загружаем sitemap с kotlov.by...');

        $urls = [];
        try {
            $response = Http::timeout(30)->get('https://kotlov.by/sitemap.xml');
            $urls = $this->parseSitemap($response->body());
            $this->command->info("Найдено URL в sitemap: " . count($urls));
        } catch (\Exception $e) {
            $this->command->warn("Не удалось загрузить sitemap: " . $e->getMessage());
        }

        $redirects = [];
        $now = now();
        $skipped = 0;
        $mapped = 0;

        // Обрабатываем URL из sitemap
        foreach ($urls as $oldUrl) {
            $path = '/' . ltrim(parse_url($oldUrl, PHP_URL_PATH) ?? '', '/');
            $path = rtrim($path, '/') ?: '/';

            $newPath = $this->mapUrl($path);

            if ($newPath === null) {
                // URL совпадает — редирект не нужен
                $skipped++;
                continue;
            }

            $redirects[$path] = [
                'from_url'    => $path,
                'to_url'      => $newPath,
                'status_code' => 301,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            $mapped++;
        }

        // Добавляем точные маппинги которых может не быть в sitemap
        foreach ($this->exactMap as $from => $to) {
            if (!isset($redirects[$from])) {
                $redirects[$from] = [
                    'from_url'    => $from,
                    'to_url'      => $to,
                    'status_code' => 301,
                    'is_active'   => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        $chunks = array_chunk(array_values($redirects), 200);
        $total = 0;
        foreach ($chunks as $chunk) {
            DB::table('redirects')->insertOrIgnore($chunk);
            $total += count($chunk);
        }

        $this->command->info("Пропущено (URL совпадают): {$skipped}");
        $this->command->info("Создано редиректов: {$total}");
        $this->command->info("Ручных маппингов добавлено: " . count($this->exactMap));
    }

    private function parseSitemap(string $xml): array
    {
        $urls = [];
        try {
            $doc = new \SimpleXMLElement($xml);
            $doc->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            foreach ($doc->url ?? [] as $url) {
                $urls[] = (string) $url->loc;
            }

            // Sitemap index
            foreach ($doc->sitemap ?? [] as $sitemap) {
                try {
                    $child = Http::timeout(15)->get((string) $sitemap->loc);
                    $urls = array_merge($urls, $this->parseSitemap($child->body()));
                } catch (\Exception) {}
            }
        } catch (\Exception $e) {
            $this->command->warn("Ошибка парсинга: " . $e->getMessage());
        }

        return $urls;
    }

    private function mapUrl(string $path): ?string
    {
        // Точное совпадение
        if (isset($this->exactMap[$path])) {
            return $this->exactMap[$path];
        }

        // Пагинация старого формата: /kotly/gazovye/page:3 → /kotly/gazovye?page=3
        if (preg_match('#^(.*)/page:(\d+)$#', $path, $m)) {
            $basePath = $m[1];
            $pageNum  = $m[2];
            $newBase  = $this->mapUrl($basePath) ?? $basePath;
            return $newBase . '?page=' . $pageNum;
        }

        // Префиксный маппинг
        foreach ($this->prefixMap as $oldPrefix => $newPrefix) {
            if (str_starts_with($path, $oldPrefix)) {
                return $newPrefix . substr($path, strlen($oldPrefix));
            }
        }

        // URL совпадает со структурой нового сайта — редирект не нужен
        return null;
    }
}
