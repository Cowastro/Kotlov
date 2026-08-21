<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncTmManagementCommand extends Command
{
    protected $signature = 'supplier:sync-tm-management
        {--apply : Apply changes to database}
        {--enrich : Generate SEO descriptions via configured AI provider, e.g. DeepSeek}
        {--limit= : Limit parsed rows}
        {--sheet= : Import only one sheet}
        {--download= : Use local XLSX file instead of Google Sheets export}
        {--repair-codes-only : Repair KOTLOV SKU and supplier article fields for already imported TM Management products}';

    protected $description = 'Sync TM Management Google Sheets price list.';

    private const SUPPLIER_NAME = 'ТМ Менеджмент';
    private const SUPPLIER_CODE = 'tm-management';
    private const SOURCE_URL = 'https://docs.google.com/spreadsheets/d/1yVThZ3ZbL6dBqzpxxsUCtF-8N32lQtHK/edit';
    private const CSV_URL = 'https://docs.google.com/spreadsheets/d/1yVThZ3ZbL6dBqzpxxsUCtF-8N32lQtHK/gviz/tq?tqx=out:csv&sheet=';
    private const SHEETS = [
        ' De Dietrich РЦ BYN 25.05',
        ' Shinhoo Vanjord 03.03',
        'Джилекс  03.03',
        ' SFA 13.04',
        'Watrix',
    ];

    /** @var array<string,int> */
    private array $brandIds = [];

    /** @var array<string,int> */
    private array $categoryIds = [];

    /** @var array<string,int> */
    private array $supplierArticleSeen = [];

    private ?int $nextKotlovSkuNumber = null;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $enrich = (bool) $this->option('enrich');
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;

        $this->info($apply ? 'APPLY: database will be updated.' : 'DRY RUN: no database writes.');

        if ((bool) $this->option('repair-codes-only')) {
            $this->repairCodes($apply);

            return self::SUCCESS;
        }

        $ai = null;
        if ($enrich) {
            $ai = app(AiContentEnricher::class);
            if (! $ai->isAvailable()) {
                $this->warn('--enrich: AI provider is not configured; SEO will use fallback template.');
                $ai = null;
            } else {
                $this->info('--enrich provider: ' . $ai->providerName());
            }
        }

        $items = $this->parseWorkbook($this->option('download') ?: null, $this->option('sheet') ?: null, $limit);

        if (! $items) {
            $this->warn('No products parsed.');
            return self::FAILURE;
        }

        $this->info('Parsed products: ' . count($items));
        $this->printSummary($items);

        if (! $apply) {
            $this->table(
                ['sheet', 'brand', 'article', 'category', 'name', 'opt', 'retail', 'match'],
                array_map(fn ($item) => [
                    $item['sheet'],
                    $item['brand'],
                    $item['article'],
                    $item['category_name'],
                    Str::limit($item['name'], 58),
                    $item['price_opt'] ?? '—',
                    $item['price_retail'] ?? '—',
                    $this->findProduct($item) ? 'update' : 'create',
                ], array_slice($items, 0, 40))
            );

            $this->warn('Run with --apply to update/create products.');
            return self::SUCCESS;
        }

        $supplierId = $this->ensureSupplier();
        $syncId = $this->startSync($supplierId, count($items));

        $stats = [
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'brands_created' => 0,
            'errors' => 0,
        ];

        foreach ($items as $idx => $item) {
            try {
                $brandId = $this->ensureBrand($item['brand'], $stats);
                $categoryId = $this->categoryId($item);
                $product = $this->findProduct($item);
                $productId = $this->upsertProduct($item, $product, $brandId, $categoryId, $ai);
                $this->upsertSupplierProduct($item, $supplierId, $syncId, $productId);

                $stats[$product ? 'updated' : 'created']++;
                $stats['linked']++;

                if (($idx + 1) % 50 === 0) {
                    $this->line('Processed ' . ($idx + 1) . '/' . count($items));
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('Error: ' . ($item['article'] ?? '—') . ' ' . $e->getMessage());
            }
        }

        DB::table('supplier_syncs')->where('id', $syncId)->update([
            'last_run_at' => now(),
            'last_status' => $stats['errors'] > 0 ? 'completed_with_errors' : 'completed',
            'last_exit_code' => $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS,
            'description' => 'Google Sheets прайс ТМ Менеджмент' . ($enrich ? ' + AI SEO' : '') . ': ' . json_encode($stats, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        $this->table(['action', 'count'], array_map(
            fn ($key, $value) => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int,array<string,mixed>> */
    private function parseWorkbook(?string $localCsvDirectory, ?string $onlySheet, ?int $limit): array
    {
        $items = [];

        foreach (self::SHEETS as $sheetName) {
            if ($onlySheet && ! str_contains(mb_strtolower($sheetName), mb_strtolower($onlySheet))) {
                continue;
            }

            $brand = $this->brandFromSheet($sheetName);
            $currentSection = null;
            $header = [];
            $rows = $this->csvRows($sheetName, $localCsvDirectory);

            foreach ($rows as $row => $rawCells) {
                $cells = [];
                foreach ($rawCells as $idx => $value) {
                    $cells[$idx + 1] = is_string($value) ? trim($value) : $value;
                }
                $line = $this->rowText($cells);
                if ($line === '') {
                    continue;
                }

                if (str_contains(mb_strtolower($line), 'артикул') && str_contains(mb_strtolower($line), 'наименование')) {
                    $header = $this->mapHeader($cells);
                    continue;
                }

                if ($brand === 'Джилекс') {
                    $header = ['article' => 2, 'name' => 3, 'retail' => 4, 'opt' => 5, 'note' => 6];
                } elseif ($brand === 'SFA') {
                    $header = ['article' => 4, 'name' => 4, 'note' => 5, 'spec_note' => 6, 'opt' => 8, 'retail' => 9];
                }

                $article = $this->cleanArticle((string) ($cells[$header['article'] ?? 1] ?? ''));
                $name = trim((string) ($cells[$header['name'] ?? 2] ?? ''));

                if ($this->isSectionRow($cells, $article, $name)) {
                    $currentSection = $line;
                    continue;
                }

                if ($article === '' || $name === '' || ($brand !== 'SFA' && ! $this->looksLikeProductArticle($article))) {
                    continue;
                }

                $retail = $this->number($cells[$header['retail'] ?? 0] ?? null);
                $opt = $this->number($cells[$header['opt'] ?? 0] ?? null);

                if ($retail === null && $opt === null) {
                    continue;
                }

                $item = [
                    'sheet' => $sheetName,
                    'brand' => $brand,
                    'section' => $currentSection,
                    'article' => $article,
                    'name' => $this->cleanName($name),
                    'note' => $this->joinedNote($cells, $header),
                    'price_opt' => $opt,
                    'price_retail' => $retail ?? $opt,
                    'category_id' => null,
                    'category_name' => '',
                    'source_url' => 'google-sheet:' . $sheetName . '#row=' . ($row + 1),
                ];
                $item['category_id'] = $this->categoryId($item);
                $item['category_name'] = DB::table('categories')->where('id', $item['category_id'])->value('name') ?? 'Каталог';
                $items[] = $item;

                if ($limit && count($items) >= $limit) {
                    return $items;
                }
            }
        }

        return $items;
    }

    /** @return array<int,array<int,string>> */
    private function csvRows(string $sheetName, ?string $localCsvDirectory): array
    {
        if ($localCsvDirectory) {
            $path = rtrim($localCsvDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($sheetName) . '.csv';
            $csv = @file_get_contents($path);
        } else {
            $csv = @file_get_contents(self::CSV_URL . urlencode($sheetName));
        }

        if ($csv === false || trim($csv) === '') {
            throw new \RuntimeException('Could not fetch CSV sheet: ' . $sheetName);
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @param array<int,mixed> $cells */
    private function mapHeader(array $cells): array
    {
        $map = [];
        foreach ($cells as $col => $value) {
            $text = mb_strtolower(trim((string) $value));
            if ($text === '') {
                continue;
            }
            if (str_contains($text, 'артикул')) {
                $map['article'] = $col;
            } elseif (str_contains($text, 'наименование')) {
                $map['name'] = $col;
            } elseif (str_contains($text, 'примеч')) {
                $map['note'] = $col;
            } elseif (str_contains($text, 'опт')) {
                $map['opt'] = $col;
            } elseif (str_contains($text, 'ррц') || str_contains($text, 'рознич')) {
                $map['retail'] = $col;
            }
        }

        return $map;
    }

    private function brandFromSheet(string $sheetName): string
    {
        $lower = mb_strtolower($sheetName);
        return match (true) {
            str_contains($lower, 'dietrich') => 'De Dietrich',
            str_contains($lower, 'shinhoo') => 'Shinhoo',
            str_contains($lower, 'джилекс') => 'Джилекс',
            str_contains($lower, 'sfa') => 'SFA',
            str_contains($lower, 'watrix') => 'Watrix',
            default => self::SUPPLIER_NAME,
        };
    }

    /** @param array<int,mixed> $cells */
    private function isSectionRow(array $cells, string $article, string $name): bool
    {
        $filled = array_values(array_filter($cells, fn ($v) => trim((string) $v) !== ''));
        if ($article !== '' && $this->looksLikeProductArticle($article)) {
            return false;
        }
        if (count($filled) <= 2 && $name !== '') {
            return true;
        }

        $text = mb_strtoupper($this->rowText($cells));
        return count($filled) <= 3 && preg_match('/^[А-ЯЁA-Z0-9\\s\\-–.,()]+$/u', $text);
    }

    private function looksLikeProductArticle(string $article): bool
    {
        if (preg_match('/^\d+(?:\\.0)?$/', $article)) {
            return true;
        }

        return (bool) preg_match('/^[A-ZА-ЯЁ0-9][A-ZА-ЯЁ0-9\\-_.\\/]{2,}$/u', mb_strtoupper($article));
    }

    private function categoryId(array $item): int
    {
        $text = mb_strtolower(($item['sheet'] ?? '') . ' ' . ($item['section'] ?? '') . ' ' . ($item['name'] ?? ''));

        return match (true) {
            str_contains($text, 'дренажник') || str_contains($text, 'дренажные насосы') => 265,
            str_contains($text, 'фекальник') || str_contains($text, 'фекальные') => 327,
            str_contains($text, 'водомет') || str_contains($text, 'винтовик') || str_contains($text, 'скваж') => 264,
            str_contains($text, 'джамбо') || str_contains($text, 'краб') || str_contains($text, 'aquamaster') => 251,
            str_contains($text, 'вихревик') || str_contains($text, 'поверхност') => 249,
            str_contains($text, 'циркуль') => 248,
            str_contains($text, 'гидроаккум') || str_contains($text, 'мембрана') || str_contains($text, 'расширитель') || str_contains($text, 'бак') => 89,
            str_contains($text, 'труба pe') || str_contains($text, 'оголовок') => 193,
            str_contains($text, 'коаксиаль') || str_contains($text, 'дымоход') => 57,
            str_contains($text, 'газов') && str_contains($text, 'котел') => 53,
            str_contains($text, 'теплов') && str_contains($text, 'насос') => 286,
            str_contains($text, 'канализац') || str_contains($text, 'sanif') || str_contains($text, 'citilift') => 328,
            str_contains($text, 'дренаж') => 265,
            str_contains($text, 'скваж') => 264,
            str_contains($text, 'насосн') && str_contains($text, 'групп') => 196,
            str_contains($text, 'циркуляц')
                || str_contains($text, 'instant')
                || preg_match('/\b(master|basic|mega|promo)\b/u', $text) => 248,
            str_contains($text, 'станция') || str_contains($text, 'aquamaster') => 251,
            str_contains($text, 'солнеч') => 94,
            str_contains($text, 'автомат') || str_contains($text, 'датчик') || str_contains($text, 'регулятор') || str_contains($text, 'термостат') => 58,
            str_contains($text, 'коллектор') || str_contains($text, 'гидрострел') || str_contains($text, 'сервомотор') => 196,
            default => 195,
        };
    }

    private function ensureSupplier(): int
    {
        $now = now();
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => self::SUPPLIER_NAME,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'notes' => 'Google Sheets: https://docs.google.com/spreadsheets/d/1yVThZ3ZbL6dBqzpxxsUCtF-8N32lQtHK/edit',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureBrand(string $name, array &$stats): int
    {
        $key = mb_strtolower($name);
        if (isset($this->brandIds[$key])) {
            return $this->brandIds[$key];
        }

        $brand = DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->orderBy('id')->first();
        if ($brand) {
            return $this->brandIds[$key] = (int) $brand->id;
        }

        $now = now();
        $id = (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $this->uniqueBrandSlug($name),
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $stats['brands_created']++;

        return $this->brandIds[$key] = $id;
    }

    private function findProduct(array $item): ?object
    {
        $article = $this->supplierKey($item);
        $nameNorm = $this->normalizeName($item['name']);

        $linked = DB::table('supplier_products')
            ->where('supplier_article_normalized', $article)
            ->whereNotNull('product_id')
            ->first();
        if ($linked) {
            return DB::table('products')->where('id', $linked->product_id)->first();
        }

        $brandId = DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($item['brand'])])->orderBy('id')->value('id');
        if ($brandId) {
            $bySku = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('sku', $item['article'])
                ->first();
            if ($bySku) {
                return $bySku;
            }

            $candidates = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('is_archived', false)
                ->get(['id', 'name', 'slug']);
            foreach ($candidates as $candidate) {
                if ($this->normalizeName($candidate->name) === $nameNorm) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function upsertProduct(array $item, ?object $product, int $brandId, int $categoryId, ?AiContentEnricher $ai = null): int
    {
        $now = now();
        $price = $item['price_retail'] ?? $item['price_opt'];
        $seo = $ai ? $this->aiSeo($item, $ai) : null;
        $payload = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $item['name'],
            'h1' => $item['name'],
            'price' => $price,
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $seo['content'] ?? $this->content($item),
            'short_description' => $seo['short_description'] ?? $this->shortDescription($item),
            'specs' => json_encode($this->specs($item), JSON_UNESCAPED_UNICODE),
            'unit' => 'шт',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => 'check',
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => false,
            'is_sale' => false,
            'meta_title' => $item['name'] . ' купить в %city%',
            'meta_keywords' => $item['brand'] . ', ' . $item['name'] . ', купить в %city%',
            'meta_description' => $item['name'] . ' — цена, характеристики, консультация и поставка в %city%.',
            'updated_at' => $now,
        ];

        if ($product) {
            $currentSku = (string) ($product->sku ?? '');
            if ($currentSku === '' || str_contains($currentSku, '|') || mb_strlen($currentSku) > 90) {
                $payload['sku'] = $this->publicSku($item);
            }
            if (! empty($product->images ?? null)) {
                unset($payload['images']);
            }
            DB::table('products')->where('id', $product->id)->update($payload);
            return (int) $product->id;
        }

        $payload['sku'] = $this->publicSku($item);
        $payload['slug'] = $this->uniqueProductSlug($item['name']);
        $payload['images'] = json_encode([], JSON_UNESCAPED_UNICODE);
        $payload['created_at'] = $now;

        return (int) DB::table('products')->insertGetId($payload);
    }

    private function upsertSupplierProduct(array $item, int $supplierId, int $syncId, int $productId): void
    {
        $now = now();
        $publicSupplierArticle = $this->publicSupplierArticle($item);

        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'supplier_article_normalized' => $this->supplierKey($item),
            ],
            [
                'supplier_sync_id' => $syncId,
                'product_id' => $productId,
                'product_sku' => DB::table('products')->where('id', $productId)->value('sku'),
                'supplier_article' => $publicSupplierArticle,
                'supplier_article_compact' => preg_replace('/[^A-Z0-9А-ЯЁ]+/u', '', mb_strtoupper($this->normalizeArticle($publicSupplierArticle))),
                'supplier_name' => $item['name'],
                'source_url' => $item['source_url'],
                'price' => $item['price_opt'] ?? $item['price_retail'],
                'currency' => 'BYN',
                'currency_rate' => 1,
                'price_byn' => $item['price_opt'] ?? $item['price_retail'],
                'in_stock' => false,
                'stock_quantity' => null,
                'stock_status' => 'preorder',
                'stock_text' => 'Уточняйте наличие',
                'warehouse_name' => self::SUPPLIER_NAME,
                'delivery_days' => null,
                'last_stock_synced_at' => $now,
                'match_status' => 'matched',
                'match_confidence' => 1,
                'raw' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function startSync(int $supplierId, int $total): int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SUPPLIER_CODE],
            [
                'name' => self::SUPPLIER_NAME,
                'code' => self::SUPPLIER_CODE,
                'title' => 'ТМ Менеджмент',
                'description' => 'Google Sheets прайс ТМ Менеджмент. Ожидается позиций: ' . $total,
                'command' => 'supplier:sync-tm-management',
                'source_url' => self::SOURCE_URL,
                'is_active' => true,
                'last_run_at' => now(),
                'last_status' => 'running',
                'last_exit_code' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', self::SUPPLIER_CODE)->value('id');
    }

    private function content(array $item): string
    {
        $name = e($item['name']);
        $brand = e($item['brand']);
        $category = e($item['category_name']);
        $note = trim((string) ($item['note'] ?? ''));
        $noteHtml = $note !== ''
            ? '<h3>Описание и особенности</h3><p>' . nl2br(e(Str::limit($note, 1200, ''))) . '</p>'
            : '';

        return <<<HTML
<p><strong>{$name}</strong> — товар бренда {$brand} в категории «{$category}». Его можно заказать через KOTLOV.BY в %city% с консультацией по подбору, совместимости и комплектации.</p>

<p>Перед покупкой рекомендуем уточнить актуальное наличие, характеристики и применимость к вашей системе отопления, водоснабжения, канализации или монтажному узлу. Это помогает правильно подобрать товар, не ошибиться с размерами, подключением и дополнительными комплектующими.</p>

{$noteHtml}

<h3>Что уточнить перед покупкой</h3>
<ul>
    <li>совместимость с вашей системой и уже установленным оборудованием;</li>
    <li>актуальную цену, наличие и срок поставки;</li>
    <li>нужны ли переходники, автоматика, дымоход, насосная группа или другие комплектующие;</li>
    <li>условия монтажа и обслуживания в %city%.</li>
</ul>
HTML;
    }

    private function shortDescription(array $item): string
    {
        return trim($item['brand']) . ' — товар для систем отопления, водоснабжения или монтажа. Уточняйте наличие, комплектацию и срок поставки по Беларуси.';
    }

    private function aiSeo(array $item, AiContentEnricher $ai): ?array
    {
        $seo = $ai->generateSeo(
            $item['name'],
            $item['brand'],
            $item['category_name'],
            $this->specs($item),
            [
                'supplier' => self::SUPPLIER_NAME,
                'price_list_note' => $item['note'] ?? '',
                'section' => $item['section'] ?? '',
            ]
        );

        if (! $seo || trim((string) ($seo['content'] ?? '')) === '' || trim((string) ($seo['short_description'] ?? '')) === '') {
            $this->warn('AI SEO skipped: ' . $item['article'] . ' ' . Str::limit($item['name'], 60));
            return null;
        }

        if (! str_contains($seo['content'], '%city%')) {
            $seo['content'] .= '<p>На KOTLOV.BY можно подобрать эту позицию и совместимые комплектующие в %city%, чтобы заказ соответствовал вашей системе и условиям монтажа.</p>';
        }

        $seo['short_description'] = str_replace('%city%', 'Беларуси', (string) $seo['short_description']);

        return $seo;
    }

    private function specs(array $item): array
    {
        return array_filter([
            'Бренд' => $item['brand'] ?? null,
            'Артикул поставщика' => $item['article'] ?? null,
            'Раздел прайса' => $item['section'] ?? null,
            'Поставщик' => self::SUPPLIER_NAME,
            'Цена ОПТ, BYN' => $item['price_opt'] ?? null,
            'Цена РРЦ, BYN' => $item['price_retail'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** @param array<int,mixed> $cells */
    private function rowText(array $cells): string
    {
        return trim(implode(' ', array_filter(array_map(fn ($value) => trim((string) $value), $cells))));
    }

    private function cleanArticle(string $article): string
    {
        $article = $this->cleanOneLine($article);
        if (preg_match('/^\d+\\.0$/', $article)) {
            $article = (string) (int) $article;
        }

        $article = $this->cleanOneLine(explode('|', $article, 2)[0]);
        $compact = mb_strtolower(preg_replace('/\s+/u', '', $article) ?? $article);

        if (str_contains($compact, 'vannedn100') && str_contains($compact, 'vannedn110')) {
            return 'Vanne DN 100/110';
        }

        if (str_contains($compact, 'vannedn50')) {
            return 'Vanne DN 50';
        }

        if (preg_match('/^(sanibox|sanidouche|sanipack|sanitop|sanivite)\(/i', $article, $match)) {
            return mb_strtoupper($match[1]);
        }

        if (str_contains($compact, 'sanifloor1')) {
            return 'SANIFLOOR 1';
        }

        if (str_contains($compact, 'sanifloor2')) {
            return 'SANIFLOOR 2';
        }

        if (str_contains($compact, 'sanicubic2classic')) {
            return 'Sanicubic 2 Classic';
        }

        if (str_contains($compact, 'sanicubic2xlvxtrismart')) {
            return 'SANICUBIC 2 XL VX TRI SMART';
        }

        if (str_contains($compact, 'sanicubic2xlvx')) {
            return 'SANICUBIC 2 XL VX';
        }

        if (str_contains($compact, 'sanicubic1wp')) {
            return 'SANICUBIC 1 WP';
        }

        if (str_contains($compact, 'sanicubic2pro')) {
            return 'Sanicubic 2 Pro';
        }

        if (str_contains($compact, 'sanialarm')) {
            return 'SANIALARM';
        }

        if (str_contains($compact, 'sanidoucheflat')) {
            return 'SANIDOUCHE Flat';
        }

        return $article;
    }

    private function cleanName(string $name): string
    {
        $name = str_replace(["\r", "\n"], ' ', $name);
        $name = str_ireplace('пооским', 'плоским', $name);
        $name = preg_replace(
            '/^Шиберная задвижка Vanne DN 100\/\s*Vanne DN 110\b.*$/u',
            'Шиберная задвижка Vanne DN 100/110 для SFA Sanicubic',
            $name
        ) ?? $name;
        $name = preg_replace(
            '/^Шиберная задвижка Vanne DN 50\b.*$/u',
            'Шиберная задвижка Vanne DN 50 для SFA Sanicubic',
            $name
        ) ?? $name;
        $name = preg_replace(
            '/^Современная насосная станция Sanicubic 2 Classic$/u',
            'Sanicubic 2 Classic',
            $name
        ) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /** @param array<int,mixed> $cells */
    private function joinedNote(array $cells, array $header): string
    {
        $parts = [];
        foreach (['note', 'spec_note'] as $key) {
            $value = trim((string) ($cells[$header[$key] ?? 0] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode("\n", $parts);
    }

    private function number(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $text = str_replace(["\xc2\xa0", ' '], '', $text);
        $text = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $text) ?? '');
        return is_numeric($text) ? round((float) $text, 2) : null;
    }

    private function normalizeArticle(string $article): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', '', $article) ?? $article));
    }

    private function supplierKey(array $item): string
    {
        $raw = $this->normalizeArticle(($item['article'] ?? '') . '|' . ($item['name'] ?? ''));
        if (mb_strlen($raw) <= 160) {
            return $raw;
        }

        return mb_substr($raw, 0, 120) . '-' . substr(md5($raw), 0, 12);
    }

    private function publicSku(array $item): string
    {
        return $this->nextKotlovSku();
    }

    private function publicSupplierArticle(array $item): string
    {
        $article = mb_substr($this->cleanArticle($this->cleanOneLine((string) ($item['article'] ?? ''))), 0, 100);
        if ($article === '') {
            $article = mb_substr($this->cleanOneLine((string) ($item['name'] ?? '')), 0, 100);
        }

        $normalized = $this->normalizeArticle($article);
        $seenKey = self::SUPPLIER_CODE . '|' . $normalized;

        if (! isset($this->supplierArticleSeen[$seenKey])) {
            $this->supplierArticleSeen[$seenKey] = 1;
            return $article;
        }

        $this->supplierArticleSeen[$seenKey]++;
        $suffix = '-' . substr(md5($this->supplierKey($item)), 0, 8);

        return mb_substr($article, 0, 100 - mb_strlen($suffix)) . $suffix;
    }

    private function nextKotlovSku(): string
    {
        if ($this->nextKotlovSkuNumber === null) {
            $max = DB::table('products')
                ->where('sku', 'like', 'KOTLOV-%')
                ->selectRaw("MAX(CAST(SUBSTRING(sku, 8) AS UNSIGNED)) as max_number")
                ->value('max_number');

            $this->nextKotlovSkuNumber = max((int) $max, 6629) + 1;
        }

        return sprintf('KOTLOV-%06d', $this->nextKotlovSkuNumber++);
    }

    private function repairCodes(bool $apply): void
    {
        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->warn('TM Management supplier not found.');
            return;
        }

        $rows = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplierId)
            ->whereNotNull('sp.product_id')
            ->orderBy('p.id')
            ->get([
                'sp.id as supplier_product_id',
                'sp.supplier_article',
                'sp.supplier_name',
                'sp.raw',
                'p.id as product_id',
                'p.sku',
                'p.name',
                'p.h1',
                'p.slug',
                'p.content',
                'p.short_description',
            ]);

        $changed = 0;
        $examples = [];
        $articleGroups = [];

        foreach ($rows as $row) {
            $raw = json_decode((string) $row->raw, true);
            $baseArticle = is_array($raw) ? $this->cleanArticle((string) ($raw['article'] ?? '')) : '';
            if ($baseArticle === '') {
                $baseArticle = $this->cleanArticle((string) $row->supplier_article);
                $baseArticle = preg_replace('/-[0-9a-f]{6,8}$/i', '', $baseArticle) ?? $baseArticle;
            }

            $articleKey = $this->normalizeArticle($baseArticle);
            $row->__tm_base_article = $baseArticle;
            $row->__tm_article_key = $articleKey;

            if ($articleKey !== '') {
                $articleGroups[$articleKey][] = [
                    'product_id' => (int) $row->product_id,
                    'supplier_product_id' => (int) $row->supplier_product_id,
                ];
            }
        }

        $canonicalSupplierProductIds = [];
        foreach ($articleGroups as $articleKey => $group) {
            usort($group, fn (array $a, array $b) => [$a['product_id'], $a['supplier_product_id']] <=> [$b['product_id'], $b['supplier_product_id']]);
            $canonicalSupplierProductIds[$articleKey] = $group[0]['supplier_product_id'];
        }

        foreach ($rows as $row) {
            $supplierArticle = (string) ($row->__tm_base_article ?? '');
            $articleKey = (string) ($row->__tm_article_key ?? '');

            if (
                count($articleGroups[$articleKey] ?? []) <= 1
                &&
                $supplierArticle !== (string) $row->supplier_article
                && DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('id', '!=', $row->supplier_product_id)
                    ->where('supplier_article', $supplierArticle)
                    ->exists()
            ) {
                $suffix = '-' . substr(md5((string) $row->supplier_product_id), 0, 6);
                $supplierArticle = mb_substr($supplierArticle, 0, 100 - mb_strlen($suffix)) . $suffix;
            }

            if (
                $articleKey !== ''
                && count($articleGroups[$articleKey] ?? []) > 1
                && ($canonicalSupplierProductIds[$articleKey] ?? null) !== (int) $row->supplier_product_id
            ) {
                $suffix = '-' . substr(md5((string) $row->supplier_product_id), 0, 6);
                $base = (string) ($row->__tm_base_article ?? $supplierArticle);
                $supplierArticle = mb_substr($base, 0, 100 - mb_strlen($suffix)) . $suffix;
            }

            $newSku = (string) $row->sku;
            if (! preg_match('/^KOTLOV-\d{6}$/', $newSku)) {
                $newSku = $this->nextKotlovSku();
            }

            $newProductName = $this->cleanName((string) $row->name);
            $newProductH1 = filled($row->h1) ? $this->cleanName((string) $row->h1) : $row->h1;
            $newSupplierName = $this->cleanName((string) $row->supplier_name);
            $newContent = str_replace('пооским', 'плоским', (string) $row->content);
            $newShortDescription = str_replace(
                ['в %city%', '%city%', 'пооским'],
                ['по Беларуси', 'Беларуси', 'плоским'],
                (string) $row->short_description
            );

            $needsUpdate = $newSku !== (string) $row->sku
                || $supplierArticle !== (string) $row->supplier_article
                || str_contains((string) $row->supplier_article, '|')
                || mb_strlen((string) $row->supplier_article) > 60
                || $newProductName !== (string) $row->name
                || $newProductH1 !== (string) $row->h1
                || $newSupplierName !== (string) $row->supplier_name
                || $newContent !== (string) $row->content
                || $newShortDescription !== (string) $row->short_description;

            if (! $needsUpdate || $supplierArticle === '') {
                continue;
            }

            if (count($examples) < 12) {
                $examples[] = [
                    'product_id' => $row->product_id,
                    'sku_from' => $row->sku,
                    'sku_to' => $newSku,
                    'article_from' => Str::limit((string) $row->supplier_article, 44),
                    'article_to' => $supplierArticle,
                ];
            }

            if ($apply) {
                DB::table('products')->where('id', $row->product_id)->update([
                    'sku' => $newSku,
                    'name' => $newProductName,
                    'h1' => $newProductH1,
                    'content' => $newContent,
                    'short_description' => $newShortDescription,
                    'updated_at' => now(),
                ]);
                DB::table('supplier_products')->where('id', $row->supplier_product_id)->update([
                    'product_sku' => $newSku,
                    'supplier_article' => $supplierArticle,
                    'supplier_article_normalized' => $this->normalizeArticle($supplierArticle),
                    'supplier_article_compact' => preg_replace('/[^A-Z0-9А-ЯЁ]+/u', '', mb_strtoupper($this->normalizeArticle($supplierArticle))),
                    'supplier_name' => $newSupplierName,
                    'updated_at' => now(),
                ]);
            }

            $changed++;
        }

        $this->info(($apply ? 'Repaired' : 'Codes to repair') . ': ' . $changed);
        if ($examples !== []) {
            $this->table(['product_id', 'sku_from', 'sku_to', 'article_from', 'article_to'], $examples);
        }

        $split = $this->splitDuplicateSupplierProductLinks($supplierId, $apply);
        $this->info(($apply ? 'Split duplicate product links' : 'Duplicate product links to split') . ': ' . $split);
    }

    private function splitDuplicateSupplierProductLinks(int $supplierId, bool $apply): int
    {
        $groups = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('product_id')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_id');

        $split = 0;
        $examples = [];

        foreach ($groups as $productId) {
            $product = DB::table('products')->where('id', $productId)->first();
            if (! $product) {
                continue;
            }

            $links = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('product_id', $productId)
                ->orderBy('id')
                ->get();

            $productNameNorm = $this->normalizeName((string) $product->name);
            $keepId = optional($links->first(function ($link) use ($productNameNorm) {
                return $this->normalizeName((string) $link->supplier_name) === $productNameNorm;
            }))->id ?? optional($links->first())->id;

            foreach ($links as $link) {
                if ((int) $link->id === (int) $keepId) {
                    continue;
                }

                $supplierName = $this->cleanName((string) $link->supplier_name);
                if ($supplierName === '') {
                    continue;
                }

                $nameNorm = $this->normalizeName($supplierName);
                if ($nameNorm === '' || $nameNorm === $productNameNorm) {
                    continue;
                }

                $existingProductId = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('id', '!=', $link->id)
                    ->where('supplier_article_normalized', $link->supplier_article_normalized)
                    ->value('product_id');

                if ($existingProductId && (int) $existingProductId !== (int) $productId) {
                    if ($apply) {
                        DB::table('supplier_products')->where('id', $link->id)->update([
                            'product_id' => $existingProductId,
                            'product_sku' => DB::table('products')->where('id', $existingProductId)->value('sku'),
                            'updated_at' => now(),
                        ]);
                    }
                    $split++;
                    continue;
                }

                $newSku = $this->nextKotlovSku();
                $price = $link->price_byn ?? $product->price;
                $newProduct = [
                    'category_id' => $product->category_id,
                    'brand_id' => $product->brand_id,
                    'supplier_id' => $product->supplier_id,
                    'name' => $supplierName,
                    'slug' => $this->uniqueProductSlug($supplierName),
                    'h1' => $supplierName,
                    'sku' => $newSku,
                    'price' => $price,
                    'price_old' => null,
                    'currency' => $product->currency ?: 'BYN',
                    'content' => $this->genericTmContent($supplierName),
                    'short_description' => $this->genericTmShortDescription($supplierName, $product->brand_id),
                    'images' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'specs' => $product->specs ?: json_encode([], JSON_UNESCAPED_UNICODE),
                    'service_info' => $product->service_info,
                    'documents' => $product->documents,
                    'promo_flags' => $product->promo_flags,
                    'video_url' => null,
                    'weight' => $product->weight,
                    'unit' => $product->unit ?: 'шт',
                    'warranty' => $product->warranty,
                    'is_active' => true,
                    'is_archived' => false,
                    'in_stock' => false,
                    'availability_status' => 'check',
                    'stock_qty' => null,
                    'is_featured' => false,
                    'is_new' => false,
                    'is_sale' => false,
                    'sort_order' => $product->sort_order,
                    'meta_title' => $supplierName . ' купить в %city%',
                    'meta_keywords' => $supplierName . ', купить в %city%',
                    'meta_description' => $supplierName . ' — цена, характеристики, консультация и поставка в %city%.',
                    'rating' => 0,
                    'reviews_count' => 0,
                    'views_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($examples) < 8) {
                    $examples[] = [
                        'from_product' => $productId,
                        'supplier_product' => $link->id,
                        'new_sku' => $newSku,
                        'name' => Str::limit($supplierName, 60),
                    ];
                }

                if ($apply) {
                    $newProductId = DB::table('products')->insertGetId($newProduct);
                    DB::table('supplier_products')->where('id', $link->id)->update([
                        'product_id' => $newProductId,
                        'product_sku' => $newSku,
                        'updated_at' => now(),
                    ]);
                }

                $split++;
            }
        }

        if ($examples !== []) {
            $this->table(['from_product', 'supplier_product', 'new_sku', 'name'], $examples);
        }

        return $split;
    }

    private function genericTmContent(string $name, ?string $brand = null, ?string $category = null): string
    {
        $name = e($name);
        $brand = e($brand ?: 'товара');
        $category = e($category ?: 'каталога отопительного оборудования');

        return <<<HTML
<p><strong>{$name}</strong> — товар бренда {$brand} в разделе «{$category}». Карточка подготовлена для подбора и заказа через KOTLOV.BY в %city% с консультацией по совместимости, комплектации и сроку поставки.</p>

<p>Перед покупкой рекомендуем уточнить актуальное наличие, характеристики и применимость к вашей системе отопления, водоснабжения, канализации или монтажному узлу. Это помогает избежать ошибок по размерам, подключению и дополнительным комплектующим.</p>

<h3>Что уточнить перед заказом</h3>
<ul>
    <li>подходит ли позиция к вашему оборудованию;</li>
    <li>нужны ли переходники, крепёж, автоматика или расходные материалы;</li>
    <li>актуальную цену, комплектацию и срок поставки в %city%;</li>
    <li>условия монтажа и обслуживания.</li>
</ul>
HTML;
    }

    private function genericTmShortDescription(string $name, ?int $brandId): string
    {
        $brand = $brandId ? DB::table('brands')->where('id', $brandId)->value('name') : null;
        $brand = trim((string) $brand);

        if ($brand !== '') {
            return $brand . ' — товар для систем отопления, водоснабжения или монтажа. Уточняйте наличие, комплектацию и срок поставки по Беларуси.';
        }

        return $name . ' — товар для систем отопления, водоснабжения или монтажа. Уточняйте наличие, комплектацию и срок поставки по Беларуси.';
    }

    private function cleanOneLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? $value);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/[^А-ЯЁA-Z0-9]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function uniqueProductSlug(string $name): string
    {
        $base = Str::slug($this->transliterate($name)) ?: 'tm-management-product';
        $slug = $base;
        $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($this->transliterate($name)) ?: 'brand';
        $slug = $base;
        $i = 2;
        while (DB::table('brands')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function transliterate(string $text): string
    {
        return strtr($text, [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E',
            'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M',
            'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ]);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function printSummary(array $items): void
    {
        $byBrand = [];
        $byCategory = [];
        foreach ($items as $item) {
            $byBrand[$item['brand']] = ($byBrand[$item['brand']] ?? 0) + 1;
            $byCategory[$item['category_name']] = ($byCategory[$item['category_name']] ?? 0) + 1;
        }
        arsort($byBrand);
        arsort($byCategory);

        $this->table(['brand', 'count'], array_map(fn ($brand, $count) => [$brand, $count], array_keys($byBrand), array_values($byBrand)));
        $this->table(['category', 'count'], array_map(fn ($category, $count) => [$category, $count], array_keys($byCategory), array_values($byCategory)));
    }
}
