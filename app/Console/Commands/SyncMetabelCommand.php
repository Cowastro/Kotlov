<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncMetabelCommand extends Command
{
    protected $signature = 'supplier:sync-metabel
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit number of items for testing}
        {--no-images : Ignored; MetaBel Excel has no image URLs}
        {--price-file= : Path to Excel price file (default: storage/prices/meta_2025.xlsx)}';

    protected $description = 'Sync MetaBel prices from Excel МРЦ price list.';

    private const SUPPLIER_CODE = 'metabel';
    private const SYNC_KEY      = 'metabel_price';
    private const BRAND_ID      = 45;
    private const SOURCE_URL    = 'https://metabel.by/produktsiya';

    private const CATEGORY_KEYWORDS = [
        'ПЕЧИ БАННЫЕ'        => 69,
        'ПЕЧИ-КАМИНЫ'        => 61,
        'ТОПКИ КАМИННЫЕ'     => 90,
        'ДВЕРИ ПЕЧНЫЕ'       => 287,
        'ГРИЛИ И АКСЕССУАРЫ' => null,
    ];

    // Supplier article → our SKU for cases where name normalization can't resolve the match.
    // "Ока с плитой" vs "с варочной панелью", "ПБМ 20В" vs "ПБМ 20 с вермикулитом".
    private const MANUAL_MATCH = [
        // Name mismatch: cannot be resolved by normalization alone
        'ОКА С ПЛИТОЙ'                            => 'PS-002.811', // "плитой" vs "варочной панелью"
        'ПЕЧЬ БАННАЯ ПБМ 20В (С ВЕРМИКУЛИТОМ)'    => 'PS-009.545', // "20В" vs "20 с вермикулитом"
        // ПС-варианты: normalizePriceName extracts only "ПС" from "(в модификации ПС)"
        'ПЕЧЬ БАННАЯ ПБМ 16 (В МОДИФИКАЦИИ ПС)'   => 'PS-006.589',
        'ПЕЧЬ БАННАЯ ПБМ 20 (В МОДИФИКАЦИИ ПС)'   => 'PS-012.050',
        // Doors renamed in new price list (ДП-01 was "Волга", ДП-02 was "Енисей" etc.)
        'ДВЕРЬ ПЕЧНАЯ ДП-01'                      => 'PS-001.899',
        'ДВЕРЬ ПЕЧНАЯ ДП-02'                      => 'PS-001.900',
        'ДВЕРЬ ПЕЧНАЯ ДП-05'                      => 'PS-001.901',
        'ДВЕРЬ КАМИННАЯ ДК-01'                    => 'PS-001.902',
    ];

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $limit     = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $priceFile = $this->option('price-file') ?: storage_path('prices/meta_2025.xlsx');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        if (! file_exists($priceFile)) {
            $this->error("Price file not found: {$priceFile}");
            return self::FAILURE;
        }

        try {
            $items = $this->parsePriceFile($priceFile);
        } catch (\Throwable $e) {
            $this->error('Failed to parse price file: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d items from price file.', count($items)));

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        if (! $apply) {
            return $this->dryRun($items);
        }

        $now        = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId     = $this->ensureSupplierSync($now);

        $stats = [
            'created'           => 0,
            'update_price'      => 0,
            'no_change'         => 0,
            'skipped_no_price'  => 0,
            'skipped_duplicate' => 0,
            'errors'            => 0,
        ];

        foreach ($items as $item) {
            if (($item['action'] ?? null) === 'skipped_duplicate') {
                $stats['skipped_duplicate']++;
                continue;
            }

            if (($item['price_byn'] ?? null) === null || $item['price_byn'] <= 0) {
                $stats['skipped_no_price']++;
                $this->line('[skip/no_price] ' . mb_substr($item['price_name'], 0, 60));
                continue;
            }

            try {
                $product = $this->findProduct($item, $supplierId);

                if (! $product) {
                    $productId = $this->createProduct($item, $now);
                    $sku       = (string) DB::table('products')->where('id', $productId)->value('sku');
                    $this->upsertSupplierProduct($item, $productId, $sku, $supplierId, $syncId, $now);
                    $stats['created']++;
                    $this->line('[create] ' . $item['price_name']);
                } else {
                    $prevPrice = (float) ($product->price ?? 0);
                    $this->updateProductPrice($product->id, $item['price_byn'], $now);
                    $this->upsertSupplierProduct($item, $product->id, (string) $product->sku, $supplierId, $syncId, $now);

                    if (abs($prevPrice - $item['price_byn']) > 0.01) {
                        $stats['update_price']++;
                        $this->line(sprintf(
                            '[price] %s  %.2f → %.2f BYN',
                            mb_substr($item['price_name'], 0, 40),
                            $prevPrice,
                            $item['price_byn']
                        ));
                    } else {
                        $stats['no_change']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  error [' . $item['supplier_article'] . ']: ' . $e->getMessage());
            }
        }

        $this->showArchiveCandidates($items, $supplierId);

        $this->table(
            ['action', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats))
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Dry-run ──────────────────────────────────────────────────────────────────

    private function dryRun(array $items): int
    {
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        $rows       = [];

        foreach ($items as $item) {
            if (($item['action'] ?? null) === 'skipped_duplicate') {
                $rows[] = ['skipped_duplicate', '—', '—', mb_substr($item['price_name'], 0, 52)];
                continue;
            }

            if (($item['price_byn'] ?? null) === null || $item['price_byn'] <= 0) {
                $rows[] = ['skipped_no_price', number_format($item['price_byn'] ?? 0, 2), '—', mb_substr($item['price_name'], 0, 52)];
                continue;
            }

            $product = $this->findProduct($item, $supplierId);

            if (! $product) {
                $action = 'create';
                $dbSku  = '—';
            } else {
                $prev   = (float) ($product->price ?? 0);
                $dbSku  = $product->sku;
                $action = abs($prev - $item['price_byn']) > 0.01
                    ? sprintf('update_price  %.2f→%.2f', $prev, $item['price_byn'])
                    : 'no_change';
            }

            $rows[] = [$action, number_format($item['price_byn'], 2), $dbSku, mb_substr($item['price_name'], 0, 52)];
        }

        $this->table(['action', 'price_byn', 'db_sku', 'price_name'], $rows);

        $this->showArchiveCandidates($items, $supplierId);

        $this->line('Run with --apply to update the database.');

        return self::SUCCESS;
    }

    // ── Excel parsing ─────────────────────────────────────────────────────────────

    private function parsePriceFile(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);

        $items      = [];
        $currentCat = null;
        $seen       = [];

        foreach ($rows as $row) {
            $num  = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            // Detect category header: the keyword is in col[0]+col[1] combined, spaces normalised
            $combined = preg_replace('/\s+/', ' ', mb_strtoupper("{$num} {$name}"));
            foreach (self::CATEGORY_KEYWORDS as $keyword => $catId) {
                if (str_contains($combined, $keyword)) {
                    $currentCat = $catId;
                    break;
                }
            }

            if (! is_numeric($num) || $name === '') {
                continue;
            }

            $raw      = $row[3] ?? null;
            $rawStr   = str_replace(',', '', (string) $raw); // remove thousands separator
            $priceByn = is_numeric($rawStr) ? round((float) $rawStr, 2) : null;
            $article  = $this->supplierArticleFromName($name);

            if (isset($seen[$article])) {
                $items[] = [
                    'price_name'       => $name,
                    'supplier_article' => $article,
                    'price_byn'        => $priceByn,
                    'cat_id'           => $currentCat,
                    'action'           => 'skipped_duplicate',
                ];
                continue;
            }

            $seen[$article] = true;
            $items[]        = [
                'price_name'       => $name,
                'supplier_article' => $article,
                'price_byn'        => $priceByn,
                'cat_id'           => $currentCat,
            ];
        }

        return $items;
    }

    private function supplierArticleFromName(string $name): string
    {
        // Prefer model name in guillemets/quotes as the stable identifier across price file revisions
        if (preg_match('/[«"](.*?)[»"]/u', $name, $m)) {
            return $this->normalizeSupplierArticle($m[1]);
        }

        return $this->normalizeSupplierArticle($name);
    }

    // ── Matching ──────────────────────────────────────────────────────────────────

    private function findProduct(array $item, int $supplierId): ?object
    {
        // 1. Already tracked via supplier_products (works on every run after first --apply)
        if ($supplierId > 0) {
            $sp = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('supplier_article', $item['supplier_article'])
                ->whereNotNull('product_id')
                ->first();

            if ($sp) {
                return DB::table('products')->where('id', $sp->product_id)->first();
            }
        }

        // 2. Manual override for the ~2 cases where name normalization fails
        if (isset(self::MANUAL_MATCH[$item['supplier_article']])) {
            return DB::table('products')
                ->where('sku', self::MANUAL_MATCH[$item['supplier_article']])
                ->first();
        }

        // 3. Normalized name scan against active MetaBel products
        $pNorm = $this->normalizePriceName($item['price_name']);

        if ($pNorm === '') {
            return null;
        }

        $candidates = DB::table('products')
            ->where('brand_id', self::BRAND_ID)
            ->where('is_archived', false)
            ->get(['id', 'sku', 'name', 'price']);

        foreach ($candidates as $candidate) {
            if ($this->normalizeDbName($candidate->name) === $pNorm) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizePriceName(string $name): string
    {
        $name = mb_strtoupper($name);

        // "(в модификации Аврора С2)" → keep only the model part
        if (preg_match('/\(В\s+МОДИФИКАЦИИ\s+([^)]+)\)/u', $name, $m)) {
            $name = $m[1];
        } elseif (preg_match('/[«"](.*?)[»"]/u', $name, $m)) {
            // Guillemets/quotes → model name
            $name = $m[1];
        } else {
            // Accessories and ПБМ items: strip type prefix, keep core identifier
            $name = preg_replace('/\b(АОТК?В?|ТКТ)\s*[\d.,]+[-\d.,]*/u', '', $name);
            $name = preg_replace('/\b(ПЕЧЬ-КАМИН|ПЕЧЬ|ТОПКА|КАМИННАЯ|БАННАЯ|КАМЕНКА)\b/u', '', $name);
            // Unwrap parens: keep content (preserves "без стекла", "с вермикулитом" etc.)
            $name = preg_replace('/\(([^)]+)\)/u', ' $1 ', $name);
        }

        $name = preg_replace('/[^А-ЯЁA-Z0-9+ ]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    private function normalizeDbName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\bМЕТА[-\s]*БЕЛ\b/u', '', $name);
        $name = preg_replace('/\b(ПЕЧЬ-КАМИН|ПЕЧЬ|ТОПКА|КАМИННАЯ|КАМИННЫЙ|БАННАЯ|КАМЕНКА|ДРОВЯНАЯ|ОТОПИТЕЛЬНАЯ)\b/u', '', $name);
        // Unwrap "(в модификации X)" → keep X
        $name = preg_replace('/\(В\s+МОДИФИКАЦИИ\s+([^)]+)\)/iu', ' $1 ', $name);
        // Remove remaining parenthetical code suffixes: (АОТ-7,0), (туннельная), (без пьедестала)
        $name = preg_replace('/\([^)]+\)/u', '', $name);
        // Remove standalone АОТ/ТКТ/АОТК codes not in parens
        $name = preg_replace('/\b(АОТК?В?|ТКТ)\s*[-–]?\s*\d[\d.,\-]*/u', '', $name);
        // Remove "N кВт" inline power specs
        $name = preg_replace('/\b\d+\s*КВТ\b/iu', '', $name);

        $name = preg_replace('/[^А-ЯЁA-Z0-9+ ]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    // ── Persistence ───────────────────────────────────────────────────────────────

    private function createProduct(array $item, $now): int
    {
        $name = $this->buildProductName($item);

        return (int) DB::table('products')->insertGetId([
            'category_id'       => $item['cat_id'] ?? 287,
            'brand_id'          => self::BRAND_ID,
            'supplier_id'       => null,
            'name'              => $name,
            'h1'                => $name,
            'sku'               => $this->nextKotlovSku(),
            'slug'              => $this->uniqueSlug($name),
            'price'             => $item['price_byn'],
            'price_old'         => null,
            'currency'          => 'BYN',
            'content'           => null,
            'short_description' => null,
            'images'            => json_encode([]),
            'specs'             => json_encode([]),
            'unit'              => 'шт',
            'warranty'          => null,
            'is_active'         => true,
            'is_archived'       => false,
            'in_stock'          => true,
            'stock_qty'         => null,
            'is_featured'       => false,
            'is_new'            => true,
            'is_sale'           => false,
            'sort_order'        => 0,
            'meta_title'        => $name . ' купить в Минске',
            'meta_keywords'     => 'Мета-Бел, ' . $name,
            'meta_description'  => $name . ' — купить по лучшей цене.',
            'rating'            => 0,
            'reviews_count'     => 0,
            'views_count'       => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    private function updateProductPrice(int $productId, float $priceByn, $now): void
    {
        DB::table('products')->where('id', $productId)->update([
            'price'      => $priceByn,
            'updated_at' => $now,
        ]);
    }

    private function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $item['supplier_article']],
            [
                'supplier_article_normalized' => $item['supplier_article'],
                'supplier_sync_id'            => $syncId,
                'product_id'                  => $productId,
                'product_sku'                 => $productSku,
                'supplier_name'               => $item['price_name'],
                'source_url'                  => self::SOURCE_URL,
                'source_wp_id'                => null,
                'price'                       => $item['price_byn'],
                'currency'                    => 'BYN',
                'currency_rate'               => 1.0,
                'price_byn'                   => $item['price_byn'],
                'in_stock'                    => true,
                'match_status'                => 'matched',
                'match_confidence'            => 'auto_name',
                'raw'                         => json_encode(['cat_id' => $item['cat_id']], JSON_UNESCAPED_UNICODE),
                'last_synced_at'              => $now,
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ]
        );
    }

    private function buildProductName(array $item): string
    {
        $model  = $this->extractModelFromPriceName($item['price_name']);
        $prefix = match ($item['cat_id']) {
            69      => 'Печь банная',
            61      => 'Печь-камин',
            90      => 'Топка каминная',
            default => '',
        };

        if ($prefix && $model !== $item['price_name']) {
            return trim("{$prefix} Мета-Бел {$model}");
        }

        return preg_replace('/\s+/', ' ', trim($item['price_name']));
    }

    private function extractModelFromPriceName(string $name): string
    {
        if (preg_match('/[«"](.*?)[»"]/u', $name, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/\(в\s+модификации\s+([^)]+)\)/iu', $name, $m)) {
            return trim($m[1]);
        }

        return trim($name);
    }

    private function showArchiveCandidates(array $priceItems, int $supplierId): void
    {
        $matchedIds = [];

        foreach ($priceItems as $item) {
            if (($item['action'] ?? null) === 'skipped_duplicate') {
                continue;
            }

            $product = $this->findProduct($item, $supplierId);
            if ($product) {
                $matchedIds[] = (int) $product->id;
            }
        }

        $candidates = DB::table('products')
            ->where('brand_id', self::BRAND_ID)
            ->where('is_archived', false)
            ->whereNotIn('id', $matchedIds ?: [0])
            ->get(['sku', 'name', 'price']);

        if ($candidates->isEmpty()) {
            return;
        }

        $this->warn(sprintf("\n⚠  Кандидаты в архив (%d) — нет в прайсе, вручную:", $candidates->count()));

        $this->table(
            ['sku', 'price_byn', 'name'],
            $candidates->map(fn ($p) => [
                $p->sku,
                number_format((float) $p->price, 2),
                mb_substr($p->name, 0, 60),
            ])->all()
        );
    }

    // ── Supplier / sync registration ──────────────────────────────────────────────

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if ($existing) {
            // Never overwrite currency/currency_rate — managed via /admin/suppliers
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name'       => 'Мета-Бел',
                'contact'    => self::SOURCE_URL,
                'is_active'  => true,
                'updated_at' => $now,
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code'          => self::SUPPLIER_CODE,
            'name'          => 'Мета-Бел',
            'currency'      => 'BYN',
            'currency_rate' => 1,
            'contact'       => self::SOURCE_URL,
            'notes'         => 'Белорусский производитель печей и топок. Прайс: Excel МРЦ. Цены в BYN.',
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function ensureSupplierSync($now): ?int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name'            => 'Мета-Бел',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'МЕТА-БЕЛ: обновление цен из прайса',
                'description'     => 'Обновляет цены на товары Мета-Бел по файлу МРЦ. Новые товары создаёт; не удаляет и не архивирует автоматически.',
                'command'         => 'supplier:sync-metabel',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => null,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function normalizeSupplierArticle(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = str_replace(['«', '»', '“', '”', '‘', '’', '–', '—', '−'], ['', '', '', '', '', '', '-', '-', '-'], $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    private function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;

        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'metabel-product';
        $slug = $base;
        $i    = 2;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
