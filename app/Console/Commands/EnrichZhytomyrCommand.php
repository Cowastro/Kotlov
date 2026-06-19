<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrich АТЕМ / Житомир products with photos and AI descriptions
 * scraped from teplodvor.by.
 *
 * Matching strategy: extract GKB supplier_article from page title
 * (same patterns as SyncGazKotelBelCommand.extractArticle) then look up
 * in supplier_products WHERE supplier_id=gazkotelbel.
 *
 *   php artisan supplier:enrich-zhytomyr --dry-run
 *   php artisan supplier:enrich-zhytomyr --apply
 *   php artisan supplier:enrich-zhytomyr --apply --only-missing
 *   php artisan supplier:enrich-zhytomyr --apply --overwrite-images --skip-ai
 *   php artisan supplier:enrich-zhytomyr --apply --only-ai
 */
class EnrichZhytomyrCommand extends Command
{
    protected $signature = 'supplier:enrich-zhytomyr
        {--dry-run      : Preview only (default)}
        {--apply        : Write changes to DB}
        {--only-missing : Skip products that already have images}
        {--overwrite-images : Replace existing images}
        {--skip-ai      : Skip AI description generation}
        {--only-ai      : Only regenerate AI texts, skip images}
        {--pages=5      : Max pages to crawl per source URL}
        {--sleep=700    : Delay between HTTP requests, ms}
        {--limit=       : Max products to process}';

    protected $description = 'Enrich АТЕМ/Житомир products with photos and AI content from teplodvor.by';

    private const BASE         = 'https://www.teplodvor.by';
    private const IMAGE_DIR    = 'img/products/zhytomyr';
    private const SUPPLIER_CODE = 'gazkotelbel';

    /** teplodvor.by category paths to crawl */
    private const SOURCES = [
        '/shop/kotly/gazovye/gazovye_atem/',
        '/shop/vodonagrevateli/gazovye kolonki/',
        '/shop/konvektory/gazovye/',
    ];

    private bool  $apply;
    private int   $sleep;
    private array $articleIndex = [];  // supplier_article → product_id
    private array $stats = [
        'crawled' => 0, 'matched' => 0, 'enriched' => 0,
        'images'  => 0, 'ai_done' => 0,
        'skipped' => 0, 'errors'  => 0,
    ];

    // ── Entry point ───────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->sleep = max(300, (int) $this->option('sleep'));

        $this->line($this->apply
            ? '<fg=red;options=bold>APPLY — database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN — no changes will be written.</>');

        $this->buildArticleIndex();
        $this->info(sprintf('Article index: %d GKB supplier_products loaded', count($this->articleIndex)));

        if (! $this->apply) {
            $sample = array_slice($this->articleIndex, 0, 10, true);
            foreach ($sample as $article => $entry) {
                $this->line("  {$article} → pid={$entry['id']}");
            }
        }

        $limit    = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $maxPages = (int) $this->option('pages');
        $enriched = 0;
        $seenUrls = [];

        foreach (self::SOURCES as $basePath) {
            if ($enriched >= $limit) {
                break;
            }
            $this->newLine();
            $this->info("Crawling: {$basePath}");

            for ($page = 1; $page <= $maxPages; $page++) {
                $pageUrl = $this->pageUrl($basePath, $page);
                $links   = $this->collectLinks($pageUrl);

                if ($links === []) {
                    $this->line("  page {$page}: no links — done.");
                    break;
                }

                $newLinks = array_filter($links, fn ($l) => ! isset($seenUrls[$l]));
                if (empty($newLinks)) {
                    $this->line("  page {$page}: no new links — done.");
                    break;
                }
                foreach ($newLinks as $l) {
                    $seenUrls[$l] = true;
                }

                $this->line("  page {$page}: " . count($newLinks) . ' links');

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
        $this->table(
            ['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats))
        );

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Catalog index ─────────────────────────────────────────────────────────────

    private function buildArticleIndex(): void
    {
        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid === 0) {
            $this->warn('Supplier gazkotelbel not found.');
            return;
        }

        $query = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $sid)
            ->whereNotNull('sp.product_id')
            ->where('p.is_archived', false);

        if ($this->option('only-missing')) {
            $query->where(function ($q) {
                $q->whereNull('p.images')->orWhere('p.images', '')->orWhere('p.images', '[]');
            });
        }

        $rows = $query->get(['sp.supplier_article', 'sp.product_id', 'p.images']);

        foreach ($rows as $row) {
            $this->articleIndex[$row->supplier_article] = [
                'id'         => (int) $row->product_id,
                'has_images' => ! empty(json_decode((string) ($row->images ?? '[]'), true)),
            ];
        }
    }

    // ── Crawl ─────────────────────────────────────────────────────────────────────

    private function pageUrl(string $path, int $page): string
    {
        if ($page <= 1) {
            return self::BASE . $path;
        }
        $clean = rtrim($path, '/');
        return self::BASE . $clean . '/page' . $page . '/';
    }

    private function collectLinks(string $url): array
    {
        $html = $this->fetch($url);
        if ($html === null) {
            return [];
        }

        preg_match_all(
            '/href="(https?:\/\/www\.teplodvor\.by\/[^"]+)"\s[^>]*class="shop-item-link"/',
            $html, $m1
        );
        preg_match_all(
            '/class="shop-item-link"\s[^>]*href="(https?:\/\/www\.teplodvor\.by\/[^"]+)"/',
            $html, $m2
        );

        $links = array_values(array_unique(array_merge($m1[1] ?? [], $m2[1] ?? [])));
        $this->stats['crawled'] += count($links);
        return $links;
    }

    // ── Product processing ────────────────────────────────────────────────────────

    private function processProduct(string $url): bool
    {
        $html = $this->fetch($url);
        if ($html === null) {
            return false;
        }

        $card = $this->parsePage($html);
        if ($card['name'] === '') {
            return false;
        }

        $article = $this->extractArticleFromTitle($card['name']);
        $entry   = $article ? ($this->articleIndex[$article] ?? null) : null;

        // Fallback: try normalised variants (дефис/пробел вариации)
        if ($entry === null && $article !== null) {
            $variants = $this->articleVariants($article);
            foreach ($variants as $v) {
                if (isset($this->articleIndex[$v])) {
                    $entry   = $this->articleIndex[$v];
                    $article = $v;
                    break;
                }
            }
        }

        $this->line(sprintf('  [%s] %s → %s',
            $article ?? '?',
            mb_substr($card['name'], 0, 50),
            $entry ? "pid={$entry['id']}" : 'NO MATCH'));

        if ($entry === null) {
            $this->stats['skipped']++;
            return false;
        }

        $this->stats['matched']++;

        if (! $this->apply) {
            $this->line('    images: ' . count($card['images']));
            if ($card['specs']) {
                foreach (array_slice($card['specs'], 0, 4, true) as $k => $v) {
                    $this->line("    · {$k}: {$v}");
                }
            }
            return true;
        }

        $pid     = $entry['id'];
        $now     = now();
        $onlyAi  = (bool) $this->option('only-ai');

        if (! $onlyAi) {
            $written = $this->downloadImages($pid, $card['images'], $entry['has_images']);
            $this->stats['images'] += $written;
        }

        if (! $this->option('skip-ai')) {
            $existingContent = (string) DB::table('products')->where('id', $pid)->value('content');
            if ($onlyAi || trim($existingContent) === '') {
                $this->generateAiContent($pid, $card, $now);
            }
        }

        DB::table('products')->where('id', $pid)->update(['updated_at' => $now]);
        $this->stats['enriched']++;
        return true;
    }

    // ── Article extraction ────────────────────────────────────────────────────────

    /**
     * Convert teplodvor.by product title to a GKB supplier_article.
     *
     * Examples:
     *   "Газовый котел Житомир-10 КС-Г-012 СН (в комплекте)" → "Ж10-КС-Г-012СН"
     *   "Газовый котел Atem Житомир-Турбо КС-Г-010 СН"       → "ТУРБО-КС-Г-010СН"
     *   "Газовый котел Atem Житомир-М АОГВ 10 СН"            → "АОГВ-10СН"
     *   "АТЕМ ВПГ-20Т"                                        → "ВПГ-20Т"
     */
    private function extractArticleFromTitle(string $name): ?string
    {
        // Normalise spaces before "СН": "010 СН" → "010СН"
        $n = preg_replace('/(\d+)\s+СН\b/iu', '${1}СН', $name) ?? $name;

        // Житомир-Турбо КС-Г-010СН → ТУРБО-КС-Г-010СН
        if (preg_match('/Турбо\s+(КС-Г[В]?-\d+[А-Яа-я]*)/iu', $n, $m)) {
            return 'ТУРБО-' . mb_strtoupper($m[1]);
        }

        // Житомир-3 КС-Г-012СН / Житомир-10 КС-ГВ-015СН
        if (preg_match('/Житомир-(\d+)\s+(КС-Г[В]?-\d+[А-Яа-я]*)/iu', $n, $m)) {
            return 'Ж' . $m[1] . '-' . mb_strtoupper($m[2]);
        }

        // "Ж-3 КС-Г-007СН" format
        if (preg_match('/Ж-(\d+)\s+(КС-Г[В]?-\d+[А-Яа-я]*)/u', $n, $m)) {
            return 'Ж' . $m[1] . '-' . mb_strtoupper($m[2]);
        }

        // АОГВ -10СН / АОГВ 10 / АОГВ-10СН
        if (preg_match('/(А[ОД]ГВ)[\s\-]+(\d+[А-Яа-я]*)/u', $n, $m)) {
            $num = mb_strtoupper($m[2]);
            if (! str_ends_with($num, 'СН')) {
                $num .= 'СН';
            }
            return mb_strtoupper($m[1]) . '-' . $num;
        }

        // ВПГ-20Т / ВПГ-20М / ВПГ-20ТМ
        if (preg_match('/(ВПГ-\d+[А-Яа-яA-Za-z]*)/u', $n, $m)) {
            return $m[1];
        }

        // КНС-5 / КНС-8 (газовые конвекторы)
        if (preg_match('/КНС-(\d+)/iu', $n, $m)) {
            return 'КНС-' . $m[1];
        }

        return null;
    }

    /** Try minor article variations (padding, case). */
    private function articleVariants(string $article): array
    {
        $variants = [];

        // "Ж3-КС-Г-10СН" ↔ "Ж3-КС-Г-010СН" (zero-padding)
        if (preg_match('/^(Ж\d+-КС-Г[В]?-)(\d+)(СН)$/iu', $article, $m)) {
            $padded   = $m[1] . str_pad($m[2], 3, '0', STR_PAD_LEFT) . $m[3];
            $unpadded = $m[1] . ltrim($m[2], '0') . $m[3];
            $variants[] = $padded;
            $variants[] = $unpadded;
        }

        // "ТУРБО-КС-Г-010СН" ↔ "ТУРБО-КС-Г-10СН" (zero-padding)
        if (preg_match('/^(ТУРБО-КС-Г[В]?-)(\d+)(СН)$/iu', $article, $m)) {
            $padded   = $m[1] . str_pad($m[2], 3, '0', STR_PAD_LEFT) . $m[3];
            $unpadded = $m[1] . ltrim($m[2], '0') . $m[3];
            $variants[] = $padded;
            $variants[] = $unpadded;
        }

        return array_unique($variants);
    }

    // ── Page parsing ──────────────────────────────────────────────────────────────

    private function parsePage(string $html): array
    {
        $name = $this->cleanText(preg_match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html, $m) ? $m[1] : '');

        // Full-size images in /userfls/shop/large/
        preg_match_all('/userfls\/shop\/(?:large|product)\/([\d\/]+[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $m);
        $images = [];
        foreach (array_unique($m[1] ?? []) as $path) {
            // Prefer /large/ over /small/
            if (str_contains($path, 'small')) {
                continue;
            }
            $images[] = self::BASE . '/userfls/shop/large/' . $path;
        }
        // Fallback: product/ path images
        if (empty($images)) {
            preg_match_all('/userfls\/shop\/product\/([\d\/]+[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $m);
            foreach (array_unique($m[1] ?? []) as $path) {
                $images[] = self::BASE . '/userfls/shop/product/' . $path;
            }
        }

        // Specs: <td class="parametr"><span>name</span></td><td>value</td>
        $specs = [];
        preg_match_all(
            '/<td[^>]*class="parametr"[^>]*>\s*<span[^>]*>([\s\S]*?)<\/span>\s*<\/td>\s*<td[^>]*>([\s\S]*?)<\/td>/u',
            $html, $m, PREG_SET_ORDER
        );
        foreach ($m as $row) {
            $k = $this->cleanText($row[1]);
            $v = $this->cleanText($row[2]);
            if ($k !== '' && $v !== '' && $k !== $v) {
                $specs[$k] = $v;
            }
        }
        // Fallback: plain tr > td pairs
        if ($specs === []) {
            preg_match_all('/<tr[^>]*>\s*<td[^>]*>([\s\S]*?)<\/td>\s*<td[^>]*>([\s\S]*?)<\/td>/u', $html, $m, PREG_SET_ORDER);
            foreach ($m as $row) {
                $k = $this->cleanText($row[1]);
                $v = $this->cleanText($row[2]);
                if ($k !== '' && $v !== '' && $k !== $v && mb_strlen($k) <= 100) {
                    $specs[$k] = $v;
                }
            }
        }

        // Description: <section id="description">
        $desc = '';
        if (preg_match('/<section[^>]*id=["\']description["\'][^>]*>([\s\S]*?)<\/section>/u', $html, $m)) {
            $raw  = preg_replace('/<(script|style)\b[\s\S]*?<\/\1>/iu', '', $m[1]) ?? $m[1];
            $desc = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $desc = preg_replace('/\s{2,}/u', ' ', $desc) ?? $desc;
        }

        return compact('name', 'images', 'specs', 'desc');
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    // ── Images ────────────────────────────────────────────────────────────────────

    private function downloadImages(int $pid, array $urls, bool $hasImages): int
    {
        if ($hasImages && ! $this->option('overwrite-images')) {
            $this->line("    skip images (already has photos)");
            return 0;
        }

        if (empty($urls)) {
            $this->line("    no images found on page");
            return 0;
        }

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved   = [];
        $hashes  = [];
        $written = 0;

        foreach (array_slice($urls, 0, 6) as $imgUrl) {
            $body = $this->fetch($imgUrl, true);
            if ($body === null || strlen($body) < 3000) {
                continue;
            }
            $size = @getimagesizefromstring($body);
            if (! $size || $size[0] < 200 || $size[1] < 200) {
                continue;
            }
            $md5 = md5($body);
            if (isset($hashes[$md5])) {
                continue;
            }
            $hashes[$md5] = true;

            $ext  = match ($size['mime'] ?? '') {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default      => 'jpg',
            };
            $file = $pid . '_' . $written . '.' . $ext;
            file_put_contents($dir . '/' . $file, $body);
            $saved[] = self::IMAGE_DIR . '/' . $file;
            $written++;
            usleep(200_000);
        }

        if ($saved !== []) {
            DB::table('products')->where('id', $pid)->update([
                'images'     => json_encode(array_values($saved), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            $this->line("    saved {$written} image(s)");
        }

        return $written;
    }

    // ── AI content ────────────────────────────────────────────────────────────────

    private function generateAiContent(int $pid, array $card, $now): void
    {
        $enricher = app(AiContentEnricher::class);
        if (! $enricher->isAvailable()) {
            $this->warn('    AI enricher not available');
            return;
        }

        $product = DB::table('products')->where('id', $pid)->first(['name', 'specs', 'short_description', 'content']);
        if (! $product) {
            return;
        }

        // Merge specs: gazkotelbel specs + teplodvor page specs (teplodvor wins on conflict)
        $existingSpecs = json_decode((string) ($product->specs ?? '{}'), true) ?: [];
        $mergedSpecs   = array_merge($existingSpecs, $card['specs']);

        $aiContent = $enricher->enrich(
            (string) $product->name,
            'АТЕМ',
            $card['desc'],
            $mergedSpecs
        );

        if ($aiContent !== null && trim(strip_tags($aiContent)) !== '') {
            $short = $enricher->shortDescription(
                (string) $product->name,
                'АТЕМ',
                $mergedSpecs
            ) ?: mb_substr(trim(strip_tags($aiContent)), 0, 240);

            DB::table('products')->where('id', $pid)->update([
                'content'          => strip_tags($aiContent, '<p><ul><li><strong>'),
                'short_description'=> mb_substr(trim($short), 0, 240),
                'meta_description' => mb_substr(trim($short), 0, 250),
                'updated_at'       => $now,
            ]);
            $this->stats['ai_done']++;
            $this->line('    AI content generated');
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
                    'Accept-Language: ru-RU,ru;q=0.9',
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
