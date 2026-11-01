<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\KaminproffPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fireway through ЧП «Камин-профессионал» (kaminproff.by): the price list, uploaded on the supplier page.
 * Every row is kept as an offer (code = sheet and name). A row is linked to a card by its earlier link or by
 * the same model (KaminproffPriceList::modelKey) among the Fireway cards and the cards the supplier carried
 * before; a card whose model words all stand in the row is only offered for confirmation. Stock is on request:
 * the offers are «под заказ». The РРЦ becomes the card price where Камин-профессионал is the card's only supplier.
 *
 *   php artisan supplier:sync-kaminproff            # dry run on the last uploaded file
 *   php artisan supplier:sync-kaminproff --apply
 */
class SyncKaminproffCommand extends Command
{
    protected $signature = 'supplier:sync-kaminproff
        {--file= : An xlsx instead of the last uploaded one}
        {--link=* : Link by hand: product id:row code or a part of its name (16756:ilma)}
        {--apply : Write offers, links and prices}';

    protected $description = 'Sync the Fireway price list of Камин-профессионал';

    public const SUPPLIER_CODE = 'kaminproff';

    private const SUPPLIER_NAME = 'Каминпрофессионал (kaminproff.by)';

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: KaminproffPriceList::storedPath());
        $rows = $path !== '' && is_file($path) ? KaminproffPriceList::parse($path) : [];
        if ($rows === []) {
            $this->line('metric.kaminproff.rows=0');
            $this->error('Нет загруженного прайса Fireway или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $supplier, $this->manualLinks($rows));
        foreach ($rows as $row) {
            $this->line(sprintf('audit.kaminproff.row=%s:%s:%s', $row['code'],
                $row['product_id'] ?: ($row['suggested_product_id'] ? 's'.$row['suggested_product_id'] : 'none'),
                $row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked')));
        }
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.kaminproff.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, int> row code => product id */
    private function manualLinks(array $rows): array
    {
        $links = [];
        foreach ((array) $this->option('link') as $pair) {
            [$id, $needle] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $needle = mb_strtolower(trim($needle));
            if (! ctype_digit(trim($id)) || $needle === '' || ! Product::query()->whereKey((int) $id)->exists()) {
                continue;
            }
            $hits = array_values(array_filter($rows, fn (array $row) => $row['code'] === $needle || str_contains(mb_strtolower($row['name']), $needle)));
            if (count($hits) === 1) {
                $links[$hits[0]['code']] = (int) $id;
            } else {
                $this->warn("--link {$pair}: строк найдено ".count($hits).', нужна ровно одна.');
            }
        }

        return $links;
    }

    /** @return array{0:list<array<string,mixed>>,1:array<string,int>} */
    private function classify(array $rows, ?Supplier $supplier, array $manual): array
    {
        $links = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->get(['supplier_article', 'supplier_name', 'product_id'])
            : collect();
        $byCode = $links->pluck('product_id', 'supplier_article');
        $brandIds = DB::table('brands')->get(['id', 'name'])->filter(fn ($brand) => self::isBrand((string) $brand->name))->pluck('id')->all();
        $cards = DB::table('products')->where('is_archived', false)
            ->where(fn ($q) => $q->whereIn('brand_id', $brandIds)->orWhereIn('id', $links->pluck('product_id')->all()))
            ->get(['id', 'name']);
        $byModel = [];
        $cardKeys = [];
        foreach ($cards as $card) {
            $key = KaminproffPriceList::modelKey((string) $card->name);
            if ($key !== '') {
                $byModel[$key][] = (int) $card->id;
                $cardKeys[(int) $card->id] = explode(' ', $key);
            }
        }
        $one = fn (array $ids): ?int => count($ids = array_values(array_unique($ids))) === 1 ? $ids[0] : null;

        $stats = ['rows' => count($rows), 'manual' => 0, 'linked_before' => 0, 'model' => 0, 'suggested' => 0, 'unlinked' => 0];
        $claimed = [];
        foreach ($rows as &$row) {
            $row['key'] = KaminproffPriceList::modelKey($row['name']);
            $productId = (int) ($manual[$row['code']] ?? $byCode[$row['code']] ?? 0);
            $reason = isset($manual[$row['code']]) ? 'manual' : ($productId ? 'linked_before' : null);
            if (! $productId && ($id = $one($byModel[$row['key']] ?? []))) {
                [$productId, $reason] = [$id, 'model'];
            }
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason;
            $row['suggested_product_id'] = null;
            if ($productId) {
                $claimed[$productId] = true;
            }
        }
        unset($row);

        // A card whose model words all stand in the row (the row adds «с закрытой каменкой», a typo…), when it
        // is the only such card and no other row names it exactly: offered, a manager confirms.
        foreach ($rows as &$row) {
            if (! $row['product_id']) {
                $words = array_flip(explode(' ', $row['key']));
                $candidates = array_keys(array_filter($cardKeys, fn (array $key, int $id) => ! isset($claimed[$id])
                    && count($key) >= 2 && count(array_diff($key, array_keys($words))) === 0, ARRAY_FILTER_USE_BOTH));
                if (count($candidates) === 1) {
                    $row['suggested_product_id'] = $candidates[0];
                }
            }
            $stats[$row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked')]++;
        }
        unset($row);

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'kaminproff_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Fireway: прайс «Камин-профессионал»',
            'description' => 'Прайс Fireway (опт и РРЦ в BYN), загрузка на странице поставщика. Остатки по запросу.',
            'command' => 'supplier:sync-kaminproff',
            'source_url' => 'https://kaminproff.by/',
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'rekeyed' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'gone_from_file' => 0, 'errors' => 0];
        $touched = [];
        $seen = [];
        foreach ($rows as $row) {
            try {
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $link = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', $row['code'])->first();
                // An offer of an older list for the same card takes the new code instead of becoming a second one.
                if (! $link && $product) {
                    $link = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $product->id)
                        ->whereNotIn('supplier_article', array_merge($seen, array_column($rows, 'code')))->first();
                    $stats['rekeyed'] += $link ? 1 : 0;
                }
                $link ??= new SupplierProduct(['supplier_id' => $supplier->id]);
                $seen[] = $row['code'];
                $link->fill([
                    'supplier_article' => $row['code'],
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => $row['code'],
                    'supplier_article_compact' => str_replace('-', '', $row['code']),
                    'supplier_name' => $row['name'],
                    'source_url' => 'https://kaminproff.by/',
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    // Stock is on request: ordered from the distributor, never hidden.
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'preorder',
                    'stock_text' => 'Под заказ, наличие уточняет менеджер',
                    'warehouse_name' => 'Камин-профессионал',
                    'last_stock_synced_at' => now(),
                    // A manual link made in the admin keeps its mark.
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : $row['match_reason']) : null,
                    'raw' => ['suggested_product_id' => $product ? null : $row['suggested_product_id'], 'sheet' => $row['sheet'], 'section' => $row['section'],
                        'specs' => $row['specs'], 'retail' => $row['rrc'], 'row' => $row['row'], 'brand' => 'Fireway',
                        'model' => trim($row['sheet'].' · '.$row['section'], ' ·')],
                    'last_synced_at' => now(),
                ])->save();
                $stats['offers']++;
                if (! $product) {
                    continue;
                }
                $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                if ($otherSupplier) {
                    $stats['retail_kept_other_supplier']++;
                } elseif (abs((float) $product->price - $row['rrc']) > 0.005 || $product->requires_price_confirmation) {
                    $product->forceFill(['price' => $row['rrc'], 'requires_price_confirmation' => false])->save();
                    $stats['retail_updated']++;
                }
                if ($product->brand_id) {
                    $supplier->brands()->syncWithoutDetaching([$product->brand_id]);
                }
                $touched[] = (int) $product->id;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($row['code'].': '.$e->getMessage());
            }
        }
        // Rows missing from the new list are not offered any more.
        foreach (SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotIn('supplier_article', $seen)->where('stock_status', '!=', 'out_of_stock')->get() as $gone) {
            $gone->forceFill(['in_stock' => false, 'stock_status' => 'out_of_stock', 'stock_text' => 'Нет в прайсе поставщика', 'last_stock_synced_at' => now()])->save();
            $stats['gone_from_file']++;
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
            'notes' => 'Официальный дистрибьютор Fireway в Беларуси, kaminproff.by. Прайс (опт и РРЦ) — кнопка «Загрузить прайс» на этой странице. Остатки по запросу у менеджера.',
        ]);
    }

    public static function isBrand(string $name): bool
    {
        return Str::slug($name, '') === 'fireway';
    }
}
