<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScrapesAqualiderCard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

    use ScrapesAqualiderCard;

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
            $html = $this->fetchCard($url);
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
        $html = $this->fetchCard($url);
        if ($html === null) {
            $this->stats['errors']++;
            return;
        }
        $d = $this->parseCard($html, $url);
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

        // Stock from the SITE (sheet sync later overrides for products in the price).
        DB::table('products')->where('id', $productId)->update(['in_stock' => $d['inStock'], 'updated_at' => $now]);

        if ($d['image'] !== null && $this->downloadCardImage($productId, $d['image'], self::IMAGE_DIR)) {
            $this->stats['images']++;
        }
        $this->stats['attrs_written'] += $this->writeCardSpecs($productId, $catId, $d['specs']);
        $this->upsertSupplierProduct($productId, $d, $url, $now);
    }

    // ── Parsing ───────────────────────────────────────────────────────────────────

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
            'is_active' => true, 'is_archived' => false, 'in_stock' => $d['inStock'], 'is_new' => true,
            'meta_title' => $name . ' купить в %city%',
            'meta_description' => $name . ' — купить в Беларуси.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
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
        // Fold Cyrillic look-alikes to Latin so the article matches the price sheet.
        $art = strtr(mb_strtoupper(trim($d['article'])),
            ['А'=>'A','В'=>'B','Е'=>'E','К'=>'K','М'=>'M','Н'=>'H','О'=>'O','Р'=>'P','С'=>'C','Т'=>'T','У'=>'Y','Х'=>'X']);
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $sid, 'supplier_article' => $art],
            // Purchase price (Опт1) comes ONLY from the price sheet (sync-tsk-nasosy).
            // Stock is taken from the SITE here (sheet overrides it for priced items).
            ['supplier_article_normalized' => $art, 'product_id' => $productId,
             'product_sku' => (string) DB::table('products')->where('id', $productId)->value('sku'),
             'supplier_name' => $d['name'], 'source_url' => $url,
             'in_stock' => $d['inStock'], 'stock_status' => $d['stockStatus'],
             'stock_text' => $d['stockText'] !== '' ? $d['stockText'] : null,
             'last_stock_synced_at' => $now,
             'match_status' => 'matched', 'match_confidence' => 'aqualider_scrape',
             'updated_at' => $now, 'created_at' => $now]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

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
