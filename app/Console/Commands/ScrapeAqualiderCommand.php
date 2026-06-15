<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Scrape aqualider.by (ТСК Насосы catalogue, Bitrix) and create/enrich products
 * with correct category, brand, photo, description and characteristics
 * (product_attribute_values). Prices/stock come separately from the price sheet
 * via supplier:sync-tsk-nasosy (matched by brand+model — site SKU ≠ sheet article).
 *
 *   php artisan supplier:scrape-aqualider --category=tsirkulyatsionnye --pages=2 --dry-run
 *   php artisan supplier:scrape-aqualider --category=tsirkulyatsionnye --pages=2 --apply
 */
class ScrapeAqualiderCommand extends Command
{
    protected $signature = 'supplier:scrape-aqualider
        {--category=tsirkulyatsionnye : Category key (see CATEGORIES) or full /catalog/... path}
        {--pages=1   : Max listing pages to crawl}
        {--limit=    : Max products to process}
        {--sleep=600 : Delay between requests, ms}
        {--apply     : Write to DB (default: preview)}
        {--dry-run   : Preview only (default)}';

    protected $description = 'Scrape aqualider.by → create/enrich products (category/brand/photo/desc/specs).';

    private const SUPPLIER_CODE = 'tsk_nasosy';
    private const BASE = 'https://aqualider.by';
    private const IMAGE_DIR = 'img/products/tsk-nasosy';

    /** Shortcut key → [listing path, default KOTLOV category_id]. */
    private const CATEGORIES = [
        'tsirkulyatsionnye' => ['/catalog/nasosy/tsirkulyatsionnye_nasosy/', 60],
        'promyshlennye'     => ['/catalog/promyshlennye_nasosy/', 272],
        'stantsii'          => ['/catalog/nasosnye_stantsii_1/', 251],
        'kanalizacionnye'   => ['/catalog/kanalizatsionnye_nasosnye_stantsii/', 265],
        'otoplenie'         => ['/catalog/komplektuyushchie_dlya_sistem_otopleniya/', 195],
        'vodosnabzhenie'    => ['/catalog/komplektuyushchie_dlya_sistem_vodosnabzheniya/', 195],
        'armatura'          => ['/catalog/zapornaya_i_reguliruyushchaya_armatura/', 195],
        'baki'              => ['/catalog/membrannye_baki_dlya_vody/', 89],
    ];

    /**
     * Specific breadcrumb keyword → category, checked IN ORDER (more specific
     * first). If none match, the run's default category (above) is used.
     */
    private const CATEGORY_MAP = [
        'канализац'  => 265,
        'фекаль'     => 265,
        'дренаж'     => 265,
        'циркуляц'   => 60,
        'скважин'    => 272,
        'гидроаккум' => 89,
        'мембранн'   => 89,
        'расширительн' => 89,
        'насосные станции' => 251,
    ];

    private bool $apply;
    private array $stats = ['listed' => 0, 'parsed' => 0, 'created' => 0, 'matched' => 0,
                            'attrs_written' => 0, 'images' => 0, 'no_category' => 0, 'errors' => 0];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->line($this->apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow;options=bold>DRY RUN</>');

        $key = (string) $this->option('category');
        if (isset(self::CATEGORIES[$key])) {
            [$path, $defaultCat] = self::CATEGORIES[$key];
        } elseif (str_starts_with($key, '/catalog/')) {
            $path = $key;
            $defaultCat = 272;
        } else {
            $this->error('Unknown category. Keys: ' . implode(', ', array_keys(self::CATEGORIES)) . ' (or a /catalog/... path)');
            return self::FAILURE;
        }

        $links = $this->collectProductLinks($path, (int) $this->option('pages'));
        $this->stats['listed'] = count($links);
        $this->info(sprintf('Collected %d product links from %s', count($links), $path));
        if ($links === []) {
            return self::SUCCESS;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : count($links);
        foreach (array_slice($links, 0, $limit) as $url) {
            try {
                $this->processProduct($url, $defaultCat);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->warn('  error: ' . $e->getMessage());
            }
            usleep((int) $this->option('sleep') * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));
        return self::SUCCESS;
    }

    private function collectProductLinks(string $path, int $maxPages): array
    {
        $links = [];
        for ($p = 1; $p <= max(1, $maxPages); $p++) {
            $url = self::BASE . $path . ($p > 1 ? "?PAGEN_1={$p}" : '');
            $html = $this->fetch($url);
            if ($html === null) {
                break;
            }
            // Product links: the category path + /{numericId}/ (may be deeper subcats).
            if (preg_match_all('#href="(/catalog/[a-z0-9_/\-]*?/\d+/)"#i', $html, $m)) {
                foreach ($m[1] as $href) {
                    $links[self::BASE . $href] = true;
                }
            }
        }
        return array_keys($links);
    }

    private function processProduct(string $url, int $defaultCat): void
    {
        $html = $this->fetch($url);
        if ($html === null) {
            $this->stats['errors']++;
            return;
        }
        $d = $this->parseProduct($html, $url);
        $this->stats['parsed']++;

        $catId = $this->resolveCategory($d['breadcrumb'], $defaultCat);

        $this->line(sprintf('<fg=cyan>%s</> %s | бренд:%s | цена:%s | specs:%d | cat:%d',
            $d['article'], mb_substr($d['name'], 0, 44), $d['brand'] ?: '—',
            $d['price'] !== null ? $d['price'] : '—', count($d['specs']), $catId));

        if (! $this->apply) {
            $sample = array_slice($d['specs'], 0, 4, true);
            foreach ($sample as $k => $v) {
                $this->line("    · {$k} = {$v}");
            }
            return;
        }

        // ── Apply: brand, match-or-create product, image, specs, supplier link ────
        $brandId = $d['brand'] !== '' ? $this->findOrCreateBrand($d['brand']) : null;
        $productId = $this->matchProduct($d['name'], $brandId);
        $now = now();

        if ($productId === null) {
            $productId = $this->createProduct($d, $catId, $brandId, $now);
            $this->stats['created']++;
        } else {
            $this->stats['matched']++;
        }

        if ($d['image'] !== null) {
            $this->maybeDownloadImage($productId, $d['image']);
        }
        $this->writeSpecs($productId, $catId, $d['specs']);
        $this->upsertSupplierProduct($productId, $d, $url, $now);
    }

    // ── Parsing ───────────────────────────────────────────────────────────────────

    private function parseProduct(string $html, string $url): array
    {
        $name = $this->meta($html, 'og:title');
        $name = trim(preg_replace('/\s*купить.*$/iu', '', $name) ?? $name);

        $specs = [];
        preg_match_all(
            '/properties-group__name[^>]*>(?:\s*<[^>]+>)*\s*([^<]+).*?properties-group__value[^>]*>(?:\s*<[^>]+>)*\s*([^<]+)/su',
            $html, $m
        );
        for ($i = 0, $n = count($m[1]); $i < $n; $i++) {
            $k = trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            $v = trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($k !== '' && $v !== '' && mb_strlen($k) <= 80 && ! isset($specs[$k])) {
                $specs[$k] = $v;
            }
        }

        $brand = $specs['Бренд'] ?? '';
        unset($specs['Бренд']);

        $article = '';
        if (preg_match('/Артикул:\s*<\/span>\s*<[^>]*>\s*([0-9A-Za-zА-Яа-я\-\/.]+)/u', $html, $a)
            || preg_match('/Артикул:[^0-9A-Za-z]*([0-9]{4,})/u', $html, $a)) {
            $article = trim($a[1]);
        }
        if ($article === '' && preg_match('#/(\d+)/?$#', $url, $idm)) {
            $article = 'AQ-' . $idm[1];
        }

        $price = null;
        if (preg_match('/itemprop="price"\s+content="([0-9.]+)"/', $html, $pm)) {
            $price = (float) $pm[1];
        }

        $image = $this->meta($html, 'og:image') ?: null;

        $crumbs = [];
        if (preg_match_all('/breadcrumbs__item-name[^>]*>([^<]+)</u', $html, $bm)) {
            $crumbs = array_map('trim', $bm[1]);
        }
        $breadcrumb = implode(' / ', $crumbs);

        $desc = $this->meta($html, 'og:description');

        // Availability from the site (Достаточно/Мало/Нет/Под заказ).
        $stockText = ''; $stockStatus = 'unknown'; $inStock = false;
        if (preg_match('/(Нет в наличии|Под заказ|Достаточно|Мало|В наличии)/u', $html, $sm)) {
            $stockText = $sm[1];
            $l = mb_strtolower($sm[1]);
            if (str_contains($l, 'нет')) {
                $stockStatus = 'out_of_stock';
            } elseif (str_contains($l, 'заказ')) {
                $stockStatus = 'preorder';
            } elseif (str_contains($l, 'мало')) {
                $stockStatus = 'low_stock'; $inStock = true;
            } else { // Достаточно / В наличии
                $stockStatus = 'in_stock'; $inStock = true;
            }
        }

        return compact('name', 'brand', 'article', 'price', 'specs', 'image', 'breadcrumb', 'desc',
            'stockText', 'stockStatus', 'inStock');
    }

    private function meta(string $html, string $prop): string
    {
        if (preg_match('/<meta[^>]+property="' . preg_quote($prop, '/') . '"[^>]+content="([^"]*)"/i', $html, $m)
            || preg_match('/<meta[^>]+content="([^"]*)"[^>]+property="' . preg_quote($prop, '/') . '"/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        return '';
    }

    private function resolveCategory(string $breadcrumb, int $default): int
    {
        $low = mb_strtolower($breadcrumb);
        foreach (self::CATEGORY_MAP as $kw => $cat) {
            if (str_contains($low, $kw)) {
                return $cat;
            }
        }
        return $default;
    }

    // ── DB writes ───────────────────────────────────────────────────────────────

    private function findOrCreateBrand(string $name): int
    {
        $name = trim($name);
        $existing = DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
        if ($existing) {
            return (int) $existing;
        }
        return (int) DB::table('brands')->insertGetId([
            'name' => $name, 'slug' => Str::slug($name) ?: Str::random(8),
            'h1' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function matchProduct(string $name, ?int $brandId): ?int
    {
        if ($brandId === null) {
            return null;
        }
        $model = $this->model($name);
        if ($model === '') {
            return null;
        }
        foreach (DB::table('products')->where('brand_id', $brandId)->where('is_archived', false)->get(['id', 'name']) as $p) {
            if ($this->model((string) $p->name) === $model) {
                return (int) $p->id;
            }
        }
        return null;
    }

    private function model(string $name): string
    {
        $n = mb_strtoupper($name);
        $n = preg_replace('/(ЦИРКУЛЯЦИОННЫЙ|НАСОС|СКВАЖИННЫЙ|ДРЕНАЖНЫЙ|ПОВЕРХНОСТНЫЙ|СТАНЦИЯ)/u', '', $n) ?? $n;
        $n = preg_replace('/[^A-ZА-Я0-9\/\-.]/u', ' ', $n) ?? $n;
        return trim(preg_replace('/\s+/u', ' ', $n) ?? $n);
    }

    private function createProduct(array $d, int $catId, ?int $brandId, $now): int
    {
        $name = $d['name'] !== '' ? $d['name'] : ($d['brand'] . ' ' . $d['article']);
        return (int) DB::table('products')->insertGetId([
            'category_id' => $catId, 'brand_id' => $brandId,
            'name' => $name, 'h1' => $name, 'sku' => $this->nextSku(),
            'slug' => $this->uniqueSlug($name),
            'price' => $d['price'] ?? 0, 'currency' => 'BYN',
            'content' => $d['desc'] !== '' ? '<p>' . e($d['desc']) . '</p>' : null,
            'short_description' => $d['desc'] !== '' ? mb_substr($d['desc'], 0, 250) : null,
            'images' => json_encode([]), 'specs' => json_encode([]), 'unit' => 'шт',
            'is_active' => true, 'is_archived' => false, 'in_stock' => false, 'is_new' => true,
            'meta_title' => $name . ' купить в %city%',
            'meta_description' => $name . ' — купить в Беларуси.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function maybeDownloadImage(int $productId, string $url): void
    {
        $cur = DB::table('products')->where('id', $productId)->value('images');
        $arr = json_decode((string) $cur, true);
        if (is_array($arr) && array_filter($arr, fn ($x) => is_string($x) && trim($x) !== '' && $x !== '[]')) {
            return; // already has a photo
        }
        try {
            $resp = Http::timeout(25)->withOptions(['verify' => false])->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
            if (! $resp->successful() || ! str_starts_with((string) $resp->header('Content-Type'), 'image/')) {
                return;
            }
            $ext = match (true) {
                str_contains($url, '.png') => 'png',
                str_contains($url, '.webp') => 'webp',
                default => 'jpg',
            };
            $dir = public_path(self::IMAGE_DIR);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file = 'aq-' . $productId . '.' . $ext;
            file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $resp->body());
            DB::table('products')->where('id', $productId)->update([
                'images' => json_encode([self::IMAGE_DIR . '/' . $file], JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
            ]);
            $this->stats['images']++;
        } catch (\Throwable) {
        }
    }

    private function writeSpecs(int $productId, int $catId, array $specs): void
    {
        foreach ($specs as $rawName => $rawVal) {
            // "Максимальная мощность, Вт" → name + suffix
            $suffix = '';
            $name = trim($rawName);
            if (preg_match('/^(.*),\s*([^,]{1,12})$/u', $name, $mm)) {
                $name = trim($mm[1]);
                $suffix = trim($mm[2]);
            }
            $low = mb_strtolower($rawVal);
            $isCheck = in_array($low, ['да', 'нет', 'есть'], true);
            $value = $isCheck ? null : trim($rawVal);
            if (! $isCheck && $suffix !== '' && preg_match('/-?\d+(?:[.,]\d+)?/u', $rawVal, $vm)) {
                $value = str_replace(',', '.', $vm[0]);
            } elseif (! $isCheck && $suffix !== '') {
                continue; // unit attr without a number — skip
            }

            $attr = DB::table('attributes')->where('category_id', $catId)->where('name', $name)
                ->first(['id', 'type', 'suffix']);
            if (! $attr) {
                $attrId = (int) DB::table('attributes')->insertGetId([
                    'category_id' => $catId, 'type' => $isCheck ? 'check' : 'value', 'name' => $name,
                    'suffix' => $suffix ?: null, 'in_product' => true, 'in_filter' => false,
                    'in_brief' => false, 'in_sort' => false, 'is_comparable' => false,
                    'sort_order' => (int) DB::table('attributes')->where('category_id', $catId)->max('sort_order') + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $type = $isCheck ? 'check' : 'value';
            } else {
                $attrId = (int) $attr->id;
                $type = $attr->type;
            }

            if (DB::table('product_attribute_values')->where('product_id', $productId)->where('attribute_id', $attrId)->exists()) {
                continue;
            }
            DB::table('product_attribute_values')->insert([
                'product_id' => $productId, 'attribute_id' => $attrId, 'option_id' => null,
                'is_checked' => $type === 'check' ? ($low === 'да' || $low === 'есть' ? 1 : 0) : null,
                'value' => $type === 'check' ? null : $value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->stats['attrs_written']++;
        }
    }

    private function upsertSupplierProduct(int $productId, array $d, string $url, $now): void
    {
        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid === 0) {
            $sid = (int) DB::table('suppliers')->insertGetId([
                'code' => self::SUPPLIER_CODE, 'name' => 'ТСК Насосы', 'currency' => 'BYN', 'currency_rate' => 1,
                'contact' => self::BASE, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $art = mb_strtoupper(trim($d['article']));
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $sid, 'supplier_article' => $art],
            ['supplier_article_normalized' => $art, 'product_id' => $productId,
             'product_sku' => (string) DB::table('products')->where('id', $productId)->value('sku'),
             'supplier_name' => $d['name'], 'source_url' => $url,
             'price' => $d['price'], 'currency' => 'BYN', 'currency_rate' => 1.0, 'price_byn' => $d['price'],
             'in_stock' => $d['inStock'], 'stock_status' => $d['stockStatus'],
             'stock_text' => $d['stockText'] !== '' ? $d['stockText'] : null,
             'match_status' => 'matched', 'match_confidence' => 'aqualider_scrape',
             'last_stock_synced_at' => $now, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function fetch(string $url): ?string
    {
        try {
            $r = Http::timeout(25)->withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
                               'Accept-Language' => 'ru-RU,ru;q=0.9'])->get($url);
            return $r->successful() ? $r->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function nextSku(): string
    {
        $max = DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')
            ->map(fn ($s) => preg_match('/^KOTLOV-(\d+)$/', (string) $s, $m) ? (int) $m[1] : 0)->max() ?? 0;
        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());
        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'aqualider';
        $slug = $base; $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
