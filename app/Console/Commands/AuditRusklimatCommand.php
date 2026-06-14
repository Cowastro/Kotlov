<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only audit of Rusklimat products already in KOTLOV.
 *
 * Reports the real state of the database (run this on production):
 *   - how many products are linked to the Rusklimat supplier
 *   - how many were created by the importer (--create-new) vs matched to existing
 *   - how many have no photo / no description / no specs / no brand
 *   - match-confidence breakdown (how each link was made)
 *   - a focused Electrolux breakdown (+ optional per-product list)
 *
 * NOTHING is written. Safe to run anytime.
 *
 * Usage:
 *   php artisan supplier:audit-rusklimat
 *   php artisan supplier:audit-rusklimat --brand=Electrolux --list
 *   php artisan supplier:audit-rusklimat --missing-photos --list
 */
class AuditRusklimatCommand extends Command
{
    protected $signature = 'supplier:audit-rusklimat
        {--brand=         : Focus the detailed list on one brand (partial match, e.g. --brand=Electrolux)}
        {--list           : Print a per-product table for the focused/missing set}
        {--missing-photos : Restrict the --list table to products without a photo}
        {--limit=80       : Max rows in the per-product list}';

    protected $description = 'Read-only audit of Rusklimat products: photos, descriptions, brands, match confidence (no writes).';

    private const SUPPLIER_CODE = 'rusklimat';

    public function handle(): int
    {
        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . self::SUPPLIER_CODE . '" not found.');
            return self::FAILURE;
        }

        // Base set: products linked to Rusklimat through supplier_products.
        $base = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplierId);

        $emptyImages  = fn ($c) => $c->whereNull('p.images')->orWhere('p.images', '')->orWhere('p.images', '[]');
        $emptyContent = fn ($c) => $c->whereNull('p.content')->orWhere('p.content', '');
        $emptySpecs   = fn ($c) => $c->whereNull('p.specs')->orWhere('p.specs', '')
                                     ->orWhere('p.specs', '[]')->orWhere('p.specs', '{}')->orWhere('p.specs', 'null');

        $linked      = (clone $base)->distinct('p.id')->count('p.id');
        $active      = (clone $base)->where('p.is_archived', false)->distinct('p.id')->count('p.id');
        $archived    = $linked - $active;

        // Heuristic: products created by the importer carry a KOTLOV-###### SKU.
        $created     = (clone $base)->where('p.sku', 'like', 'KOTLOV-%')->distinct('p.id')->count('p.id');
        $matchedExisting = $linked - $created;

        $noPhoto     = (clone $base)->where($emptyImages)->distinct('p.id')->count('p.id');
        $noContent   = (clone $base)->where($emptyContent)->distinct('p.id')->count('p.id');
        $noSpecs     = (clone $base)->where($emptySpecs)->distinct('p.id')->count('p.id');
        $noBrand     = (clone $base)->whereNull('p.brand_id')->distinct('p.id')->count('p.id');
        $noShort     = (clone $base)->where(fn ($c) => $c->whereNull('p.short_description')->orWhere('p.short_description', ''))
                                    ->distinct('p.id')->count('p.id');

        $this->newLine();
        $this->info('── Rusklimat: product state (read-only) ─────────────────────────');
        $this->table(['metric', 'count'], [
            ['linked to Rusklimat (total)',     $linked],
            ['  · active (not archived)',        $active],
            ['  · archived',                     $archived],
            ['created by importer (SKU KOTLOV-)', $created],
            ['matched to existing products',     $matchedExisting],
            ['without photo (images empty)',     $noPhoto],
            ['without long description (content)', $noContent],
            ['without short description',         $noShort],
            ['without specs',                     $noSpecs],
            ['without brand',                     $noBrand],
        ]);

        // ── Match-confidence breakdown (how the link was made) ────────────────────
        $byConfidence = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('product_id')
            ->select('match_confidence', DB::raw('count(*) as c'))
            ->groupBy('match_confidence')
            ->orderByDesc('c')
            ->get();

        $this->newLine();
        $this->info('── Match confidence (supplier_products) ─────────────────────────');
        $this->table(
            ['match_confidence', 'count'],
            $byConfidence->map(fn ($r) => [$r->match_confidence ?? '—', $r->c])->all()
        );

        // ── Stock snapshot ────────────────────────────────────────────────────────
        $byStock = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->select('stock_status', DB::raw('count(*) as c'))
            ->groupBy('stock_status')
            ->orderByDesc('c')
            ->get();

        $this->newLine();
        $this->info('── Stock status (supplier_products) ─────────────────────────────');
        $this->table(
            ['stock_status', 'count'],
            $byStock->map(fn ($r) => [$r->stock_status ?? '—', $r->c])->all()
        );

        // ── Per-brand: photo / description coverage ───────────────────────────────
        $perBrand = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->where('sp.supplier_id', $supplierId)
            ->select(
                DB::raw('COALESCE(b.name, "(no brand)") as brand'),
                DB::raw('count(distinct p.id) as total'),
                DB::raw('sum(case when p.images is null or p.images = "" or p.images = "[]" then 1 else 0 end) as no_photo'),
                DB::raw('sum(case when p.content is null or p.content = "" then 1 else 0 end) as no_desc')
            )
            ->groupBy('b.name')
            ->orderByDesc('total')
            ->get();

        $this->newLine();
        $this->info('── Coverage by brand (top 25) ───────────────────────────────────');
        $this->table(
            ['brand', 'total', 'no_photo', 'no_desc'],
            $perBrand->take(25)->map(fn ($r) => [$r->brand, $r->total, $r->no_photo, $r->no_desc])->all()
        );

        // ── Photo integrity: decode JSON + check physical files ───────────────────
        // SQL "images empty" is NOT enough — [""], [null], ["[]"] and dead paths
        // all look "non-empty". Here we decode each value and stat the real file.
        $focusBrand = (string) $this->option('brand');

        $scan = DB::table('products as p')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->whereIn('p.id', function ($q) use ($supplierId) {
                $q->from('supplier_products')->select('product_id')
                  ->where('supplier_id', $supplierId)->whereNotNull('product_id');
            })
            ->get(['p.id', 'p.sku', 'p.images', 'b.name as brand']);

        $cats   = array_fill_keys(
            ['images_null', 'images_empty_json_array', 'images_invalid_values',
             'images_file_missing', 'images_external_url', 'images_file_exists'],
            0
        );
        $fmt    = array_fill_keys(['img/', 'storage products/', 'legacy proxy-image', 'bare filename', 'http(s) external'], 0);
        $focus  = array_fill_keys(array_keys($cats), 0);
        $focusN = 0;
        $focusLc = mb_strtolower($focusBrand);

        foreach ($scan as $r) {
            [$cat, $format] = $this->classifyImages($r->images, (string) $r->sku, (int) $r->id);
            $cats[$cat]++;
            if ($format !== null) {
                $fmt[$format] = ($fmt[$format] ?? 0) + 1;
            }
            if ($focusBrand !== '' && $r->brand !== null && str_contains(mb_strtolower($r->brand), $focusLc)) {
                $focusN++;
                $focus[$cat]++;
            }
        }

        $realPhotos = $cats['images_file_exists'] + $cats['images_external_url'];
        $brokenOrEmpty = $cats['images_null'] + $cats['images_empty_json_array']
                       + $cats['images_invalid_values'] + $cats['images_file_missing'];

        $this->newLine();
        $this->info('── Photo integrity (decoded JSON + physical files) ──────────────');
        $this->table(['metric', 'count'], [
            ['images = null / empty string',     $cats['images_null']],
            ['images = [] (empty json array)',   $cats['images_empty_json_array']],
            ['images = [""]/[null]/["[]"] (junk)', $cats['images_invalid_values']],
            ['path set but FILE MISSING',        $cats['images_file_missing']],
            ['external http(s) url',             $cats['images_external_url']],
            ['FILE EXISTS on disk',              $cats['images_file_exists']],
            ['── real usable photo (file or url)', $realPhotos],
            ['── broken or empty (no real photo)', $brokenOrEmpty],
        ]);

        $this->newLine();
        $this->info('── First-image path format ──────────────────────────────────────');
        $this->table(
            ['format', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($fmt), array_values($fmt))
        );

        if ($focusBrand !== '') {
            $this->newLine();
            $this->info(sprintf('── %s: photo integrity ──────────────────────────────', $focusBrand));
            $this->table(['metric', 'count'], [
                ['total linked',                 $focusN],
                ['real usable photo',            $focus['images_file_exists'] + $focus['images_external_url']],
                ['empty array []',               $focus['images_empty_json_array']],
                ['junk values',                  $focus['images_invalid_values']],
                ['path set but FILE MISSING',    $focus['images_file_missing']],
                ['null / empty',                 $focus['images_null']],
            ]);
        }

        // ── Focused brand summary (e.g. Electrolux) ───────────────────────────────
        if ($focusBrand !== '') {
            $focus = DB::table('products as p')
                ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
                ->join('brands as b', 'p.brand_id', '=', 'b.id')
                ->where('sp.supplier_id', $supplierId)
                ->where('b.name', 'like', '%' . $focusBrand . '%');

            $fTotal   = (clone $focus)->distinct('p.id')->count('p.id');
            $fNoPhoto = (clone $focus)->where($emptyImages)->distinct('p.id')->count('p.id');
            $fNoDesc  = (clone $focus)->where($emptyContent)->distinct('p.id')->count('p.id');

            $this->newLine();
            $this->info(sprintf('── Brand focus: %s ─────────────────────────────────────', $focusBrand));
            $this->table(['metric', 'count'], [
                ['total linked', $fTotal],
                ['without photo', $fNoPhoto],
                ['without description', $fNoDesc],
            ]);
        }

        // ── Optional per-product list ─────────────────────────────────────────────
        if ($this->option('list')) {
            $limit       = max(1, (int) $this->option('limit'));
            $onlyMissing = (bool) $this->option('missing-photos');

            $q = DB::table('products as p')
                ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
                ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
                ->where('sp.supplier_id', $supplierId)
                ->when($focusBrand !== '', fn ($q) => $q->where('b.name', 'like', '%' . $focusBrand . '%'))
                ->orderBy('p.id')
                ->get(['p.id', 'p.sku', 'p.name', 'p.images', 'p.content', 'sp.supplier_article', 'b.name as brand']);

            $listRows = [];
            foreach ($q as $r) {
                [$cat] = $this->classifyImages($r->images, (string) $r->sku, (int) $r->id);
                $hasPhoto = in_array($cat, ['images_file_exists', 'images_external_url'], true);
                if ($onlyMissing && $hasPhoto) {
                    continue;
                }
                $listRows[] = [
                    $r->id,
                    $r->sku,
                    mb_substr((string) $r->supplier_article, 0, 22),
                    mb_substr((string) ($r->brand ?? '—'), 0, 14),
                    mb_substr((string) $r->name, 0, 40),
                    str_replace('images_', '', $cat),
                    trim((string) $r->content) !== '' ? 'yes' : 'NO',
                ];
                if (count($listRows) >= $limit) {
                    break;
                }
            }

            $this->newLine();
            $this->info(sprintf('── Per-product list (%d rows%s) ─────────────', count($listRows), $onlyMissing ? ', missing photos only' : ''));
            $this->table(['id', 'sku', 'supplier_article', 'brand', 'name', 'photo_state', 'desc'], $listRows);
        }

        $this->newLine();
        $this->line('<fg=yellow>Tip:</> fill empty photos/descriptions with: '
            . '<fg=green>php artisan supplier:enrich-rusklimat --limit=80</> (only touches empty fields).');

        return self::SUCCESS;
    }

    /**
     * Classify a products.images value and locate its first usable photo on disk.
     *
     * @return array{0:string,1:?string}  [category, first-image-path-format|null]
     *   category ∈ images_null | images_empty_json_array | images_invalid_values
     *           | images_file_missing | images_external_url | images_file_exists
     */
    private function classifyImages(?string $raw, string $sku, int $id): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['images_null', null];
        }

        $decoded = json_decode($raw, true);

        // Non-array JSON or plain string in column.
        if (! is_array($decoded)) {
            $trimmed = trim($raw);
            if ($trimmed === '[]') {
                return ['images_empty_json_array', null];
            }
            // Treat a bare non-JSON string as a single path.
            $decoded = [$trimmed];
        }

        if (count($decoded) === 0) {
            return ['images_empty_json_array', null];
        }

        // First genuinely usable entry (non-empty string, not junk markers).
        $first = null;
        foreach ($decoded as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $e = trim($entry);
            if ($e === '' || $e === '[]' || $e === 'null' || $e === '""') {
                continue;
            }
            $first = $e;
            break;
        }

        if ($first === null) {
            return ['images_invalid_values', null];
        }

        if (str_starts_with($first, 'http://') || str_starts_with($first, 'https://')) {
            return ['images_external_url', 'http(s) external'];
        }

        [$file, $format] = $this->resolveLocalFile($first, $sku, $id);

        if ($file !== null && file_exists($file)) {
            return ['images_file_exists', $format];
        }

        return ['images_file_missing', $format];
    }

    /**
     * Map a stored image path to its physical file on disk (production layout),
     * mirroring Product::imageUrl() + the /proxy-image route.
     *
     * @return array{0:?string,1:string}  [absolute-file-path|null, format-label]
     */
    private function resolveLocalFile(string $path, string $sku, int $id): array
    {
        // img/... → public/img/...
        if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
            return [public_path(ltrim($path, '/')), 'img/'];
        }

        // products/... → storage symlink → public/storage/products/...
        if (str_starts_with($path, 'products/')) {
            return [public_path('storage/' . $path), 'storage products/'];
        }

        // product/... → /proxy-image/product/... → public/images/product/...
        if (str_starts_with($path, 'product/')) {
            return [public_path('images/' . $path), 'legacy proxy-image'];
        }

        // 000/000065/file.jpg → public/images/product/000/000065/file.jpg
        if (substr_count($path, '/') >= 2) {
            return [public_path('images/product/' . $path), 'legacy proxy-image'];
        }

        // Bare filename → reconstruct legacy dir from SKU, then ID (as imageUrl does).
        $skuParts  = explode('.', $sku);
        $firstRaw  = explode('-', $skuParts[0] ?? '')[1] ?? null;
        $secondRaw = $skuParts[1] ?? null;

        if ($firstRaw !== null && $secondRaw !== null && is_numeric($firstRaw) && is_numeric($secondRaw)) {
            $n1   = (int) $firstRaw;
            $dir1 = sprintf('00%d', $n1);
            $dir2 = sprintf('%s%03d', str_pad((string) $n1, 3, '0', STR_PAD_LEFT), (int) $secondRaw);
            return [public_path('images/product/' . $dir1 . '/' . $dir2 . '/' . $path), 'bare filename'];
        }

        if ($id > 0) {
            $n1   = (int) floor($id / 1000);
            $dir1 = sprintf('00%d', $n1);
            $dir2 = str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            return [public_path('images/product/' . $dir1 . '/' . $dir2 . '/' . $path), 'bare filename'];
        }

        return [null, 'bare filename'];
    }
}
