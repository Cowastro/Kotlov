<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Synchronise the Лигмет (ligmet) supplier price list (stoves / fireplaces /
 * chimneys) into KOTLOV. Modeled on supplier:sync-tsk-nasosy.
 *
 * The workbook is a single flat sheet with no brand column — the brand and
 * category live in hierarchical SECTION header rows (e.g.
 * «02) ПЕЧИ/1) Печи-камины/Kratki, Польша»). We walk the rows, carry the
 * current section, derive brand + category from it, and only keep the requested
 * brands.
 *
 *   php artisan supplier:sync-ligmet --dry-run
 *   php artisan supplier:sync-ligmet --apply --create-new
 *
 * A product may be sold by several suppliers but keeps ONE catalogue SKU and ONE
 * retail price: we match to the existing product and add a per-supplier
 * supplier_products row (one row per product per supplier). Retail (products.price)
 * is set from the Лигмет «Цена розница» column.
 */
class SyncLigmetCommand extends Command
{
    protected $signature = 'supplier:sync-ligmet
        {--dry-run : Preview, write nothing}
        {--apply : Write changes}
        {--limit= : Process only the first N data rows}
        {--stock-file= : Local .xlsx path (skips download)}
        {--sheet-url= : Override the default spreadsheet URL}
        {--folder-url= : Google Drive folder URL; newest XLS/XLSX file will be used}
        {--candidate-report= : Write create_candidate rows to CSV}
        {--all-categories : Import chimneys/doors/accessories too (default: stoves/fireplaces only)}
        {--archive-existing : Archive existing active products of these brands (in scope) before import; excludes them from matching}
        {--redirects : Write an old→new redirect map (archived → recreated, by brand+model)}
        {--create-new : Create products for rows with no match}';

    protected $description = 'Sync Лигмет prices & stock (stoves/fireplaces) into supplier_products; retail from the price list.';

    private const SUPPLIER_CODE = 'ligmet';
    private const SUPPLIER_NAME = 'Лигмет';
    private const SYNC_KEY      = 'ligmet_stock';
    private const FILE_ID       = '1YA5Aq05X2h3i1bRulkzvrgwlQ8JHdBMn';
    private const FOLDER_ID     = '1pQQRGMKBEHHEjUF3AYsi_dTvlxxTFxzz';
    private const SOURCE_URL    = 'https://ligmet.by/';

    /** Fixed column layout — header row 9: B..H (0-based A=0). */
    private const COLS = [
        'article' => 1,  // B  Код
        'qty'     => 2,  // C  Количество
        'name'    => 3,  // D  Наименование
        'unit'    => 4,  // E  Ед.изм.
        'status'  => 5,  // F  Доступно
        'price'   => 6,  // G  Цена Опт (закупка)
        'retail'  => 7,  // H  Цена розница
    ];

    /**
     * Requested brands → canonical catalogue brand name. Section path is matched
     * case-insensitively against the keys; rows of other brands are skipped.
     * (LLdnord intentionally omitted — absent from this price list.)
     */
    private const BRAND_MAP = [
        'ермак'    => 'Ермак',
        'кпд'      => 'КПД',
        'blist'    => 'Blist',
        'fireway'  => 'FireWay',
        'fergus'   => 'Ferguss',   // matches «Fergus» and «Ferguss»
        'invicta'  => 'Invicta',
        'kratki'   => 'Kratki',
        'mbs'      => 'MBS',
        'nordflam' => 'Nordflam',
        'panadero' => 'Panadero',
    ];

    /** Section-path keyword → KOTLOV category_id (checked in order). */
    private const CATEGORY_MAP = [
        'топк'        => 90,  // Каминные топки
        'печи-камины' => 61,  // Печи-камины
        'печь-камин'  => 61,
        'двери'       => 287, // Печное и каминное литьё (двери)
        'литье'       => 287,
        'литьё'       => 287,
        'аксессуар'   => 291, // Дровницы и каминные принадлежности
        'комплектующ' => 291,
        'бан'         => 69,  // Дровяные печи (для бани)
        'дымоход'     => 78,  // Дымоходы
        'камин'       => 90,  // прочие КАМИНЫ → топки
        'печ'         => 61,  // прочие ПЕЧИ → печи-камины
    ];

    private const DEFAULT_CATEGORY = 61;

    /**
     * Only these categories are imported (stoves / fireplaces / inserts / sauna
     * wood stoves). Chimneys (78), cast-iron/doors (287) and accessories (291)
     * are skipped. Pass --all-categories to import everything.
     */
    private const ALLOWED_CATEGORIES = [61, 90, 69];

    /** Category + color words dropped when comparing models across catalogue/sheet. */
    private const MODEL_STOPWORDS = [
        // category prefixes
        'ПЕЧЬ', 'ПЕЧЬ-КАМИН', 'ПЕЧЬ-КАМИНЫ', 'КАМИН', 'КАМИННАЯ', 'КАМИННЫЙ', 'ТОПКА',
        'ПЕЧНОЙ', 'ДРОВЯНАЯ', 'ДРОВЯНОЙ', 'БАННАЯ', 'ОТОПИТЕЛЬНАЯ', 'ВАРОЧНАЯ',
        // material prefixes (Лигмет prepends Стальная/Чугунная to stove names)
        'СТАЛЬНАЯ', 'СТАЛЬНОЙ', 'ЧУГУННАЯ', 'ЧУГУННЫЙ',
        // colors / finish variants (Лигмет often appends color to model name)
        'СЕРАЯ', 'СЕРЫЙ', 'СЕРОЕ', 'СЕРЫЕ',
        'ЧЁРНАЯ', 'ЧЁРНЫЙ', 'ЧЁРНОЕ', 'ЧЕРНАЯ', 'ЧЕРНЫЙ', 'ЧЕРНОЕ',
        'БЕЛАЯ', 'БЕЛЫЙ', 'БЕЛОЕ',
        'БЕЖЕВАЯ', 'БЕЖЕВЫЙ',
        'КРАСНАЯ', 'КРАСНЫЙ',
        'КОРИЧНЕВАЯ', 'КОРИЧНЕВЫЙ',
        'ПАТИНА', 'АНТРАЦИТ', 'ГРАФИТ', 'КРЕМОВАЯ', 'КРЕМОВЫЙ',
    ];

    private array $indexBySupplierArticle = [];
    private array $indexByBrandModel = [];
    private array $brandById = [];
    private array $brandByName = [];
    /** product ids excluded from matching because they'll be archived */
    private array $excludeIds = [];
    /** created products for redirect mapping: [brand_id][model] => ['id'=>,'slug'=>] */
    private array $createdByBrandModel = [];

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply') && ! $this->option('dry-run');
        $createNew = (bool) $this->option('create-new');
        $limit     = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        try {
            $path = $this->resolveFile();
            $rows = $this->parseWorkbook($path);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d product rows for requested brands', count($rows)));
        if ($limit) {
            $rows = array_slice($rows, 0, $limit);
        }
        if ($rows === []) {
            $this->warn('No rows for the requested brands — check the section/brand mapping.');
            return self::SUCCESS;
        }

        // Existing products to archive (and therefore exclude from matching).
        $archived = [];
        if ($this->option('archive-existing')) {
            $archived = $this->archiveExisting($apply);
            $this->excludeIds = array_map(fn ($a) => (int) $a->id, $archived);
            $this->info(sprintf('%s %d existing product(s) of these brands (cat %s)',
                $apply ? 'Archived' : 'Would archive', count($archived), implode('/', self::ALLOWED_CATEGORIES)));
        }

        $this->buildIndex();
        $classified = array_map(fn ($r) => $this->classify($r), $rows);

        if (! $apply) {
            return $this->showDryRun($classified);
        }

        $result = $this->applyChanges($classified, $createNew);
        if ($this->option('redirects') && $archived !== []) {
            $this->buildRedirects($archived);
        }
        return $result;
    }

    // ── Archive existing brand products (reversible; CSV report) ───────────────────

    /** @return array<int,object> archived rows (id, sku, slug, name, brand_id) */
    private function archiveExisting(bool $apply): array
    {
        $brandIds = array_values(array_unique(array_filter(array_map(
            fn ($name) => $this->brandByNameId($name),
            array_values(self::BRAND_MAP)
        ))));
        if ($brandIds === []) {
            return [];
        }
        $rows = DB::table('products')
            ->whereIn('brand_id', $brandIds)
            ->whereIn('category_id', self::ALLOWED_CATEGORIES)
            ->where('is_archived', false)
            ->get(['id', 'sku', 'slug', 'name', 'brand_id', 'category_id'])
            ->all();
        if ($rows === [] || ! $apply) {
            return $rows;
        }

        $dir = storage_path('app/reports/ligmet');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $csv = $dir . '/archived-' . date('Ymd-His') . '.csv';
        $fh = fopen($csv, 'w');
        fputcsv($fh, ['id', 'sku', 'slug', 'name', 'brand_id', 'category_id']);
        foreach ($rows as $r) {
            fputcsv($fh, [$r->id, $r->sku, $r->slug, $r->name, $r->brand_id, $r->category_id]);
        }
        fclose($fh);
        $this->line("  archive report: {$csv}");

        DB::table('products')->whereIn('id', array_map(fn ($r) => (int) $r->id, $rows))
            ->update(['is_archived' => true, 'is_active' => false, 'updated_at' => now()]);

        return $rows;
    }

    private function brandByNameId(string $name): ?int
    {
        return (int) (DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id') ?? 0) ?: null;
    }

    /** Map archived products to freshly created ones by brand+model → redirect CSV. */
    private function buildRedirects(array $archived): void
    {
        $dir = storage_path('app/reports/ligmet');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $csv = $dir . '/redirects-' . date('Ymd-His') . '.csv';
        $fh = fopen($csv, 'w');
        fputcsv($fh, ['old_id', 'old_slug', 'new_id', 'new_slug', 'brand_id', 'model']);
        $mapped = 0;
        foreach ($archived as $a) {
            $bid = (int) $a->brand_id;
            $model = $this->model((string) $a->name, $this->brandById[$bid] ?? '');
            $new = $this->createdByBrandModel[$bid][$model] ?? null;
            if ($new === null) {
                continue;
            }
            fputcsv($fh, [$a->id, $a->slug, $new['id'], $new['slug'], $bid, $model]);
            $mapped++;
        }
        fclose($fh);
        $this->info("Redirects mapped: {$mapped} → {$csv}");
    }

    // ── Download / read workbook ──────────────────────────────────────────────────

    private function resolveFile(): string
    {
        $file = $this->option('stock-file');
        if ($file !== null) {
            if (! file_exists($file)) {
                throw new \RuntimeException("File not found: {$file}");
            }
            return $file;
        }

        $ctx = $this->httpContext();

        $folderUrl = $this->option('folder-url');
        if ($folderUrl !== null || $this->option('sheet-url') === null) {
            $folderId = $this->driveFolderId((string) ($folderUrl ?: '')) ?: self::FOLDER_ID;
            $url = $this->latestDriveWorkbookUrl($folderId, $ctx);
            $this->line("Using latest Ligmet workbook from Drive folder: {$url}");
        } else {
            $url = $this->option('sheet-url');
        }

        $id  = self::FILE_ID;
        if ($url !== null && preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            $id = $m[1];
        }

        foreach ([
            "https://docs.google.com/spreadsheets/d/{$id}/export?format=xlsx",
            "https://drive.google.com/uc?export=download&id={$id}",
        ] as $u) {
            $bin = @file_get_contents($u, false, $ctx);
            if ($bin !== false && strlen($bin) > 10000 && substr($bin, 0, 2) === 'PK') {
                $tmp = tempnam(sys_get_temp_dir(), 'lig') . '.xlsx';
                file_put_contents($tmp, $bin);
                return $tmp;
            }
        }
        throw new \RuntimeException('Failed to download the Лигмет workbook (.xlsx).');
    }

    private function httpContext()
    {
        return stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 180,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: */*",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
    }

    private function driveFolderId(string $url): ?string
    {
        if ($url !== '' && preg_match('#/folders/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function latestDriveWorkbookUrl(string $folderId, $ctx): string
    {
        $html = @file_get_contents("https://drive.google.com/drive/folders/{$folderId}", false, $ctx);
        if ($html === false || $html === '') {
            return "https://docs.google.com/spreadsheets/d/" . self::FILE_ID . "/edit";
        }

        $payload = $html;
        if (preg_match("/window\\['_DRIVE_ivd'\\]\\s*=\\s*'([^']+)'/s", $html, $m)) {
            $payload = stripcslashes($m[1]);
        }

        $matches = [];
        preg_match_all(
            '/\["([a-zA-Z0-9_-]{20,})",\["' . preg_quote($folderId, '/') . '"\],"([^"]+\.(?:xls|xlsx))","application\/(?:vnd\.ms-excel|vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet)"/u',
            $payload,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches === []) {
            return "https://docs.google.com/spreadsheets/d/" . self::FILE_ID . "/edit";
        }

        usort($matches, function (array $a, array $b): int {
            return strcmp($this->dateKeyFromFileName($b[2]), $this->dateKeyFromFileName($a[2]));
        });

        return "https://docs.google.com/spreadsheets/d/{$matches[0][1]}/edit?rtpof=true&sd=true";
    }

    private function dateKeyFromFileName(string $name): string
    {
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})/', $name, $m)) {
            return $m[1] . $m[2] . $m[3] . $m[4] . $m[5] . $m[6];
        }

        return $name;
    }

    /** @return array<int,array<string,mixed>> product rows with derived brand/category */
    private function parseWorkbook(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Cannot open workbook archive.');
        }
        $shared = $this->sharedStrings($zip);
        $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === '') {
            throw new \RuntimeException('sheet1.xml not found in workbook.');
        }

        $c = self::COLS;
        $reader = new \XMLReader();
        $reader->XML($sheetXml);
        $curRow = 0;
        $cells = [];
        $rowsRaw = [];
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'row') {
                if ($cells !== []) {
                    $rowsRaw[$curRow] = $cells;
                    $cells = [];
                }
                $curRow = (int) $reader->getAttribute('r');
            }
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'c') {
                $ref  = (string) $reader->getAttribute('r');
                $type = $reader->getAttribute('t');
                $xml  = $reader->readOuterXML();
                $val  = preg_match('/<v>(.*?)<\/v>/s', $xml, $vm) ? $vm[1] : '';
                if ($type === 's') {
                    $val = $shared[(int) $val] ?? '';
                }
                $col = preg_replace('/\d+/', '', $ref);
                $cells[$this->colIndex($col)] = $this->clean((string) $val);
            }
        }
        if ($cells !== []) {
            $rowsRaw[$curRow] = $cells;
        }
        $reader->close();

        $section = '';
        $items = [];
        foreach ($rowsRaw as $row) {
            $article = trim((string) ($row[$c['article']] ?? ''));
            $name    = trim((string) ($row[$c['name']] ?? ''));
            $priceRaw = (string) ($row[$c['price']] ?? '');

            // Section header: text in col B, but no name and no price.
            if ($article !== '' && $name === '' && trim($priceRaw) === '') {
                $section = $article;
                continue;
            }
            if ($name === '') {
                continue;
            }
            $price = $this->num($priceRaw);
            if ($price === null) {
                continue; // not a priced product row
            }
            $brand = $this->brandFromSection($section);
            if ($brand === null) {
                continue; // not one of the requested brands
            }
            $category = $this->categoryFromSection($section);
            if (! $this->option('all-categories') && ! in_array($category, self::ALLOWED_CATEGORIES, true)) {
                continue; // stoves/fireplaces/sauna only by default
            }

            $items[] = [
                'article'      => $article !== '' ? $article : ('LIG-' . substr(md5($section . $name), 0, 10)),
                'norm_article' => $this->normArticle($article),
                'brand'        => $brand,
                'name'         => $name,
                'section'      => $section,
                'price'        => $price,                                   // Цена Опт (закупка)
                'retail_price' => $this->num((string) ($row[$c['retail']] ?? '')), // Цена розница
                'status_text'  => trim((string) ($row[$c['status']] ?? '')),
                'category_id'  => $category,
            ];
        }
        return $items;
    }

    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $out = [];
        $reader = new \XMLReader();
        $reader->XML($xml);
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'si') {
                $node = new \SimpleXMLElement($reader->readOuterXML());
                $t = '';
                foreach ($node->xpath('.//*[local-name()="t"]') as $tn) {
                    $t .= (string) $tn;
                }
                $out[] = $t;
            }
        }
        $reader->close();
        return $out;
    }

    private function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    // ── Brand / category from section ─────────────────────────────────────────────

    private function brandFromSection(string $section): ?string
    {
        $low = mb_strtolower($section);
        foreach (self::BRAND_MAP as $needle => $canonical) {
            if (mb_strpos($low, $needle) !== false) {
                return $canonical;
            }
        }
        return null;
    }

    private function categoryFromSection(string $section): int
    {
        $low = mb_strtolower($section);
        foreach (self::CATEGORY_MAP as $kw => $cat) {
            if (mb_strpos($low, $kw) !== false) {
                return $cat;
            }
        }
        return self::DEFAULT_CATEGORY;
    }

    // ── Stock ─────────────────────────────────────────────────────────────────────

    private function stock(string $text): array
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return ['status' => 'unknown', 'in_stock' => false, 'delivery_days' => null];
        }
        if (str_contains($t, 'заканч')) {
            return ['status' => 'low_stock', 'in_stock' => true, 'delivery_days' => 0];
        }
        if (str_contains($t, 'налич') || str_contains($t, 'склад') || str_contains($t, 'есть')) {
            return ['status' => 'in_stock', 'in_stock' => true, 'delivery_days' => 0];
        }
        if (str_contains($t, 'заказ')) {
            return ['status' => 'preorder', 'in_stock' => false, 'delivery_days' => 14];
        }
        if (str_contains($t, 'нет') || str_contains($t, 'отсут')) {
            return ['status' => 'out_of_stock', 'in_stock' => false, 'delivery_days' => null];
        }
        return ['status' => 'unknown', 'in_stock' => false, 'delivery_days' => null];
    }

    // ── Index & matching ──────────────────────────────────────────────────────────

    private function buildIndex(): void
    {
        DB::table('brands')->get(['id', 'name'])->each(function ($b) {
            $this->brandById[(int) $b->id] = $b->name;
            $this->brandByName[mb_strtolower($b->name)] = (int) $b->id;
        });

        $sid = $this->supplierId();
        if ($sid > 0) {
            DB::table('supplier_products')->where('supplier_id', $sid)->whereNotNull('product_id')
                ->get(['supplier_article', 'product_id'])
                ->each(fn ($sp) => $this->indexBySupplierArticle[$this->normArticle($sp->supplier_article)] = (int) $sp->product_id);
        }

        $exclude = array_flip($this->excludeIds);
        DB::table('products')->where('is_archived', false)->get(['id', 'name', 'brand_id'])
            ->each(function ($p) use ($exclude) {
                if (isset($exclude[(int) $p->id])) {
                    return; // about to be archived — don't match to it
                }
                $bid = (int) $p->brand_id;
                if ($bid > 0) {
                    $model = $this->model((string) $p->name, $this->brandById[$bid] ?? '');
                    if ($model !== '') {
                        $this->indexByBrandModel[$bid][$model] = (int) $p->id;
                    }
                }
            });
    }

    private function classify(array $row): array
    {
        $brandId = $this->resolveBrand($row['brand']);
        $stock   = $this->stock($row['status_text']);
        $match   = $this->match($row, $brandId);

        $action = match (true) {
            $match !== null => 'matched',
            default         => 'create_candidate',
        };

        return $row + [
            'matched_product_id'   => $match['product_id'] ?? null,
            'matched_sku'          => $match['sku'] ?? null,
            'confidence'           => $match['confidence'] ?? null,
            'resolved_brand_id'    => $brandId,
            'resolved_category_id' => $row['category_id'],
            'stock'                => $stock,
            'action'               => $action,
        ];
    }

    private function match(array $row, ?int $brandId): ?array
    {
        if (isset($this->indexBySupplierArticle[$row['norm_article']]) && $row['norm_article'] !== '') {
            $pid = $this->indexBySupplierArticle[$row['norm_article']];
            return ['product_id' => $pid, 'sku' => $this->sku($pid), 'confidence' => 'exact_supplier_article'];
        }
        if ($brandId !== null && ! empty($this->indexByBrandModel[$brandId])) {
            $model = $this->model($row['name'], $this->brandById[$brandId] ?? '');
            if ($model !== '' && isset($this->indexByBrandModel[$brandId][$model])) {
                $pid = $this->indexByBrandModel[$brandId][$model];
                return ['product_id' => $pid, 'sku' => $this->sku($pid), 'confidence' => 'brand_model'];
            }
        }
        return null;
    }

    private function resolveBrand(string $name): ?int
    {
        $key = mb_strtolower(trim($name));
        return $this->brandByName[$key] ?? null;
    }

    private function findOrCreateBrand(string $name): int
    {
        $existing = DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
        if ($existing) {
            return (int) $existing;
        }
        return (int) DB::table('brands')->insertGetId([
            'name' => $name, 'slug' => Str::slug($name) ?: Str::random(8),
            'h1' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── Dry-run report ────────────────────────────────────────────────────────────

    private function showDryRun(array $rows): int
    {
        $actions = [];
        $brands  = [];
        $cats    = [];
        $conf    = [];
        foreach ($rows as $r) {
            $actions[$r['action']] = ($actions[$r['action']] ?? 0) + 1;
            $brands[$r['brand']]   = ($brands[$r['brand']] ?? 0) + 1;
            $cats[$r['resolved_category_id']] = ($cats[$r['resolved_category_id']] ?? 0) + 1;
            if ($r['action'] === 'matched') {
                $conf[$r['confidence']] = ($conf[$r['confidence']] ?? 0) + 1;
            }
        }

        $this->newLine();
        $this->table(['метрика', 'кол-во'], [
            ['строк (товары)', count($rows)],
            ['matched', $actions['matched'] ?? 0],
            ['create_candidate', $actions['create_candidate'] ?? 0],
        ]);

        $this->info('По брендам:');
        ksort($brands);
        $this->table(['бренд', 'строк'], array_map(fn ($k, $v) => [$k, $v], array_keys($brands), array_values($brands)));

        $catNames = DB::table('categories')->whereIn('id', array_keys($cats))->pluck('name', 'id');
        $this->info('По категориям (для новых):');
        $this->table(['cat_id', 'категория', 'строк'],
            array_map(fn ($k, $v) => [$k, $catNames[$k] ?? '—', $v], array_keys($cats), array_values($cats)));

        if ($conf !== []) {
            $this->info('Матчинг по уверенности:');
            $this->table(['confidence', 'кол-во'], array_map(fn ($k, $v) => [$k, $v], array_keys($conf), array_values($conf)));
        }

        $this->info('Примеры (12):');
        $this->table(
            ['article', 'brand', 'name', 'опт', 'розн', 'наличие', 'action', 'matched_sku'],
            array_map(fn ($r) => [
                mb_substr($r['article'], 0, 14), $r['brand'], mb_substr($r['name'], 0, 34),
                number_format($r['price'], 2),
                $r['retail_price'] !== null ? number_format($r['retail_price'], 2) : '—',
                $r['stock']['status'], $r['action'], $r['matched_sku'] ?? '—',
            ], array_slice($rows, 0, 12))
        );

        $reportPath = trim((string) ($this->option('candidate-report') ?? ''));
        if ($reportPath !== '') {
            $this->writeCandidateReport($rows, $reportPath);
        }

        $this->newLine();
        $this->line('Запусти с <fg=green>--apply</> (и <fg=green>--create-new</> для новых).');
        return self::SUCCESS;
    }

    private function writeCandidateReport(array $rows, string $reportPath): void
    {
        $candidates = array_values(array_filter($rows, fn (array $row): bool => $row['action'] === 'create_candidate'));
        if ($candidates === []) {
            $this->info('Candidate report skipped: no create_candidate rows.');
            return;
        }

        $path = $this->absoluteReportPath($reportPath);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn('Candidate report failed: cannot write ' . $path);
            return;
        }

        fputcsv($handle, ['article', 'brand', 'category_id', 'section', 'name', 'opt_byn', 'retail_byn', 'stock_status', 'stock_text']);
        foreach ($candidates as $row) {
            fputcsv($handle, [
                $row['article'],
                $row['brand'],
                $row['resolved_category_id'],
                $row['section'],
                $row['name'],
                $row['price'],
                $row['retail_price'],
                $row['stock']['status'] ?? '',
                $row['status_text'],
            ]);
        }
        fclose($handle);

        $this->info(sprintf('Candidate report written: %s (%d rows)', $path, count($candidates)));
    }

    private function absoluteReportPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('#^[A-Za-z]:/#', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    // ── Apply ─────────────────────────────────────────────────────────────────────

    private function applyChanges(array $rows, bool $createNew): int
    {
        $now = now();
        $sid = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $stats = array_fill_keys(['matched', 'created', 'retail_set', 'skipped', 'errors'], 0);

        foreach ($rows as $r) {
            try {
                if ($r['matched_product_id'] !== null) {
                    $pid = (int) $r['matched_product_id'];
                    $this->setRetail($pid, $r['retail_price'], $now, $stats);
                    $this->upsertSupplierProduct($r, $pid, (string) $r['matched_sku'], $sid, $syncId, $now);
                    $stats['matched']++;
                } elseif ($createNew) {
                    $r['resolved_brand_id'] = $r['resolved_brand_id'] ?? $this->findOrCreateBrand($r['brand']);
                    $pid = $this->createProduct($r, $now);
                    $sku = $this->sku($pid);
                    $this->upsertSupplierProduct($r, $pid, $sku, $sid, $syncId, $now);
                    // remember for old→new redirect mapping
                    $bid = (int) $r['resolved_brand_id'];
                    $model = $this->model($r['name'], $this->brandById[$bid] ?? $r['brand']);
                    if ($model !== '') {
                        $this->createdByBrandModel[$bid][$model] = ['id' => $pid, 'slug' => $this->slugOf($pid)];
                    }
                    $stats['created']++;
                    $this->line("[create] {$r['brand']} {$r['name']} → {$sku}");
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("[error] {$r['article']}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->table(['метрика', 'кол-во'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));
        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Retail (products.price) = Лигмет «Цена розница» (per the chosen policy). */
    private function setRetail(int $pid, ?float $retail, $now, array &$stats): void
    {
        if ($retail === null || $retail <= 0) {
            return;
        }
        DB::table('products')->where('id', $pid)->update(['price' => $retail, 'updated_at' => $now]);
        $stats['retail_set']++;
    }

    private function upsertSupplierProduct(array $r, int $pid, string $sku, int $sid, ?int $syncId, $now): void
    {
        $payload = [
            'supplier_article'            => $r['norm_article'] !== '' ? $r['norm_article'] : $r['article'],
            'supplier_article_normalized' => $r['norm_article'],
            'supplier_sync_id' => $syncId,
            'product_id'   => $pid,
            'product_sku'  => $sku,
            'supplier_name' => trim($r['brand'] . ' ' . $r['name']),
            'source_url'   => self::SOURCE_URL,
            'price'        => $r['price'],          // Цена Опт — закупка
            'currency'     => 'BYN',
            'currency_rate' => 1.0,
            'price_byn'    => $r['price'],
            'in_stock'     => $r['stock']['in_stock'],
            'stock_status' => $r['stock']['status'],
            'stock_text'   => $r['status_text'] !== '' ? $r['status_text'] : null,
            'delivery_days' => $r['stock']['delivery_days'],
            'match_status' => 'matched',
            'match_confidence' => $r['confidence'] ?? 'created',
            'raw'          => json_encode(['code' => $r['article'], 'section' => $r['section'], 'retail' => $r['retail_price']], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now,
            'last_stock_synced_at' => $now,
            'updated_at'   => $now,
        ];

        $existing = DB::table('supplier_products')->where('supplier_id', $sid)->where('product_id', $pid)->value('id');
        if ($existing) {
            DB::table('supplier_products')->where('id', $existing)->update($payload);
            return;
        }
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $sid, 'supplier_article' => $payload['supplier_article']],
            $payload + ['created_at' => $now]
        );
    }

    private function createProduct(array $r, $now): int
    {
        $name = trim($r['brand'] . ' ' . $r['name']);
        $retail = $r['retail_price'] ?? 0;
        return (int) DB::table('products')->insertGetId([
            'category_id' => (int) $r['resolved_category_id'],
            'brand_id'    => (int) $r['resolved_brand_id'],
            'name'        => $name,
            'h1'          => $name,
            'sku'         => $this->nextSku(),
            'slug'        => $this->uniqueSlug($name),
            'price'       => $retail,
            'currency'    => 'BYN',
            'images'      => json_encode([]),
            'specs'       => json_encode([]),
            'unit'        => 'шт',
            'is_active'   => true,
            'is_archived' => false,
            'in_stock'    => $r['stock']['in_stock'],
            'is_new'      => true,
            'meta_title'  => $name . ' купить в %city%',
            'meta_description' => $name . ' — купить по выгодной цене в Беларуси.',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    // ── Supplier / sync registration ──────────────────────────────────────────────

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => self::SUPPLIER_NAME, 'is_active' => true, 'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }
        return (int) DB::table('suppliers')->insertGetId([
            'code' => self::SUPPLIER_CODE, 'name' => self::SUPPLIER_NAME, 'currency' => 'BYN',
            'currency_rate' => 1, 'contact' => self::SOURCE_URL,
            'notes' => 'Печи/камины/дымоходы (Лигмет). Цена Опт = закупка; Цена розница = розница сайта.',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function ensureSync($now): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            ['name' => self::SUPPLIER_NAME, 'code' => self::SUPPLIER_CODE, 'title' => 'Лигмет: цены и наличие',
             'description' => 'Цены/наличие из прайса Лигмет. Цена Опт = закупка; Цена розница = розница.',
             'command' => 'supplier:sync-ligmet', 'source_url' => self::SOURCE_URL,
             'image_disk_path' => 'img/products/ligmet', 'is_active' => true,
             'last_run_at' => $now, 'updated_at' => $now, 'created_at' => $now]
        );
        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function supplierId(): int
    {
        static $id = null;
        $id ??= (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        return $id;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function sku(int $pid): string
    {
        return (string) DB::table('products')->where('id', $pid)->value('sku');
    }

    private function slugOf(int $pid): string
    {
        return (string) DB::table('products')->where('id', $pid)->value('slug');
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
        $base = Str::slug($name) ?: 'ligmet';
        $slug = $base; $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function normArticle(string $s): string
    {
        return trim(mb_strtoupper(trim($s)));
    }

    private function model(string $productName, string $brand): string
    {
        $n = mb_strtoupper($productName);
        if ($brand !== '') {
            $n = preg_replace('/' . preg_quote(mb_strtoupper($brand), '/') . '/u', '', $n) ?? $n;
        }
        $n = preg_replace('/[^А-ЯЁA-Z0-9\-\/.]/u', ' ', $n) ?? $n;
        $toks = array_filter(
            preg_split('/\s+/u', trim($n)) ?: [],
            fn ($t) => $t !== '' && ! in_array($t, self::MODEL_STOPWORDS, true)
        );
        return implode(' ', $toks);
    }

    private function num(string $v): ?float
    {
        if (trim($v) === '') {
            return null;
        }
        $clean = str_replace([' ', "\u{A0}", ','], ['', '', '.'], $v);
        if (! preg_match('/-?\d+(?:\.\d+)?/', $clean, $m)) {
            return null;
        }
        return (float) $m[0];
    }

    private function clean(string $v): string
    {
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = str_replace("\u{A0}", ' ', $v);
        return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
    }
}
