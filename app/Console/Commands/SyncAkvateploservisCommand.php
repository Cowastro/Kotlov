<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\AkvateploservisPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Galmet and Apamet through ООО «Акватеплосервис» (galmet.by): the price list, uploaded on the supplier page.
 * Every row is kept as an offer (code = the Galmet article, an Apamet row its name). A row is linked to a card
 * by its earlier link, by the Galmet article (in the card SKU or title, or another supplier's article), or by
 * the same model (AkvateploservisPriceList::modelKey) among the Galmet and Apamet cards; a card whose model
 * words all stand in one row only is offered for confirmation. «склад» is in stock, other rows are ordered,
 * discontinued rows are not offered. The РРЦ becomes the card price where Акватеплосервис is the card's only
 * supplier.
 *
 *   php artisan supplier:sync-akvateploservis            # dry run on the last uploaded file
 *   php artisan supplier:sync-akvateploservis --apply
 */
class SyncAkvateploservisCommand extends Command
{
    protected $signature = 'supplier:sync-akvateploservis
        {--file= : An xls instead of the last uploaded one}
        {--link=* : Link by hand: product id or slug:row code (16756:26-308070N)}
        {--apply : Write offers, links and prices}';

    protected $description = 'Sync the Galmet price list of Акватеплосервис';

    public const SUPPLIER_CODE = 'akvateploservis';

    private const SUPPLIER_NAME = 'ООО «Акватеплосервис» (Galmet, Apamet)';

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: AkvateploservisPriceList::storedPath());
        $rows = $path !== '' && is_file($path) ? AkvateploservisPriceList::parse($path) : [];
        if ($rows === []) {
            $this->line('metric.akvateploservis.rows=0');
            $this->error('Нет загруженного прайса Galmet или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $supplier, $this->manualLinks($rows));
        // The card address next to each row, to check a link by eye.
        $slugs = DB::table('products')->whereIn('id', array_filter(array_merge(array_column($rows, 'product_id'), array_column($rows, 'suggested_product_id'))))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $id = $row['product_id'] ?: $row['suggested_product_id'];
            $this->line(sprintf('audit.akvateploservis.row=%s:%s:%s:%s', Str::slug($row['code']),
                $row['product_id'] ?: ($row['suggested_product_id'] ? 's'.$row['suggested_product_id'] : 'none'),
                $row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked'), $id ? (string) ($slugs[$id] ?? '') : ''));
        }
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.akvateploservis.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, int> row code => product id */
    private function manualLinks(array $rows): array
    {
        $codes = array_column($rows, 'code');
        $links = [];
        foreach ((array) $this->option('link') as $pair) {
            [$id, $code] = array_pad(explode(':', (string) $pair, 2), 2, '');
            // A card by its id or its address (the workflow log may mask digits of an id).
            $id = ctype_digit(trim($id)) ? (int) $id : (int) Product::query()->where('slug', trim($id))->value('id');
            $code = collect($codes)->first(fn (string $c) => $c === mb_strtoupper(trim($code)) || Str::slug($c) === trim($code));
            if ($id && $code !== null && Product::query()->whereKey($id)->exists()) {
                $links[$code] = $id;
            } else {
                $this->warn("--link {$pair}: карточка или строка не найдена.");
            }
        }

        return $links;
    }

    /** @return array{0:list<array<string,mixed>>,1:array<string,int>} */
    private function classify(array $rows, ?Supplier $supplier, array $manual): array
    {
        $links = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('product_id', 'supplier_article')
            : collect();
        $brandIds = DB::table('brands')->get(['id', 'name'])->filter(fn ($brand) => self::isBrand((string) $brand->name))->pluck('id')->all();
        $cards = DB::table('products')->where('is_archived', false)
            ->where(fn ($q) => $q->whereIn('brand_id', $brandIds)->orWhereIn('id', $links->values()->all()))
            ->get(['id', 'name', 'sku']);
        // 26-408100N and 26-408100 (a card title without the last N) are one article.
        $compact = fn (string $value): string => rtrim(preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($value)) ?? '', 'N');
        $byArticle = [];
        $byModel = [];
        $cardKeys = [];
        foreach ($cards as $card) {
            // «… FL (26-408000N)»: the Galmet article in the title or the SKU of the card.
            preg_match_all('/\b\d{2}-\d{6}N?\b/u', mb_strtoupper($card->name.' '.$card->sku), $m);
            foreach (array_unique($m[0]) as $article) {
                $byArticle[$compact($article)][] = (int) $card->id;
            }
            $key = AkvateploservisPriceList::modelKey((string) $card->name);
            if ($key !== '') {
                $byModel[$key][] = (int) $card->id;
                $cardKeys[(int) $card->id] = explode(' ', $key);
            }
        }
        foreach (DB::table('supplier_products')->whereNotNull('product_id')->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->whereIn('product_id', $cards->pluck('id'))->get(['supplier_article', 'product_id']) as $link) {
            if (preg_match('/^\d{2}-?\d{6}N?$/i', trim((string) $link->supplier_article))) {
                $byArticle[$compact((string) $link->supplier_article)][] = (int) $link->product_id;
            }
        }
        $one = fn (array $ids): ?int => count($ids = array_values(array_unique($ids))) === 1 ? $ids[0] : null;

        $stats = ['rows' => count($rows), 'manual' => 0, 'linked_before' => 0, 'article' => 0, 'model' => 0, 'suggested' => 0, 'unlinked' => 0];
        $claimed = [];
        foreach ($rows as &$row) {
            $row['key'] = AkvateploservisPriceList::modelKey($row['name']);
            $productId = (int) ($manual[$row['code']] ?? $links[$row['code']] ?? 0);
            $reason = isset($manual[$row['code']]) ? 'manual' : ($productId ? 'linked_before' : null);
            if (! $productId && $row['article'] !== '' && ($id = $one($byArticle[$compact($row['article'])] ?? []))) {
                [$productId, $reason] = [$id, 'article'];
            }
            // One card is one row: a later row of the same model (a discontinued twin) does not take it.
            if (! $productId && ($id = $one($byModel[$row['key']] ?? [])) && ! isset($claimed[$id])) {
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

        // A card linked to two rows before keeps the priced one (the first in the list); the other is unlinked.
        $holder = [];
        foreach ($rows as $index => $row) {
            if ($row['product_id'] && $row['match_reason'] !== 'manual') {
                $current = $holder[$row['product_id']] ?? null;
                if ($current === null || ($rows[$current]['rrc'] <= 0 && $row['rrc'] > 0)) {
                    $holder[$row['product_id']] = $index;
                }
            }
        }
        foreach ($rows as $index => &$row) {
            if ($row['product_id'] && $row['match_reason'] !== 'manual' && ($holder[$row['product_id']] ?? $index) !== $index) {
                [$row['product_id'], $row['match_reason']] = [null, null];
            }
        }
        unset($row);

        // A card whose model words all stand in the row («Neptun Kombi SGW(S)100» for «… 100-R SH» and «… 100-L SH»):
        // offered to the row it fits closest — the fewest extra words, or of rows with one price the first.
        $offered = [];
        foreach ($rows as $index => $row) {
            if ($row['product_id'] || $row['rrc'] <= 0) {
                continue;
            }
            $words = explode(' ', $row['key']);
            $candidates = array_keys(array_filter($cardKeys, fn (array $key, int $id) => ! isset($claimed[$id])
                && count($key) >= 2 && count(array_diff($key, $words)) === 0, ARRAY_FILTER_USE_BOTH));
            if (count($candidates) === 1) {
                $offered[$candidates[0]][] = $index;
            }
        }
        foreach ($offered as $id => $indexes) {
            $extra = fn (int $index): int => count(explode(' ', $rows[$index]['key'])) - count($cardKeys[$id]);
            usort($indexes, fn (int $a, int $b): int => $extra($a) <=> $extra($b) ?: $a <=> $b);
            $closest = array_values(array_filter($indexes, fn (int $index) => $extra($index) === $extra($indexes[0])));
            if (count($closest) === 1 || count(array_unique(array_map(fn (int $index) => $rows[$index]['rrc'], $closest))) === 1) {
                $rows[$closest[0]]['suggested_product_id'] = $id;
            }
        }
        foreach ($rows as $row) {
            $stats[$row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked')]++;
        }

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'akvateploservis_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Galmet: прайс «Акватеплосервис»',
            'description' => 'Прайс Galmet и Apamet (опт и РРЦ в BYN, остатки склада), загрузка на странице поставщика.',
            'command' => 'supplier:sync-akvateploservis',
            'source_url' => 'http://galmet.by/',
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'in_stock' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'gone_from_file' => 0, 'errors' => 0];
        $touched = [];
        $seen = [];
        foreach ($rows as $row) {
            $seen[] = $row['code'];
            try {
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $status = AkvateploservisPriceList::stockStatus($row['stock'], $row['rrc']);
                $priced = $row['rrc'] > 0 && $status !== 'discontinued';
                $link = SupplierProduct::query()->firstOrNew(['supplier_id' => $supplier->id, 'supplier_article' => $row['code']]);
                $link->fill([
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => $row['code'],
                    'supplier_article_compact' => preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($row['code'])),
                    'supplier_name' => $row['brand'].' '.$row['name'],
                    'source_url' => 'http://galmet.by/',
                    'price' => $priced ? ($row['wholesale'] ?: null) : null,
                    'price_byn' => $priced ? ($row['wholesale'] ?: null) : null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $status === 'in_stock',
                    'stock_quantity' => null,
                    'stock_status' => $status === 'discontinued' ? 'out_of_stock' : $status,
                    'stock_text' => $row['stock'] !== '' ? Str::ucfirst($row['stock']) : 'Под заказ, наличие уточняет менеджер',
                    'warehouse_name' => 'Акватеплосервис, Минск',
                    'delivery_days' => $status === 'in_stock' ? 0 : null,
                    'last_stock_synced_at' => now(),
                    // A manual link made in the admin keeps its mark.
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : $row['match_reason']) : null,
                    'raw' => ['suggested_product_id' => $product ? null : $row['suggested_product_id'], 'section' => $row['section'], 'article' => $row['article'],
                        'specs' => $row['specs'], 'retail' => $row['rrc'], 'row' => $row['row'], 'brand' => $row['brand'],
                        'model' => trim(($row['article'] !== '' ? 'Арт. '.$row['article'].' · ' : '').$row['section'], ' ·')],
                    'last_synced_at' => now(),
                ])->save();
                $stats['offers']++;
                $stats['in_stock'] += (int) ($status === 'in_stock');
                if (! $product) {
                    continue;
                }
                $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                if (! $priced) {
                    // No price in the list: the card keeps its price.
                } elseif ($otherSupplier) {
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
            'notes' => 'Импортёр Galmet (Польша) и Apamet (Турция) в Беларуси, galmet.by. Прайс (опт, РРЦ и остатки) — кнопка «Загрузить прайс» на этой странице.',
        ]);
    }

    public static function isBrand(string $name): bool
    {
        return in_array(Str::slug($name, ''), ['galmet', 'apamet'], true);
    }
}
