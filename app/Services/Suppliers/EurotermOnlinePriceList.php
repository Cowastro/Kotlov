<?php

namespace App\Services\Suppliers;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EurotermOnlinePriceList
{
    public const URL = 'https://euroterm.by/prajs-list.html';

    public function load(): array
    {
        $credentials = app(EurotermPortalCredentials::class);
        $data = $credentials->get();
        $username = (string) ($data['username'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeException('Для онлайн-прайса Евротерма настройте логин и пароль. Старый файл не используется вместо свежих данных.');
        }
        $credentials->markAttempt();
        $deadline = microtime(true) + 600;
        $http = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9',
            'Referer' => self::URL,
        ])->withOptions([
            'cookies' => new CookieJar,
            'verify' => resource_path('certificates/globalsign-root-r6.pem'),
            'allow_redirects' => false,
        ])->connectTimeout(10)->timeout(40);
        $get = function (string $url) use ($http): string {
            for ($i = 0; $i < 5; $i++) {
                $response = $http->get($this->url($url));
                if ($response->redirect()) {
                    $url = $this->url((string) $response->header('Location'));

                    continue;
                }
                if (! $response->successful()) {
                    throw new RuntimeException('Онлайн-прайс Евротерма недоступен (HTTP '.$response->status().'). Данные не обновлены.');
                }

                return $response->body();
            }
            throw new RuntimeException('Слишком много перенаправлений Евротерма.');
        };
        $login = $this->xpath($get('https://euroterm.by/log-in.html'));
        $form = $login->query('//form[.//input[@type="password"]]')->item(0);
        if (! $form) {
            throw new RuntimeException('Не найдена форма входа Евротерма.');
        }
        $fields = [];
        foreach ($login->query('.//input[@type="hidden"]', $form) as $input) {
            if ($input->getAttribute('name') !== '') {
                $fields[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }
        $response = $http->asForm()->post($this->url($form->getAttribute('action')), array_merge($fields, [
            'username' => $username, 'password' => $password, 'option' => 'com_users', 'task' => 'user.login',
        ]));
        if (! $response->successful() && ! $response->redirect()) {
            throw new RuntimeException('Не удалось войти в онлайн-прайс Евротерма.');
        }

        $queue = [self::URL];
        $visited = $rows = $fingerprints = $unpriced = $ambiguous = [];
        $duplicates = 0;
        $hash = hash_init('sha256');
        $parser = app(EurotermPriceList::class);
        while ($queue !== []) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Загрузка онлайн-прайса Евротерма заняла слишком долго. Частичные данные не применены.');
            }
            if (count($visited) >= 160) {
                throw new RuntimeException('Онлайн-прайс Евротерма загружен не полностью: превышен лимит страниц.');
            }
            $url = array_shift($queue);
            $page = $this->parsePage($get($url));
            $fingerprint = hash('sha256', json_encode([array_column($page['rows'], 'article'), $page['unpriced']]));
            if (isset($fingerprints[$fingerprint])) {
                throw new RuntimeException('Евротерм вернул повтор страницы вместо продолжения прайса. Данные не обновлены.');
            }
            $fingerprints[$fingerprint] = true;
            $visited[$url] = true;
            $unpriced = array_merge($unpriced, $page['unpriced']);
            hash_update($hash, json_encode($page['rows'], JSON_UNESCAPED_UNICODE));
            foreach ($page['rows'] as $row) {
                $row['article_key'] = $parser->articleKey($row['article']);
                $row['name_key'] = $parser->nameKey($row['name']);
                $key = $row['article_key'];
                if ($key === '') {
                    $unpriced[] = ['article' => $row['article'], 'name' => $row['name'], 'source_url' => $row['source_url'], 'reason' => 'invalid_article'];

                    continue;
                }
                if (isset($ambiguous[$key])) {
                    $ambiguous[$key][] = $row;

                    continue;
                }
                if (isset($rows[$key])) {
                    // The stock-sorted catalogue can repeat an unchanged card across pages.
                    if ($rows[$key] === $row) {
                        $duplicates++;
                    } else {
                        // Never choose between different products/prices sharing one article.
                        $ambiguous[$key] = [$rows[$key], $row];
                        unset($rows[$key]);
                    }

                    continue;
                }
                $rows[$key] = $row;
            }
            foreach ($page['pages'] as $next) {
                $next = $this->url($next);
                if (! isset($visited[$next]) && ! in_array($next, $queue, true)) {
                    $queue[] = $next;
                }
            }
            if ($queue !== []) {
                usleep(max(250, (int) config('services.euroterm.pause_ms', 1000)) * 1000);
            }
        }

        $payload = [
            'source_date' => now()->timezone('Europe/Minsk')->toDateString(),
            'source_sha256' => hash_final($hash), 'source_kind' => 'online', 'source_pages' => count($visited),
            'supplier' => ['code' => 'euroterm', 'name' => 'ООО «Евротерм»', 'website' => EurotermPriceList::WEBSITE],
            'warehouse' => ['name' => 'Евротерм · Машиностроителей, 9А', 'address' => 'Минск, ул. Машиностроителей, 9А'],
            'rows' => array_values($rows),
            'unpriced_rows' => $unpriced,
            'ambiguous_rows' => $ambiguous, 'duplicate_rows' => $duplicates,
        ];
        $disk = Storage::disk('local');
        $snapshot = 'suppliers/euroterm/latest-online.json';
        if (! $disk->put($snapshot, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Не удалось сохранить проверенный онлайн-прайс Евротерма.');
        }
        @chmod($disk->path($snapshot), 0600);

        return $payload;
    }

    public function parsePage(string $html): array
    {
        $x = $this->xpath($html);
        if ($x->query('//input[@name="task" and (@value="user.logout" or @value="logout")]|//form[contains(@action,"task=user.logout")]')->length === 0) {
            throw new RuntimeException('Вход в Евротерм не подтверждён или сессия истекла. Цены не обновлены.');
        }
        $rows = $unpriced = [];
        foreach ($x->query('//*[contains(concat(" ",normalize-space(@class)," ")," product-container ")]') as $card) {
            $text = fn (string $query): string => trim($x->query($query, $card)->item(0)?->textContent ?? '');
            $link = $x->query('.//*[contains(@class,"vm-product_name")]/a[@href]', $card)->item(0);
            $article = $text('.//*[contains(concat(" ",@class," ")," sku ")]');
            $wholesale = $this->number($text('.//span[contains(concat(" ",@class," ")," PricesalesPrice ")]'));
            $retail = $this->number($text('.//span[contains(concat(" ",@class," ")," Priceroz ")]'));
            $rate = $this->number($text('.//*[@id="kurseu"]'));
            if (! $link || $article === '' || $wholesale <= 0 || $retail <= 0 || $rate <= 0) {
                if (! $link) {
                    throw new RuntimeException('Изменилась структура карточек онлайн-прайса Евротерма. Данные не обновлены.');
                }
                $unpriced[] = [
                    'article' => $article, 'name' => trim($link->textContent),
                    'source_url' => $this->url($link->getAttribute('href')),
                    'reason' => $article === '' ? 'missing_article' : 'missing_price',
                ];

                continue;
            }
            $stock = $text('.//*[contains(concat(" ",@class," ")," stock-info ")]');
            $inStock = preg_match('/^\s*В наличии\s*$/ui', $stock) === 1;
            $out = preg_match('/нет в наличии|отсутствует/ui', $stock) === 1;
            $rows[] = [
                'article' => $article, 'name' => trim($link->textContent),
                'source_url' => $this->url($link->getAttribute('href')),
                'wholesale' => $wholesale, 'retail' => round($retail * $rate, 2),
                'stock_quantity' => $out ? 0 : null, 'in_stock' => $inStock,
                'stock_status' => $inStock ? 'in_stock' : ($out ? 'out_of_stock' : 'check'),
                'stock_text' => $inStock ? 'В наличии у Евротерма; количество уточняется' : ($out ? 'Нет в наличии у Евротерма' : 'Наличие уточняется'),
                'warehouse_name' => 'Евротерм · Машиностроителей, 9А',
                'category_path' => [], 'section' => '', 'category' => '', 'brand' => '', 'unit' => '',
            ];
        }
        if ($rows === [] && $unpriced === []) {
            throw new RuntimeException('Онлайн-прайс Евротерма пуст или его формат изменился.');
        }
        $pages = [];
        foreach ($x->query('//a[@href]') as $link) {
            if (preg_match('~^/results\d+-\d+\.html$~', $link->getAttribute('href'))) {
                $pages[] = $link->getAttribute('href');
            }
        }

        return ['rows' => $rows, 'unpriced' => $unpriced, 'pages' => array_values(array_unique($pages))];
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $old = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($old);

        return new DOMXPath($dom);
    }

    private function number(string $value): float
    {
        $value = preg_replace('/[\s\x{00a0}]+/u', '', $value);

        return preg_match('/^(\d+(?:[.,]\d+)?)(?:р\.?|p\.?)?$/u', $value, $m) ? (float) str_replace(',', '.', $m[1]) : 0;
    }

    private function url(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return 'https://euroterm.by'.$url;
        }
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'euroterm.by'
            || isset($parts['user']) || isset($parts['port'])) {
            throw new RuntimeException('Небезопасная ссылка в онлайн-прайсе Евротерма.');
        }

        return $url;
    }
}
