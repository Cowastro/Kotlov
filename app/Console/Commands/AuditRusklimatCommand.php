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

        // ── Focused brand summary (e.g. Electrolux) ───────────────────────────────
        $focusBrand = (string) $this->option('brand');
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
            $limit = max(1, (int) $this->option('limit'));

            $rows = DB::table('products as p')
                ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
                ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
                ->where('sp.supplier_id', $supplierId)
                ->when($focusBrand !== '', fn ($q) => $q->where('b.name', 'like', '%' . $focusBrand . '%'))
                ->when($this->option('missing-photos'), fn ($q) => $q->where($emptyImages))
                ->orderBy('p.id')
                ->limit($limit)
                ->get(['p.id', 'p.sku', 'p.name', 'p.images', 'p.content', 'sp.supplier_article', 'b.name as brand']);

            $this->newLine();
            $this->info(sprintf('── Per-product list (%d rows) ───────────────────────────', $rows->count()));
            $this->table(
                ['id', 'sku', 'supplier_article', 'brand', 'name', 'photo', 'desc'],
                $rows->map(function ($r) {
                    $hasPhoto = ! in_array(trim((string) $r->images), ['', '[]'], true) && $r->images !== null;
                    $hasDesc  = trim((string) $r->content) !== '';
                    return [
                        $r->id,
                        $r->sku,
                        mb_substr((string) $r->supplier_article, 0, 22),
                        mb_substr((string) ($r->brand ?? '—'), 0, 14),
                        mb_substr((string) $r->name, 0, 44),
                        $hasPhoto ? 'yes' : 'NO',
                        $hasDesc ? 'yes' : 'NO',
                    ];
                })->all()
            );
        }

        $this->newLine();
        $this->line('<fg=yellow>Tip:</> fill empty photos/descriptions with: '
            . '<fg=green>php artisan supplier:enrich-rusklimat --limit=80</> (only touches empty fields).');

        return self::SUCCESS;
    }
}
