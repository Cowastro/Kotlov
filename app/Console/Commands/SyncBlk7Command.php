<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\Blk7Catalog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ООО «БЛК7» (b2b.blk7.by): the open brand pages of the B2B site give the warehouse stock, the retail price and
 * the wholesale price in BYN. Lavoro boilers first. Every item is kept as an offer (code = the article «BLK…»).
 * A row is linked by its earlier link or by the same model (Blk7Catalog::modelKey: series, power, «без
 * автоматики», «двухконтурный») among the brand's cards. Stock on the warehouse is «в наличии», «Под заказ» is
 * preorder. The retail price becomes the card price where БЛК7 is the card's only supplier.
 *
 *   php artisan supplier:sync-blk7            # dry run
 *   php artisan supplier:sync-blk7 --apply
 */
class SyncBlk7Command extends Command
{
    protected $signature = 'supplier:sync-blk7
        {--brand=lavoro : Brand page of the site (lavoro)}
        {--link=* : Link by hand: product id or slug:article (lavoro-l-12:BLK0073754)}
        {--apply : Write offers, links, stock and prices}';

    protected $description = 'Sync stock and prices of ООО «БЛК7» (b2b.blk7.by), Lavoro boilers';

    public const SUPPLIER_CODE = 'blk7';

    private const SUPPLIER_NAME = 'ООО «БЛК7» (Lavoro)';

    public function handle(Blk7Catalog $catalog): int
    {
        $brand = (string) $this->option('brand');
        $rows = $catalog->items($brand);
        if ($rows === []) {
            $this->line('metric.blk7.rows=0');
            $this->error('Сайт b2b.blk7.by не отдал список товаров.');

            return self::FAILURE;
        }
        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $brand, $supplier, $this->manualLinks($rows));
        $slugs = DB::table('products')->whereIn('id', array_filter(array_column($rows, 'product_id')))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $this->line(sprintf('audit.blk7.row=%s:%s:%s:%s', mb_strtolower($row['article']), $row['product_id'] ?: 'none',
                $row['match_reason'] ?? 'unlinked', $row['product_id'] ? (string) ($slugs[$row['product_id']] ?? '') : ''));
        }
        $stats['in_stock'] = count(array_filter($rows, fn (array $r) => ($r['stock'] ?? 0) > 0));
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $brand, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.blk7.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Our brand ids of a site brand («Lavoro», «LAVORO ECO»). @return list<int> */
    public static function brandIds(string $brand): array
    {
        return DB::table('brands')->get(['id', 'name'])
            ->filter(fn ($b) => str_starts_with(Str::slug((string) $b->name, ''), $brand))->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<string, int> article => product id */
    private function manualLinks(array $rows): array
    {
        $links = [];
        foreach ((array) $this->option('link') as $pair) {
            [$id, $article] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $id = ctype_digit(trim($id)) ? (int) $id : (int) Product::query()->where('slug', trim($id))->value('id');
            $article = mb_strtoupper(trim($article));
            if ($id && in_array($article, array_column($rows, 'article'), true)) {
                $links[$article] = $id;
            } else {
                $this->warn("--link {$pair}: нет карточки или строки.");
            }
        }

        return $links;
    }

    /** @return array{0:list<array<string,mixed>>,1:array<string,int>} */
    private function classify(array $rows, string $brand, ?Supplier $supplier, array $manual): array
    {
        $byArticle = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('product_id', 'supplier_article')
            : collect();
        $byModel = [];
        foreach (DB::table('products')->whereIn('brand_id', self::brandIds($brand) ?: [0])->where('is_archived', false)->get(['id', 'name']) as $card) {
            $key = Blk7Catalog::modelKey((string) $card->name);
            if ($key !== '') {
                $byModel[$key][] = (int) $card->id;
            }
        }
        $stats = ['rows' => count($rows), 'manual' => 0, 'linked_before' => 0, 'model' => 0, 'ambiguous' => 0, 'unlinked' => 0];
        $taken = [];
        foreach ($rows as &$row) {
            $row['key'] = Blk7Catalog::modelKey($row['name']);
            $id = (int) ($manual[$row['article']] ?? $byArticle[$row['article']] ?? 0);
            $reason = isset($manual[$row['article']]) ? 'manual' : ($id ? 'linked_before' : null);
            if (! $id && $row['key'] !== '') {
                $ids = array_values(array_unique($byModel[$row['key']] ?? []));
                if (count($ids) === 1 && ! isset($taken[$ids[0]])) {
                    [$id, $reason] = [$ids[0], 'model'];
                } elseif (count($ids) > 1) {
                    $reason = 'ambiguous';
                }
            }
            $row['product_id'] = $id ?: null;
            $row['match_reason'] = $reason;
            if ($id) {
                $taken[$id] = true;
            }
            $stats[$reason ?? 'unlinked']++;
        }
        unset($row);

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, string $brand, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'blk7_'.$brand], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'БЛК7: '.Str::title($brand),
            'description' => 'Открытая страница бренда на b2b.blk7.by: остаток склада, розничная и оптовая цена в BYN. Обновляется каждые 3 часа.',
            'command' => 'supplier:sync-blk7 --brand='.$brand,
            'source_url' => Blk7Catalog::BRANDS[$brand] ?? Blk7Catalog::BASE,
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'gone_from_site' => 0, 'errors' => 0];
        $touched = [];
        $seen = [];
        foreach ($rows as $row) {
            try {
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $link = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', $row['article'])->first()
                    ?? new SupplierProduct(['supplier_id' => $supplier->id]);
                $seen[] = $row['article'];
                $stock = (int) ($row['stock'] ?? 0);
                $link->fill([
                    'supplier_article' => $row['article'],
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => mb_strtolower($row['article']),
                    'supplier_article_compact' => mb_strtolower($row['article']),
                    'supplier_name' => $row['name'],
                    'source_url' => $row['url'],
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $stock > 0,
                    'stock_quantity' => $stock > 0 ? $stock : null,
                    'stock_status' => $stock > 0 ? 'in_stock' : 'preorder',
                    'stock_text' => $stock > 0 ? "На складе поставщика: {$stock} шт." : 'Под заказ',
                    'warehouse_name' => 'БЛК7',
                    'last_stock_synced_at' => now(),
                    'match_status' => $product ? 'matched' : 'unmatched',
                    'match_confidence' => $product ? (in_array($link->match_confidence, ['manual_admin', 'created_from_price'], true) ? $link->match_confidence : $row['match_reason']) : null,
                    'raw' => array_merge(Arr::only((array) $link->raw, ['photo_searched_at', 'deal_by_id', 'no_auto_link']), [
                        'brand' => Str::title($brand), 'name' => $row['name'], 'retail' => $row['retail'], 'image' => $row['image'],
                        'stock' => $row['stock'], 'model' => $row['key'],
                    ]),
                    'last_synced_at' => now(),
                ])->save();
                $stats['offers']++;
                if (! $product) {
                    continue;
                }
                $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                if ($otherSupplier) {
                    $stats['retail_kept_other_supplier']++;
                } elseif ($row['retail'] > 0 && (abs((float) $product->price - $row['retail']) > 0.005 || $product->requires_price_confirmation)) {
                    $product->forceFill(['price' => $row['retail'], 'requires_price_confirmation' => false])->save();
                    $stats['retail_updated']++;
                }
                if ($product->brand_id) {
                    $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                }
                $touched[] = (int) $product->id;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($row['article'].': '.$e->getMessage());
            }
        }
        // Items gone from the brand page are not offered any more (only this brand's offers).
        foreach (SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_sync_id', $sync->id)
            ->whereNotIn('supplier_article', $seen)->where('stock_status', '!=', 'out_of_stock')->get() as $gone) {
            $gone->forceFill(['in_stock' => false, 'stock_quantity' => null, 'stock_status' => 'out_of_stock', 'stock_text' => 'Нет на сайте поставщика', 'last_stock_synced_at' => now()])->save();
            $stats['gone_from_site']++;
            if ($gone->product_id) {
                $touched[] = (int) $gone->product_id;
            }
        }
        app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
        $sync->forceFill(['last_status' => $stats['errors'] ? 'partial' : 'updated', 'last_exit_code' => $stats['errors'] ? 1 : 0, 'last_run_at' => now()])->save();

        return $stats;
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->firstOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'ООО «БЛК7», B2B-сайт b2b.blk7.by. Котлы Lavoro: остаток, розничная и оптовая цена с открытой страницы бренда, обновление каждые 3 часа.',
        ]);
    }
}
