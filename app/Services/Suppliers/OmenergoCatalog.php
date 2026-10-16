<?php

namespace App\Services\Suppliers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OmenergoCatalog
{
    public const CATEGORIES = [
        'https://omenergo.by/katalog/stabilizatory-napryazheniya/stabilizatory-napryazheniya-dlya-gazovyh-kotlov/',
        'https://omenergo.by/katalog/stabilizatory-napryazheniya/odnofaznye-stabilizatory-napryazheniya/',
        'https://omenergo.by/katalog/stabilizatory-napryazheniya/trehfaznye-stabilizatory-napryazheniya/',
        'https://omenergo.by/katalog/istochniki-besperebojnogo-pitaniya/',
        'https://omenergo.by/katalog/avtotransformatory-latr/',
    ];

    public function index(bool $refresh = false): array
    {
        $path = 'omenergo/catalog-index-v2.json';
        if (! $refresh && Storage::disk('local')->exists($path)) {
            return json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        }
        $entries = [];
        foreach (self::CATEGORIES as $category) {
            $queue = [$category.'?limit=100'];
            $seen = [];
            while ($queue !== []) {
                $url = array_shift($queue);
                if (isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                if (count($seen) > 10) {
                    throw new RuntimeException('Omenergo: unexpected pagination size.');
                }
                $xpath = $this->xpath($this->fetch($url));
                foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," us-module-title ")]/a[@href]') as $a) {
                    $href = html_entity_decode($a->getAttribute('href'), ENT_QUOTES, 'UTF-8');
                    $this->assertUrl($href);
                    $entries[$href] = ['name' => $this->clean($a->textContent), 'url' => $href];
                }
                foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," pagination ")]//a[@href]') as $a) {
                    $href = html_entity_decode($a->getAttribute('href'), ENT_QUOTES, 'UTF-8');
                    if (str_starts_with($href, $category) && ! isset($seen[$href])) {
                        $queue[] = $href;
                    }
                }
            }
        }
        // First-party product catalogue of the official Energy distributor:
        // exact manufacturer articles disambiguate omitted models and revisions.
        foreach (['stabilizatory', 'ibp', 'latr'] as $section) {
            $base = 'https://energy-ltd.ru/catalog/'.$section;
            $queue = [$base];
            $seen = [];
            while ($queue !== []) {
                $url = array_shift($queue);
                if (isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                if (count($seen) > 15) {
                    throw new RuntimeException('Energy: unexpected catalogue pagination size.');
                }
                $xpath = $this->xpath($this->fetch($url));
                foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," product_item ")]') as $card) {
                    $article = $this->clean($xpath->evaluate('string(.//*[contains(@class,"art-block")])', $card));
                    $a = $xpath->query('.//*[contains(@class,"product_name")]/a[@href]', $card)->item(0);
                    if ($a && preg_match('/^[ЕE]\d{4}-\d+$/u', $article)) {
                        $href = $this->energyUrl($a->getAttribute('href'));
                        $entries[$href] = ['name' => $this->clean($a->textContent), 'url' => $href, 'article' => $article];
                    }
                }
                foreach ($xpath->query('//*[contains(@class,"pagination")]//a[@href]') as $a) {
                    $href = $this->energyUrl($a->getAttribute('href'));
                    if (str_starts_with($href, $base) && ! isset($seen[$href])) {
                        $queue[] = $href;
                    }
                }
            }
        }
        if (count($entries) < 100) {
            throw new RuntimeException('Omenergo: source catalog unexpectedly incomplete.');
        }
        foreach ($this->primaryIndex() as $entry) {
            $entries[$entry['url']] = $entry;
        }
        Storage::disk('local')->put($path, json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return array_values($entries);
    }

    public function key(string $name): string
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/\b(?:энергия|энерготех|стабилизатор|напряжения|сетевой|фильтр|со|для|отопительных|систем|источник|бесперебойного|питания)\b/iu', '', $name);
        $name = strtr($name, ['асн' => 'ach', 'арс' => 'apc', 'настенный' => 'навесной', 'трехфазный' => '', 'х' => 'x', 'ё' => 'е']);
        $name = preg_replace('/(\d+)\s*ва\b/iu', '$1', $name);
        // Voltron (HP), Voltron (5%) and Voltron РСН are the same
        // manufacturer series; Neo and HV are intentionally never removed.
        // Evidence: energy-ltd.ru/products/stabilizator-napryazheniya-energiya-voltron-500-hp,
        // manufacturer article E0101-0153 and linked Voltron 500–20000 passport.
        if (preg_match('/voltron\s*(?:рсн\s*[- ]?)?\d+/iu', $name)) {
            $name = preg_replace('/\(\s*(?:hp|5%)\s*\)|\bрсн\b/iu', '', $name);
        }

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $name);
    }

    public function find(array $row, array $index): ?array
    {
        $parser = new OmenergoPriceList;
        $articles = array_values(array_filter($index, fn ($entry) => isset($entry['article']) && $parser->articleKey($entry['article']) === $parser->articleKey($row['article'] ?? '')));
        $primary = array_values(array_filter($articles, fn ($entry) => $entry['factory'] ?? false));
        if (count($primary) === 1) {
            return $primary[0];
        }
        if (count($articles) === 1) {
            return $articles[0];
        }
        $key = $this->key($row['name']);
        $matches = array_values(array_filter($index, fn ($entry) => $this->key($entry['name']) === $key));

        return count($matches) === 1 ? $matches[0] : null;
    }

    public function page(string $url): array
    {
        $this->assertUrl($url);
        $cache = 'omenergo/pages/'.hash('sha256', $url).'.html';
        if (! Storage::disk('local')->exists($cache)) {
            Storage::disk('local')->put($cache, $this->fetch($url));
        }

        $html = Storage::disk('local')->get($cache);

        return match (parse_url($url, PHP_URL_HOST)) {
            'energy-ltd.ru' => $this->parseEnergyPage($html),
            'xn--c1adkrq9eta.xn--p1ai' => $this->parseFactoryPage($html),
            'www.energoteh.ru' => $this->parseEnergotehPage($html),
            default => $this->parsePage($html),
        };
    }

    private function primaryIndex(): array
    {
        $url = 'https://xn--c1adkrq9eta.xn--p1ai/product/stabilizatory-napryazheniya-energiya-konstanta';
        $xpath = $this->xpath($this->fetch($url));
        $entries = [];
        foreach ($xpath->query('//a[contains(@class,"js-load-product-variants")][@data-filter][@data-nid]') as $a) {
            $filter = $a->getAttribute('data-filter');
            $nid = $a->getAttribute('data-nid');
            if (! ctype_digit($filter) || ! ctype_digit($nid)) {
                continue;
            }
            $response = Http::timeout(45)->withOptions(['allow_redirects' => false])->asForm()->post('https://xn--c1adkrq9eta.xn--p1ai/load-product-variants/no', [
                'nid' => $nid, 'filters' => json_encode(['0' => $filter]), 'checkFilters' => json_encode(['0' => $filter]),
            ])->throw()->json();
            $variantId = null;
            foreach ($response ?? [] as $command) {
                if (($command['method'] ?? '') === 'pvUpdateUrl') {
                    $variantId = $command['args'][0] ?? null;
                }
            }
            if (! is_numeric($variantId) || (int) $variantId <= 0) {
                throw new RuntimeException('Energy manufacturer variant could not be verified.');
            }
            $variantUrl = $url.'?pv='.(int) $variantId;
            $page = $this->page($variantUrl);
            if ($page['article'] === '') {
                throw new RuntimeException('Energy manufacturer variant article missing.');
            }
            $entries[] = ['name' => $page['name'], 'article' => $page['article'], 'url' => $variantUrl, 'factory' => true];
        }
        $entries[] = ['name' => 'Стабилизатор напряжения Энерготех INFINITY 50000х3', 'article' => 'ET11-08', 'url' => 'https://www.energoteh.ru/catalog/seriya-infinity/trekhfaznyy-energotekh-infinity-50000x3/', 'factory' => true];

        return $entries;
    }

    public function parseFactoryPage(string $html): array
    {
        $xpath = $this->xpath($html);
        $specs = [];
        $article = '';
        $name = '';
        foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," card-characteristic__item ")]') as $item) {
            $key = $this->clean($xpath->evaluate('string(./span[1]/text()[1])', $item));
            $value = $this->clean($xpath->evaluate('string(./span[2])', $item));
            if ($key === 'Название модели') {
                $name = $value;
            } elseif ($key === 'Артикул') {
                $article = $value;
            } elseif ($key !== '' && $value !== '' && mb_strlen($value) <= 350) {
                $specs[$key] = ['key' => $key, 'value' => $value];
            }
        }
        foreach ($xpath->query('//table//tr') as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells->length === 2) {
                $key = $this->clean($cells->item(0)->textContent);
                $value = $this->clean($cells->item(1)->textContent);
                if ($key !== '' && $value !== '' && mb_strlen($key) <= 120 && mb_strlen($value) <= 350) {
                    $specs[$key] = ['key' => $key, 'value' => $value];
                }
            }
        }
        $images = [];
        foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," slider__for--img ")]/img[@data-old]') as $img) {
            $images[] = str_replace('/styles/style_400x400/public/', '/', $img->getAttribute('data-old'));
        }
        $documents = [];
        foreach ($xpath->query('//a[contains(@href,".pdf")][@download]') as $a) {
            $documents[] = ['name' => $this->clean($a->textContent), 'url' => $a->getAttribute('href')];
        }

        return ['name' => $name, 'article' => $article, 'brand' => 'Энергия', 'images' => array_values(array_unique($images)), 'specs' => array_values($specs), 'documents' => $documents];
    }

    public function parseEnergotehPage(string $html): array
    {
        $xpath = $this->xpath($html);
        $specs = [];
        foreach ($xpath->query('//*[@id="pp-description"]//tr') as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells->length !== 2) {
                continue;
            }
            $key = $this->clean($cells->item(0)->textContent);
            $value = $this->clean($cells->item(1)->textContent);
            if ($key !== '' && $value !== '' && mb_strlen($key) <= 120 && mb_strlen($value) <= 350) {
                $specs[] = ['key' => $key, 'value' => $value];
            }
        }
        $images = [];
        foreach ($xpath->query('//a[contains(concat(" ",normalize-space(@class)," ")," img-middle ")][@href]') as $a) {
            $images[] = 'https://www.energoteh.ru'.$a->getAttribute('href');
        }
        $documents = [];
        foreach ($xpath->query('//*[@id="pp-description"]//a[contains(@href,".pdf")]') as $a) {
            $documents[] = ['name' => 'Паспорт INFINITY 50000х3', 'url' => $a->getAttribute('href')];
        }

        return ['name' => $this->clean($xpath->evaluate('string(//h1)')), 'brand' => 'Энерготех', 'images' => $images, 'specs' => $specs, 'documents' => $documents];
    }

    public function identityMatches(array $row, array $page): bool
    {
        if (isset($page['article'])) {
            $parser = new OmenergoPriceList;
            if ($row['brand'] !== 'Энергия' || $parser->articleKey($page['article']) !== $parser->articleKey($row['article'])) {
                return false;
            }
            // A supplier typo must not turn Expert 350 (220V) into Expert 800.
            preg_match_all('/\d+(?:[.,]\d+)?/u', $row['name'], $rowNumbers);
            preg_match_all('/\d+(?:[.,]\d+)?/u', $page['name'], $pageNumbers);

            $wanted = array_map(fn ($n) => (float) str_replace(',', '.', $n), $rowNumbers[0]);
            $actual = array_map(fn ($n) => (float) str_replace(',', '.', $n), $pageNumbers[0]);

            return $wanted !== [] && $wanted === array_slice($actual, 0, count($wanted));
        }

        return $this->key($page['name']) === $this->key($row['name']) && mb_strtolower($page['brand']) === mb_strtolower($row['brand']);
    }

    public function verifiedSpecs(array $row, array $page): array
    {
        $result = [];
        $conflicts = [];
        preg_match('/(\d+(?:[.,]\d+)?)\s*%\s*(?:точност|погрешност)/iu', $row['description'], $accuracy);
        foreach ($page['specs'] as $spec) {
            if (preg_match('/гарант|производство|страна/iu', $spec['key'])) {
                continue; // Local warranty/origin are taken from this supplier's sheet.
            }
            if (isset($accuracy[1]) && preg_match('/точност|номинальное выходное напряжение/iu', $spec['key'])
                && preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $spec['value'], $sourceAccuracy)
                && abs((float) str_replace(',', '.', $accuracy[1]) - (float) str_replace(',', '.', $sourceAccuracy[1])) > 0.01) {
                $conflicts[] = $spec['key'];

                continue;
            }
            $result[] = $spec;
        }

        return ['specs' => $result, 'conflicts' => $conflicts];
    }

    public function parseEnergyPage(string $html): array
    {
        $xpath = $this->xpath($html);
        $code = $this->clean($xpath->evaluate('string(//*[contains(@class,"product_code")])'));
        preg_match('/[ЕE]\d{4}-\d+/u', $code, $article);
        $images = [];
        foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," slider__images ")]//a[@href]') as $a) {
            $images[] = $this->energyUrl($a->getAttribute('href'));
        }
        $specs = [];
        foreach ($xpath->query('//*[@id="features"]//*[contains(concat(" ",normalize-space(@class)," ")," char_name ")]') as $node) {
            $key = $this->clean($node->textContent);
            $value = $this->clean($xpath->evaluate('string(./*[contains(@class,"char_value")])', $node->parentNode));
            if ($key !== '' && $value !== '' && mb_strlen($value) <= 350) {
                $specs[$key] = ['key' => $key, 'value' => $value];
            }
        }
        $documents = [];
        foreach ($xpath->query('//*[@itemprop="documents"]//a[@href]') as $a) {
            $url = $this->energyUrl($a->getAttribute('href'));
            if (preg_match('/\.pdf(?:\?|$)/i', $url)) {
                $documents[] = ['name' => $this->clean($a->textContent), 'url' => $url];
            }
        }

        return ['name' => $this->clean($xpath->evaluate('string(//h1)')), 'article' => $article[0] ?? '', 'brand' => 'Энергия', 'images' => array_values(array_unique($images)), 'specs' => array_values($specs), 'documents' => $documents];
    }

    public function parsePage(string $html): array
    {
        $xpath = $this->xpath($html);
        $name = $this->clean($xpath->evaluate('string(//h1)'));
        $brand = $this->clean($xpath->evaluate('string(//*[contains(@class,"us-product-info-item-manufacturer")]/a)'));
        $images = [];
        foreach ($xpath->query('//a[contains(concat(" ",normalize-space(@class)," ")," oct-gallery ")][@href]') as $a) {
            $url = $a->getAttribute('href');
            if (parse_url($url, PHP_URL_HOST) === 'omenergo.by' && str_contains($url, '/image/') && preg_match('/\.(jpg|jpeg|png|webp)$/i', $url)) {
                $images[] = $url;
            }
        }
        $specs = [];
        foreach ($xpath->query('//*[contains(@class,"us-product-description-cont")]//tr') as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells->length !== 2) {
                continue;
            }
            $key = $this->clean($cells->item(0)->textContent);
            $value = $this->clean($cells->item(1)->textContent);
            if ($key !== '' && $value !== '' && mb_strlen($key) <= 120 && mb_strlen($value) <= 350
                && ! in_array($key, ['Дополнительные параметры', 'Наименование', 'Параметр'], true)) {
                $specs[$key] = ['key' => $key, 'value' => $value];
            }
        }
        $documents = [];
        foreach ($xpath->query('//*[contains(@class,"us-product-description-cont")]//a[@href]') as $a) {
            $url = $a->getAttribute('href');
            if (parse_url($url, PHP_URL_HOST) === 'omenergo.by' && preg_match('/\.pdf(?:\?|$)/i', $url)) {
                $documents[] = ['name' => $this->clean($a->textContent) ?: 'Документация', 'url' => $url];
            }
        }

        return ['name' => $name, 'brand' => $brand, 'images' => array_values(array_unique($images)), 'specs' => array_values($specs), 'documents' => $documents];
    }

    public function downloadImage(string $url, string $article, int $index): ?string
    {
        $this->assertUrl($url);
        if (! str_contains($url, '/image/') && ! str_contains($url, '/files/') && ! str_contains($url, '/upload/')) {
            return null;
        }
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }
        $path = 'img/products/omenergo/'.strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $article)).'-'.$index.'-'.substr(hash('sha256', $url), 0, 10).'.'.$extension;
        if (is_file(public_path($path))) {
            return $path;
        }
        $body = $this->fetch($url);
        if (strlen($body) > 10 * 1024 * 1024) {
            throw new RuntimeException('Omenergo image exceeds 10 MiB.');
        }
        $size = @getimagesizefromstring($body);
        if (! $size || min($size[0], $size[1]) < 200 || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }
        if (! is_dir(dirname(public_path($path)))) {
            mkdir(dirname(public_path($path)), 0775, true);
        }
        file_put_contents(public_path($path), $body);

        return $path;
    }

    public function downloadDocument(string $url, string $article): ?string
    {
        $this->assertUrl($url);
        if (! preg_match('/\.pdf$/i', (string) parse_url($url, PHP_URL_PATH))) {
            return null;
        }
        $path = 'docs/omenergo/'.strtolower(preg_replace('/[^a-zA-Z0-9-]/', '', $article)).'-'.substr(hash('sha256', $url), 0, 12).'.pdf';
        if (is_file(public_path($path))) {
            return '/'.$path;
        }
        $body = $this->fetch($url);
        if (strlen($body) > 20 * 1024 * 1024 || ! str_starts_with(ltrim($body), '%PDF-')) {
            return null;
        }
        if (! is_dir(dirname(public_path($path)))) {
            mkdir(dirname(public_path($path)), 0775, true);
        }
        file_put_contents(public_path($path), $body);

        return '/'.$path;
    }

    private function fetch(string $url): string
    {
        $this->assertUrl($url);

        return Http::timeout(45)->retry(2, 1000)->withOptions(['allow_redirects' => false])->get($url)->throw()->body();
    }

    private function assertUrl(string $url): void
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array(parse_url($url, PHP_URL_HOST), ['omenergo.by', 'energy-ltd.ru', 'xn--c1adkrq9eta.xn--p1ai', 'www.energoteh.ru'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PORT) !== null) {
            throw new RuntimeException('Only verified HTTPS Omenergo / official Energy sources are allowed.');
        }
    }

    private function energyUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        if (! str_starts_with($url, 'https://')) {
            $url = 'https://energy-ltd.ru/'.ltrim(preg_replace('#^(?:\.\./)+#', '', $url), '/');
        }
        $this->assertUrl($url);

        return $url;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $value)));
    }
}
