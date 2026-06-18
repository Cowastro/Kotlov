<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrich Лигмет brand products with photos, specs and AI content
 * scraped from teplodvor.by (covers Kratki, Ермак, Nordflam, Invicta, etc.)
 *
 *   php artisan supplier:enrich-teplodvor --dry-run
 *   php artisan supplier:enrich-teplodvor --brand=Kratki --apply
 *   php artisan supplier:enrich-teplodvor --brand=Ермак --apply --overwrite-images
 *   php artisan supplier:enrich-teplodvor --source-url=/shop/pechi-kaminy/invicta/ --apply
 */
class EnrichTeplodvorCommand extends Command
{
    protected $signature = 'supplier:enrich-teplodvor
        {--brand=      : Limit to one brand (e.g. Kratki)}
        {--source-url= : Category URL(s) comma-separated; overrides per-brand defaults}
        {--pages=10    : Max pages to crawl per URL}
        {--limit=      : Max products to process}
        {--sleep=700   : Delay between HTTP requests, ms}
        {--overwrite-images : Replace existing images}
        {--skip-ai     : Skip AI description/SEO generation}
        {--only-ai     : Only regenerate AI texts, skip images and specs}
        {--only-missing : Restrict catalog index to products without images}
        {--apply       : Write to DB (default: dry-run)}
        {--dry-run     : Preview only (default)}';

    protected $description = 'Enrich Лигмет brand products with photos/specs/AI from teplodvor.by';

    private const BASE = 'https://www.teplodvor.by';
    private const IMAGE_DIR = 'img/products/ligmet';
    private const SUPPLIER_CODE = 'ligmet';

    /**
     * Known brand listing pages on teplodvor.by.
     * Value = array of paths; all are crawled when --brand= targets this brand.
     */
    private const BRAND_SOURCES = [
        'Kratki'   => ['/shop/pechi-kaminy/kratki/'],
        'Ермак'    => [
            '/shop/pech-dlya-bani/ermak/',   // банные КЛАССИКА/СТАНДАРТ/ПРЕМИУМ
            '/shop/pechi-kaminy/ermak/',      // каминные STOKER
        ],
        'Nordflam' => ['/shop/pechi-kaminy/nordflam/'],
        'Invicta'  => ['/shop/pechi-kaminy/invicta/'],
        // Add more as discovered: Blist, FireWay, Ferguss, MBS, Panadero
    ];

    /** Catalog brand names (used for brand detection in product titles). */
    private const BRAND_SLUGS = [
        'kratki'   => 'Kratki',
        'invicta'  => 'Invicta',
        'blist'    => 'Blist',
        'fireway'  => 'FireWay',
        'ferguss'  => 'Ferguss',
        'mbs'      => 'MBS',
        'nordflam' => 'Nordflam',
        'panadero' => 'Panadero',
        'ermak'    => 'Ермак',
        'кпд'      => 'КПД',
    ];

    /** Same stopwords as enrich-100kaminov for consistent model() normalisation. */
    private const STOPWORDS = [
        'ПЕЧЬ','ПЕЧЬ-КАМИН','ПЕЧЬ-КАМИНЫ','КАМИН','КАМИННАЯ','КАМИННЫЙ','ТОПКА',
        'ПЕЧНОЙ','ДРОВЯНАЯ','ДРОВЯНОЙ','БАННАЯ','ОТОПИТЕЛЬНАЯ','ВАРОЧНАЯ',
        'СТАЛЬНАЯ','СТАЛЬНОЙ','ЧУГУННАЯ','ЧУГУННЫЙ',
        'СЕРАЯ','СЕРЫЙ','СЕРОЕ','СЕРЫЕ','ЧЁРНАЯ','ЧЁРНЫЙ','ЧЁРНОЕ','ЧЕРНАЯ','ЧЕРНЫЙ','ЧЕРНОЕ',
        'БЕЛАЯ','БЕЛЫЙ','БЕЛОЕ','БЕЖЕВАЯ','БЕЖЕВЫЙ','КРАСНАЯ','КРАСНЫЙ',
        'КОРИЧНЕВАЯ','КОРИЧНЕВЫЙ','ПАТИНА','АНТРАЦИТ','ГРАФИТ','КРЕМОВАЯ','КРЕМОВЫЙ',
        'GREY','GRAY','BLACK','WHITE','SATIN','CERAMIC','ECODESIGN',
        'STOVE','FIREPLACE',
        'EKO','PATINE',
        'С','ДУХОВКОЙ','КАМНЕМ','КРЫШКОЙ','ВОДЯНЫМ','ВЕНТИЛЯТОРОМ',
        'КУПИТЬ','МИНСКЕ','ДОСТАВКОЙ','ЦЕНА','ОПИСАНИЕ','ХАРАКТЕРИСТИКИ',
        // teplodvor.by spec suffixes in product names
        'КВТ','ОБШИВКА','THERMOTEC','КОНТУРОМ','KAFEL','КАФЕЛЬНАЯ','САДОВЫЙ','САДОВАЯ',
        'ЧУГУН','ЧУГУННОЙ','PREMIUM','AQUA','АКВА','STOKER',
        // Brand names as safety-net stopwords (handles Cyrillic/Latin mismatch like ERMAK≠ЕРМАК)
        'KRATKI','INVICTA','BLIST','FIREWAY','NORDFLAM','FERGUSS','MBS','PANADERO',
        'ERMAK','ЕРМАК',
        // Mojibake variants of "Ермак" from Лигмет price (mixed Cyrillic+Latin encoding)
        'ЕRMAK','ЕRМАК','ERМАК','ЕRМАК',
        // Russian "для бани" / "банно-отопительная" prefixes in product names
        'ДЛЯ','БАНИ','БАННО',
    ];

    private bool $apply;
    private int  $sleep;
    private array $catalogIndex = [];
    private array $stats = [
        'crawled' => 0, 'matched' => 0, 'enriched' => 0,
        'images'  => 0, 'specs'   => 0, 'ai_done'  => 0,
        'skipped' => 0, 'errors'  => 0,
    ];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->sleep = max(200, (int) $this->option('sleep'));

        $this->line($this->apply
            ? '<fg=red;options=bold>APPLY</>'
            : '<fg=yellow;options=bold>DRY RUN</>');

        $this->buildCatalogIndex();
        $this->info(sprintf('Catalog index: %d brands, %d products total',
            count($this->catalogIndex),
            array_sum(array_map('count', $this->catalogIndex))));

        if (! $this->apply) {
            $brandFilter = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;
            foreach ($this->catalogIndex as $bKey => $entries) {
                if ($brandFilter && $bKey !== $brandFilter) {
                    continue;
                }
                $keys = array_slice(array_keys($entries), 0, 30);
                $this->line(sprintf('  [%s] model keys: %s%s',
                    $bKey, implode(', ', $keys), count($entries) > 30 ? ' …' : ''));
            }
        }

        $brandFilter = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;
        $limit       = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $maxPages    = (int) $this->option('pages');
        $enriched    = 0;

        // Determine which URLs to crawl.
        if ($this->option('source-url')) {
            $paths = array_map('trim', explode(',', (string) $this->option('source-url')));
            $crawlPaths = [];
            foreach ($paths as $p) {
                $crawlPaths[] = str_starts_with($p, 'http') ? (parse_url($p, PHP_URL_PATH) ?? $p) : $p;
            }
        } elseif ($brandFilter) {
            $canonBrand = null;
            foreach (self::BRAND_SLUGS as $slug => $name) {
                if (mb_strtolower($name) === $brandFilter || $slug === $brandFilter) {
                    $canonBrand = $name;
                    break;
                }
            }
            if ($canonBrand && isset(self::BRAND_SOURCES[$canonBrand])) {
                $crawlPaths = self::BRAND_SOURCES[$canonBrand]; // already an array
            } else {
                $this->error("No default teplodvor.by URL for brand '{$brandFilter}'. Use --source-url=");
                return self::FAILURE;
            }
        } else {
            // Flatten: [brand => [path, ...], ...] → [path, ...]
            $crawlPaths = array_merge(...array_values(self::BRAND_SOURCES));
        }

        $seenUrls = [];

        foreach ($crawlPaths as $basePath) {
            if ($enriched >= $limit) {
                break;
            }
            $this->newLine();
            $this->info("Crawling: {$basePath}");

            for ($page = 1; $page <= $maxPages; $page++) {
                $pageUrl = $this->pageUrl($basePath, $page);
                $links   = $this->collectLinks($pageUrl);

                if ($links === []) {
                    $this->line("  page {$page}: no links, stopping.");
                    break;
                }

                $newLinks = array_filter($links, fn ($l) => ! isset($seenUrls[$l]));
                if (empty($newLinks)) {
                    $this->line("  page {$page}: no new links, stopping.");
                    break;
                }
                foreach ($newLinks as $l) {
                    $seenUrls[$l] = true;
                }

                $this->line("  page {$page}: " . count($newLinks) . ' products');

                foreach ($newLinks as $productUrl) {
                    if ($enriched >= $limit) {
                        break 2;
                    }
                    try {
                        if ($this->processProduct($productUrl)) {
                            $enriched++;
                        }
                    } catch (\Throwable $e) {
                        $this->stats['errors']++;
                        $this->warn("  error [{$productUrl}]: " . $e->getMessage());
                    }
                    usleep($this->sleep * 1000);
                }
                usleep($this->sleep * 1000);
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Catalog index ─────────────────────────────────────────────────────────────

    private function buildCatalogIndex(): void
    {
        $brandFilter = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;

        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid === 0) {
            $this->warn('Supplier "ligmet" not found — indexing all products of these brands.');
        }

        $brandsQ = DB::table('brands')->whereIn('name', array_values(self::BRAND_SLUGS));
        if ($brandFilter) {
            $brandsQ->whereRaw('LOWER(name) = ?', [$brandFilter]);
        }
        $brands = $brandsQ->pluck('id', 'name');

        foreach ($brands as $brandName => $brandId) {
            $key = mb_strtolower($brandName);
            $this->catalogIndex[$key] = [];

            $query = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('is_archived', false);

            if ($sid > 0) {
                $pids = DB::table('supplier_products')
                    ->where('supplier_id', $sid)
                    ->whereNotNull('product_id')
                    ->pluck('product_id');
                $query->whereIn('id', $pids);
            }

            if ($this->option('only-missing')) {
                $query->where(function ($q) {
                    $q->whereNull('images')->orWhere('images', '')->orWhere('images', '[]');
                });
            }

            $query->get(['id', 'name', 'images'])->each(function ($p) use ($key, $brandName) {
                $model = $this->model((string) $p->name, $brandName);
                if ($model !== '') {
                    $hasImages = ! empty(json_decode((string) ($p->images ?? '[]'), true));
                    $this->catalogIndex[$key][$model] = [
                        'id'         => (int) $p->id,
                        'name'       => $p->name,
                        'has_images' => $hasImages,
                    ];
                }
            });
        }
    }

    // ── Crawl ─────────────────────────────────────────────────────────────────────

    /** Build paginated URL using Bitrix SEF pattern: /path/page{n}/?query */
    private function pageUrl(string $path, int $page): string
    {
        if ($page <= 1) {
            return self::BASE . $path;
        }
        $q     = parse_url($path, PHP_URL_QUERY);
        $clean = rtrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        return self::BASE . $clean . '/page' . $page . '/' . ($q ? '?' . $q : '');
    }

    /** Extract product page URLs from a teplodvor.by listing page. */
    private function collectLinks(string $url): array
    {
        $html = $this->fetch($url);
        if ($html === null) {
            return [];
        }

        $links = [];

        // Each product block contains a hidden good_id field (Bitrix product ID).
        // The link to the product page uses class="shop-item-link".
        preg_match_all('/name="good_id"\s+value="(\d+)"/', $html, $idMatches);
        preg_match_all('/href="(https?:\/\/www\.teplodvor\.by\/[^"]+)"\s[^>]*class="shop-item-link"/', $html, $linkMatches);
        // Also try reversed attribute order.
        preg_match_all('/class="shop-item-link"\s[^>]*href="(https?:\/\/www\.teplodvor\.by\/[^"]+)"/', $html, $linkMatches2);

        foreach (array_unique(array_merge($linkMatches[1] ?? [], $linkMatches2[1] ?? [])) as $href) {
            $links[] = $href;
        }

        $unique = array_values(array_unique($links));
        $this->stats['crawled'] += count($unique);
        return $unique;
    }

    // ── Product processing ────────────────────────────────────────────────────────

    private function processProduct(string $url): bool
    {
        $html = $this->fetch($url);
        if ($html === null) {
            $this->stats['errors']++;
            return false;
        }

        $card = $this->parsePage($html);
        if ($card['name'] === '') {
            return false;
        }

        $brandKey  = $this->detectBrand($card['name']);
        if ($brandKey === null) {
            return false;
        }
        $brandName = self::BRAND_SLUGS[$brandKey];
        $modelKey  = $this->model($card['name'], $brandName);

        $entry = $this->catalogIndex[mb_strtolower($brandName)][$modelKey] ?? null;

        $this->line(sprintf('  [%s] %s → %s → %s',
            $brandName,
            mb_substr($card['name'], 0, 40),
            $modelKey,
            $entry ? "pid={$entry['id']}" : 'NO MATCH'));

        if ($entry === null) {
            $this->stats['skipped']++;
            return false;
        }

        $this->stats['matched']++;

        if (! $this->apply) {
            foreach (array_slice($card['specs'], 0, 4, true) as $k => $v) {
                $this->line("    · {$k}: {$v}");
            }
            $this->line('    · images: ' . count($card['images']));
            return true;
        }

        $pid     = $entry['id'];
        $now     = now();
        $changed = false;

        $onlyAi = (bool) $this->option('only-ai');

        // Images.
        if (! $onlyAi) {
            $written = $this->downloadImages($pid, $card['images'], $entry['has_images']);
            if ($written > 0) {
                $this->stats['images'] += $written;
                $changed = true;
            }
        }

        // Specs.
        if (! $onlyAi && $card['specs'] !== []) {
            $this->stats['specs'] += $this->writeSpecs($pid, $card['specs']);
        }

        // AI enrichment: always generate unique SEO content (never copy supplier text verbatim).
        if (! $this->option('skip-ai')) {
            $this->generateAiContent($pid, $card, $brandName, $now);
        }

        if ($changed) {
            DB::table('products')->where('id', $pid)->update(['updated_at' => $now]);
        }

        // Record source URL.
        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid > 0) {
            DB::table('supplier_products')
                ->where('supplier_id', $sid)->where('product_id', $pid)
                ->update(['source_url' => $url, 'updated_at' => $now]);
        }

        $this->stats['enriched']++;
        return true;
    }

    // ── Parsing ───────────────────────────────────────────────────────────────────

    private function parsePage(string $html): array
    {
        // Name: <h1> tag.
        $name = $this->cleanText(preg_match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html, $m) ? $m[1] : '');

        // Images: full-size in /userfls/shop/large/ (same pattern as SyncTeplodarCommand).
        preg_match_all('/userfls\/shop\/large\/([\d]+\/[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $m);
        $images = [];
        foreach (array_unique($m[1] ?? []) as $path) {
            $images[] = self::BASE . '/userfls/shop/large/' . $path;
        }

        // Specs: <td class="parametr"><span>name</span></td><td>value</td>
        // (same structure as SyncTeplodarCommand confirmed in production)
        $specs = [];
        preg_match_all(
            '/<td[^>]*class="parametr"[^>]*>\s*<span[^>]*>([\s\S]*?)<\/span>\s*<\/td>\s*<td[^>]*>([\s\S]*?)<\/td>/u',
            $html, $m, PREG_SET_ORDER
        );
        foreach ($m as $row) {
            $key = $this->cleanText($row[1]);
            $val = $this->cleanText($row[2]);
            if ($key !== '' && $val !== '' && $key !== $val && mb_strlen($key) <= 120) {
                $specs[$key] = $val;
            }
        }
        // Fallback: plain <tr><td>name</td><td>value</td></tr> table rows.
        if ($specs === []) {
            preg_match_all('/<tr[^>]*>\s*<td[^>]*>([\s\S]*?)<\/td>\s*<td[^>]*>([\s\S]*?)<\/td>\s*<\/tr>/u', $html, $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $key = $this->cleanText($row[1]);
                $val = $this->cleanText($row[2]);
                if ($key !== '' && $val !== '' && $key !== $val && mb_strlen($key) <= 120) {
                    $specs[$key] = $val;
                }
            }
        }

        // Description: <section id="description"> (confirmed by SyncTeplodarCommand).
        $desc = '';
        if (preg_match('/<section[^>]*id=["\']description["\'][^>]*>([\s\S]*?)<\/section>/u', $html, $m)) {
            $raw = preg_replace('/<(script|style)\b[\s\S]*?<\/\1>/iu', '', $m[1]) ?? $m[1];
            $desc = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        return compact('name', 'images', 'specs', 'desc');
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function detectBrand(string $name): ?string
    {
        $upper = mb_strtoupper($name);
        foreach (self::BRAND_SLUGS as $slug => $canonical) {
            if (mb_stripos($upper, mb_strtoupper($canonical)) !== false) {
                return $slug;
            }
        }
        return null;
    }

    private function model(string $name, string $brand): string
    {
        $n = mb_strtoupper($name);
        if ($brand !== '') {
            $n = preg_replace('/' . preg_quote(mb_strtoupper($brand), '/') . '/u', '', $n) ?? $n;
        }
        $n    = preg_replace('/[^А-ЯЁA-Z0-9]/u', ' ', $n) ?? $n;
        $toks = array_filter(
            preg_split('/\s+/u', trim($n)) ?: [],
            fn ($t) => $t !== ''
                && ! in_array($t, self::STOPWORDS, true)
                && ! preg_match('/^P\d{5,}$/', $t)          // Invicta product codes
                && ! preg_match('/^\d+КВТ$/u', $t)           // power suffix: 8КВТ, 7КВТ
                && ! preg_match('/^\d{3,}$/', $t)            // 3+ digit specs: Ø150, 2025
        );
        return implode(' ', $toks);
    }

    // ── Images ────────────────────────────────────────────────────────────────────

    private function downloadImages(int $pid, array $urls, bool $hasImages): int
    {
        if ($hasImages && ! $this->option('overwrite-images')) {
            return 0;
        }

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved   = [];
        $hashes  = [];
        $written = 0;

        foreach (array_slice($urls, 0, 8) as $imgUrl) {
            $body = $this->fetch($imgUrl, true);
            if ($body === null || strlen($body) < 2000) {
                continue;
            }
            $size = @getimagesizefromstring($body);
            if (! $size || $size[0] < 100 || $size[1] < 100) {
                continue;
            }
            $md5 = md5($body);
            if (isset($hashes[$md5])) {
                continue;
            }
            $hashes[$md5] = true;

            $ext  = match ($size['mime']) {
                'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
            };
            $file = $pid . '_' . $written . '.' . $ext;
            file_put_contents($dir . '/' . $file, $body);
            $saved[] = self::IMAGE_DIR . '/' . $file;
            $written++;
            usleep(200_000);
        }

        if ($saved !== []) {
            DB::table('products')->where('id', $pid)->update([
                'images' => json_encode(array_values($saved)),
            ]);
        }

        return $written;
    }

    // ── Specs ─────────────────────────────────────────────────────────────────────

    private function writeSpecs(int $pid, array $specs): int
    {
        $catId   = (int) DB::table('products')->where('id', $pid)->value('category_id');
        $written = 0;
        $now     = now();

        foreach ($specs as $key => $val) {
            $attrId = DB::table('attributes')
                ->where('category_id', $catId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($key)])
                ->value('id');

            if (! $attrId) {
                $attrId = DB::table('attributes')->insertGetId([
                    'category_id' => $catId,
                    'name'        => $key,
                    'type'        => 'value',
                    'in_filter'   => false,
                    'in_product'  => true,
                    'in_brief'    => false,
                    'sort_order'  => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $pid, 'attribute_id' => $attrId],
                ['value' => (string) $val, 'updated_at' => $now, 'created_at' => $now]
            );
            $written++;
        }

        return $written;
    }

    // ── AI content ────────────────────────────────────────────────────────────────

    private function generateAiContent(int $pid, array $card, string $brand, $now): void
    {
        $enricher = app(AiContentEnricher::class);
        if (! $enricher->isAvailable()) {
            return;
        }

        $existing = DB::table('products')
            ->where('id', $pid)
            ->first(['short_description', 'content', 'name', 'category_id']);
        if (! $existing) {
            return;
        }

        $updates  = [];
        $rawDesc  = $card['desc'] ?? '';

        // Same approach as admin "Обновить из ссылки": raw supplier text is context only,
        // AI generates unique SEO HTML; raw text is never stored.
        $aiContent = $enricher->enrich((string) $existing->name, $brand, $rawDesc, $card['specs']);
        if ($aiContent !== null && trim(strip_tags($aiContent)) !== '') {
            $updates['content'] = strip_tags($aiContent, '<p><ul><li><strong>');

            $short = $enricher->shortDescription((string) $existing->name, $brand, $card['specs'])
                ?: mb_substr(trim(strip_tags($aiContent)), 0, 240);
            $updates['short_description'] = mb_substr(trim($short), 0, 240);
            $updates['meta_description']  = mb_substr(trim($short), 0, 250);
        }

        if ($updates !== []) {
            $updates['updated_at'] = $now;
            DB::table('products')->where('id', $pid)->update($updates);
            $this->stats['ai_done']++;
        }
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────────

    private function fetch(string $url, bool $binary = false): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 30,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'header'          => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept: text/html,application/xhtml+xml,*/*;q=0.9',
                    'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
                    'Referer: https://www.teplodvor.by/',
                ]),
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || (! $binary && strlen($body) < 500)) {
            return null;
        }
        return $body;
    }
}
