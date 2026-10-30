<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\PrometallPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ProMetall through ЧТУП «Бани и печи» (banipechi.by): the wholesale price list, uploaded on the supplier page.
 * Every row is kept as an offer (article = the ProMetall article, p0320); a row is linked to a card only by a
 * sure key — its earlier link, the EAN-13 of the 1С catalogue, the article (a card SKU or another supplier's
 * article) or the exact name, within the ProMetall brand. A card of the brand with the same words of the name
 * is offered for confirmation. Stock is on request: the offers are «под заказ». The РРЦ becomes the card price
 * where ProMetall is the card's only supplier.
 *
 *   php artisan supplier:sync-prometall            # dry run on the last uploaded file
 *   php artisan supplier:sync-prometall --apply
 */
class SyncPrometallCommand extends Command
{
    protected $signature = 'supplier:sync-prometall
        {--file= : An xlsx instead of the last uploaded one}
        {--link=* : Link by hand: article:product id (p0010:16756), kept like a link made in the admin}
        {--apply : Write offers, links and prices}';

    protected $description = 'Sync the ProMetall price list of «Бани и печи»';

    public const SUPPLIER_CODE = 'prometall';

    private const SUPPLIER_NAME = 'ПроМеталл (ЧТУП «Бани и печи»)';

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: PrometallPriceList::storedPath());
        $rows = $path !== '' && is_file($path) ? PrometallPriceList::parse($path) : [];
        if ($rows === []) {
            $this->line('metric.prometall.rows=0');
            $this->error('Нет загруженного прайса ProMetall или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $manual = $this->manualLinks();
        [$rows, $stats] = $this->classify($rows, $supplier, $manual);
        foreach ($rows as $row) {
            $this->line(sprintf('audit.prometall.row=%s:%s:%s:%s', Str::slug($row['code']), $row['product_id'] ?: ($row['suggested_product_id'] ? 's'.$row['suggested_product_id'] : 'none'),
                $row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked'), Str::limit(Str::slug($row['name']), 60, '')));
        }
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.prometall.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @return array{0:list<array<string,mixed>>,1:array<string,int>} */
    /** @return array<string, int> article => product id */
    private function manualLinks(): array
    {
        $links = [];
        foreach ((array) $this->option('link') as $pair) {
            [$code, $id] = array_pad(explode(':', (string) $pair, 2), 2, '');
            if (trim($code) !== '' && ctype_digit(trim($id)) && Product::query()->whereKey((int) $id)->exists()) {
                $links[trim($code)] = (int) $id;
            }
        }

        return $links;
    }

    private function classify(array $rows, ?Supplier $supplier, array $manual = []): array
    {
        $links = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('product_id', 'supplier_article')
            : collect();
        $brandIds = DB::table('brands')->get(['id', 'name'])->filter(fn ($brand) => self::isBrand((string) $brand->name))->pluck('id')->all();
        $brandCards = DB::table('products')->where('is_archived', false)->whereIn('brand_id', $brandIds)->get(['id', 'sku', 'name']);
        $inBrand = $brandCards->pluck('id')->flip();
        $bySku = $byName = $byWords = [];
        foreach (DB::table('products')->where('is_archived', false)->get(['id', 'sku', 'name']) as $product) {
            if (($sku = self::key((string) $product->sku)) !== '') {
                $bySku[$sku][] = (int) $product->id;
            }
        }
        foreach ($brandCards as $product) {
            $byName[self::nameKey((string) $product->name)][] = (int) $product->id;
            if (($words = self::wordsKey((string) $product->name)) !== '') {
                $byWords[$words][] = (int) $product->id;
            }
        }
        $byEan = [];
        foreach (DB::table('integration_products')->whereNotNull('product_id')->whereNotNull('barcode')->get(['barcode', 'product_id']) as $item) {
            $byEan[preg_replace('/\D+/', '', (string) $item->barcode)][] = (int) $item->product_id;
        }
        $byArticle = [];
        foreach (DB::table('supplier_products')->whereNotNull('product_id')->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->get(['supplier_article', 'product_id']) as $link) {
            if (($article = self::key((string) $link->supplier_article)) !== '') {
                $byArticle[$article][] = (int) $link->product_id;
            }
        }
        $one = fn (array $ids): ?int => count($ids = array_values(array_unique($ids))) === 1 ? $ids[0] : null;

        $stats = ['rows' => count($rows), 'manual_admin' => 0, 'linked_before' => 0, 'ean' => 0, 'article' => 0, 'exact_name' => 0, 'suggested' => 0, 'unlinked' => 0];
        $claimed = $links->flip();
        foreach ($rows as &$row) {
            $productId = (int) ($manual[$row['code']] ?? $links[$row['code']] ?? 0);
            $reason = isset($manual[$row['code']]) ? 'manual_admin' : ($productId ? 'linked_before' : null);
            if (! $productId && strlen($row['ean']) >= 8 && ($id = $one($byEan[$row['ean']] ?? []))) {
                [$productId, $reason] = [$id, 'ean'];
            }
            $article = self::key($row['article']);
            // «P0130;132» names two colours of one item: not a sure key.
            if (! $productId && ! str_contains($row['article'], ';') && ($id = $one(array_merge($bySku[$article] ?? [], $byArticle[$article] ?? []))) && isset($inBrand[$id])) {
                [$productId, $reason] = [$id, 'article'];
            }
            if (! $productId && ($id = $one($byName[self::nameKey($row['name'])] ?? []))) {
                [$productId, $reason] = [$id, 'exact_name'];
            }
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason;
            $row['suggested_product_id'] = null;
            if (! $productId && ($words = self::wordsKey($row['name'])) !== '') {
                $candidates = array_values(array_filter(array_unique($byWords[$words] ?? []), fn (int $id) => ! isset($claimed[$id])));
                if (count($candidates) === 1) {
                    $row['suggested_product_id'] = $candidates[0];
                    $reason = 'suggested';
                }
            }
            $stats[$reason ?? 'unlinked']++;
        }
        unset($row);

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'prometall_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'ProMetall: оптовый прайс «Бани и печи»',
            'description' => 'Прайс-лист ProMetall (опт и РРЦ в BYN), загрузка на странице поставщика. Остатки по запросу.',
            'command' => 'supplier:sync-prometall',
            'source_url' => 'https://banipechi.by/',
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'gone_from_file' => 0, 'errors' => 0];
        $touched = [];
        $seen = [];
        foreach ($rows as $row) {
            $seen[] = $row['code'];
            try {
                $link = SupplierProduct::query()->firstOrNew(['supplier_id' => $supplier->id, 'supplier_article' => $row['code']]);
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $link->fill([
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => self::key($row['code']),
                    'supplier_article_compact' => self::key($row['code']),
                    'supplier_name' => 'ProMetall '.$row['name'],
                    'source_url' => 'https://banipechi.by/',
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    // Stock is on request: ordered from the dealer, never hidden.
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'preorder',
                    'stock_text' => 'Под заказ, наличие уточняет менеджер',
                    'warehouse_name' => 'Бани и печи',
                    'last_stock_synced_at' => now(),
                    // A manual link made in the admin keeps its mark.
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : $row['match_reason']) : null,
                    'raw' => ['suggested_product_id' => $product ? null : $row['suggested_product_id'], 'ean' => $row['ean'], 'section' => $row['section'],
                        'description' => $row['description'], 'retail' => $row['rrc'], 'brand' => 'ProMetall',
                        'model' => trim('Арт. '.$row['article'].' · '.$row['section'], ' ·')],
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
            'notes' => 'Дилер ProMetall в Беларуси, banipechi.by. Прайс (опт и РРЦ) — кнопка «Загрузить прайс» на этой странице. Остатки по запросу у менеджера.',
        ]);
    }

    /** «ProMetall», «ProMETALL», «ПроМеталл» are one brand. */
    public static function isBrand(string $name): bool
    {
        return in_array(Str::slug($name, ''), ['prometall', 'prometal'], true);
    }

    /** Words that say what the item is, not which one it is. */
    private const DESCRIPTIVE = ['печь', 'печи', 'банная', 'банный', 'для', 'бани', 'сауны', 'в', 'с', 'и', 'на', 'под', 'заказ', 'prometall', 'прометалл',
        'цв', 'цвет', 'наборный', 'рисунок', 'мм', 'шт'];

    /** The other words of the name in any order; empty when fewer than two are left. */
    public static function wordsKey(string $name): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(str_replace('ё', 'е', $name))) ?: [];
        $words = array_values(array_unique(array_filter($words, fn (string $w) => $w !== '' && ! in_array($w, self::DESCRIPTIVE, true))));
        sort($words);

        return count($words) >= 2 ? implode(' ', $words) : '';
    }

    private static function key(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', mb_strtoupper($value)) ?? '';
    }

    private static function nameKey(string $value): string
    {
        return trim(preg_replace('/[^a-zа-я0-9]+/u', ' ', mb_strtolower(str_replace('ё', 'е', $value))) ?? '');
    }
}
