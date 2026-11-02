<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\TeplotopPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LEMAX boilers, gas water heaters and radiators, KRATS, EKF and radiator fittings through ООО «ТеплоТоп-Бел»
 * (ttop.by): the two price lists (опт and РРЦ), uploaded together on the supplier page. Every row is kept as an
 * offer (code = the article, or the brand and name). A row links to a card by its earlier link, by the article
 * (in the card SKU or title, or another supplier's article) or by the same model (TeplotopPriceList::modelKey)
 * among the cards of its brand; a card whose model words all stand in one row only is offered for confirmation.
 * One card is one row. Stock is on request («под заказ»). The РРЦ becomes the card price where ТеплоТоп is the
 * card's only supplier.
 *
 *   php artisan supplier:sync-teplotop            # dry run on the last uploaded files
 *   php artisan supplier:sync-teplotop --apply
 */
class SyncTeplotopCommand extends Command
{
    protected $signature = 'supplier:sync-teplotop
        {--opt= : The opt xlsx instead of the last uploaded one}
        {--rrc= : The RRC xlsx instead of the last uploaded one}
        {--link=* : Link by hand: product id or slug:row code (lemaks-classic-20:lemax-classic-20)}
        {--unlink=* : A row code that must not link to any card (a wrong automatic link), kept with --apply}
        {--apply : Write offers, links and prices}';

    protected $description = 'Sync the price lists of ТеплоТоп-Бел (LEMAX, KRATS, EKF, fittings)';

    public const SUPPLIER_CODE = 'teplotop';

    private const SUPPLIER_NAME = 'ООО «ТеплоТоп-Бел» (LEMAX)';

    /** The brand of a row and the names it has in our catalogue. */
    private const BRAND_NAMES = [
        'LEMAX' => ['lemax', 'lemaks', 'лемакс'],
        'KRATS' => ['krats'],
        'EKF' => ['ekf'],
        'Varmega' => ['varmega'],
        'Simplex' => ['simplex'],
        'Sanext' => ['sanext'],
        'Danfoss' => ['danfoss'],
        'Av Engineering' => ['avengineering', 'ave'],
        'Cewal' => ['cewal'],
        'Itap' => ['itap'],
        'РИДАН' => ['ridan', 'ридан'],
    ];

    public function handle(): int
    {
        [$storedOpt, $storedRrc] = TeplotopPriceList::storedPaths();
        $opt = (string) ($this->option('opt') ?: $storedOpt);
        $rrc = (string) ($this->option('rrc') ?: $storedRrc);
        $rows = $opt !== '' && is_file($opt) ? TeplotopPriceList::parse($opt, $rrc) : [];
        if ($rows === []) {
            $this->line('metric.teplotop.rows=0');
            $this->error('Нет загруженного прайса ТеплоТоп или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $supplier, $this->manualLinks($rows));
        // The card address next to each row, to check a link by eye. Radiators without a card are counted, not listed.
        $slugs = DB::table('products')->whereIn('id', array_filter(array_merge(array_column($rows, 'product_id'), array_column($rows, 'suggested_product_id'))))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $id = $row['product_id'] ?: $row['suggested_product_id'];
            if (! $id && str_starts_with($row['code'], 'lemax-rad-')) {
                continue;
            }
            $this->line(sprintf('audit.teplotop.row=%s:%s:%s:%s', Str::slug($row['code']),
                $row['product_id'] ?: ($row['suggested_product_id'] ? 's'.$row['suggested_product_id'] : 'none'),
                $row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked'), $id ? (string) ($slugs[$id] ?? '') : ''));
        }
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.teplotop.{$key}={$value}");
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
            $code = collect($codes)->first(fn (string $c) => $c === trim($code) || Str::slug($c) === trim($code));
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
        // Rows a manager unlinked stay without a card until linked by hand.
        $blocked = array_flip(array_map(fn ($code) => Str::slug((string) $code), (array) $this->option('unlink')));
        if ($supplier) {
            foreach (SupplierProduct::query()->where('supplier_id', $supplier->id)->get(['supplier_article', 'raw']) as $offer) {
                if (! empty($offer->raw['no_auto_link'])) {
                    $blocked[Str::slug((string) $offer->supplier_article)] = true;
                }
            }
        }
        $brandOf = [];
        foreach (DB::table('brands')->get(['id', 'name']) as $brand) {
            $slug = Str::slug((string) $brand->name, '');
            foreach (self::BRAND_NAMES as $rowBrand => $names) {
                if (in_array($slug, $names, true)) {
                    $brandOf[(int) $brand->id] = $rowBrand;
                }
            }
        }
        $cards = DB::table('products')->where('is_archived', false)
            ->where(fn ($q) => $q->whereIn('brand_id', array_keys($brandOf))->orWhereIn('id', $links->values()->all()))
            ->get(['id', 'name', 'sku', 'brand_id']);
        $compact = fn (string $value): string => preg_replace('/[^A-ZА-Я0-9]+/u', '', mb_strtoupper($value)) ?? '';
        $byArticle = [];
        $byModel = [];
        $cardKeys = [];
        foreach ($cards as $card) {
            $brand = $brandOf[(int) $card->brand_id] ?? '';
            if (($sku = $compact((string) $card->sku)) !== '' && mb_strlen($sku) >= 4) {
                $byArticle[$sku][] = (int) $card->id;
            }
            $key = TeplotopPriceList::modelKey((string) $card->name);
            if ($key !== '' && $brand !== '') {
                $byModel[$brand.'|'.$key][] = (int) $card->id;
                $cardKeys[$brand][(int) $card->id] = explode(' ', $key);
            }
        }
        foreach (DB::table('supplier_products')->whereNotNull('product_id')->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->get(['supplier_article', 'product_id']) as $link) {
            if (mb_strlen($article = $compact((string) $link->supplier_article)) >= 4) {
                $byArticle[$article][] = (int) $link->product_id;
            }
        }
        $one = fn (array $ids): ?int => count($ids = array_values(array_unique($ids))) === 1 ? $ids[0] : null;

        $stats = ['rows' => count($rows), 'radiators' => 0, 'manual' => 0, 'linked_before' => 0, 'article' => 0, 'model' => 0, 'suggested' => 0, 'unlinked' => 0];
        $claimed = [];
        foreach ($rows as &$row) {
            $row['key'] = TeplotopPriceList::modelKey($row['name']);
            $row['blocked'] = isset($blocked[Str::slug($row['code'])]) && ! isset($manual[$row['code']]);
            if ($row['blocked']) {
                [$row['product_id'], $row['match_reason'], $row['suggested_product_id']] = [null, null, null];

                continue;
            }
            $productId = (int) ($manual[$row['code']] ?? $links[$row['code']] ?? 0);
            $reason = isset($manual[$row['code']]) ? 'manual' : ($productId ? 'linked_before' : null);
            // «KTL-01-075K/KTL-01-075KS»: each article of the row counts.
            foreach ($row['article'] !== '' ? preg_split('/\s*\/\s*/u', $row['article']) : [] as $article) {
                if (! $productId && mb_strlen($compact($article)) >= 4 && ($id = $one($byArticle[$compact($article)] ?? [])) && ! isset($claimed[$id])) {
                    [$productId, $reason] = [$id, 'article'];
                }
            }
            if (! $productId && ($id = $one($byModel[$row['brand'].'|'.$row['key']] ?? [])) && ! isset($claimed[$id])) {
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

        // A card whose model words all stand in the row («Газовый котел Лемакс Premier 23,2» for «Premier 23,2»
        // is exact; «АОГВ 23,2 Premier» is offered): the row it fits closest, only one row per card.
        $offered = [];
        foreach ($rows as $index => $row) {
            if ($row['product_id'] || $row['blocked'] || ! isset($cardKeys[$row['brand']])) {
                continue;
            }
            $words = explode(' ', $row['key']);
            $candidates = array_keys(array_filter($cardKeys[$row['brand']], fn (array $key, int $id) => ! isset($claimed[$id])
                && count(array_intersect($key, $words)) >= 2 && (count(array_diff($key, $words)) === 0 || count(array_diff($words, $key)) === 0), ARRAY_FILTER_USE_BOTH));
            if (count($candidates) === 1) {
                $offered[$candidates[0]][] = $index;
            }
        }
        foreach ($offered as $id => $indexes) {
            if (count($indexes) === 1) {
                $rows[$indexes[0]]['suggested_product_id'] = $id;
            }
        }
        foreach ($rows as $row) {
            $stats['radiators'] += (int) str_starts_with($row['code'], 'lemax-rad-');
            $stats[$row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked')]++;
        }

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'teplotop_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'ТеплоТоп-Бел: прайсы опт и РРЦ',
            'description' => 'Прайсы ТеплоТоп-Бел (LEMAX, KRATS, EKF, арматура): опт и РРЦ в BYN, загрузка двух файлов на странице поставщика. Остатки по запросу.',
            'command' => 'supplier:sync-teplotop',
            'source_url' => 'https://ttop.by/',
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
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $link = SupplierProduct::query()->firstOrNew(['supplier_id' => $supplier->id, 'supplier_article' => $row['code']]);
                $link->fill([
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => $row['code'],
                    'supplier_article_compact' => str_replace('-', '', $row['code']),
                    'supplier_name' => Str::limit($row['brand'].' '.$row['name'], 250, ''),
                    'source_url' => 'https://ttop.by/',
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    // Stock is on request: ordered from the importer, never hidden.
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'preorder',
                    'stock_text' => ! empty($row['sale']) ? 'Распродажа остатков, наличие уточняет менеджер' : 'Под заказ, наличие уточняет менеджер',
                    'warehouse_name' => 'ТеплоТоп-Бел, Минск',
                    'last_stock_synced_at' => now(),
                    // A manual link made in the admin keeps its mark.
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : $row['match_reason']) : null,
                    'raw' => ['suggested_product_id' => $product ? null : $row['suggested_product_id'], 'sheet' => $row['sheet'], 'article' => $row['article'],
                        'specs' => $row['specs'], 'retail' => $row['rrc'], 'brand' => $row['brand'], 'sale' => ! empty($row['sale']), 'no_auto_link' => $row['blocked'],
                        'model' => trim(($row['article'] !== '' ? 'Арт. '.$row['article'].' · ' : '').$row['sheet'], ' ·')],
                    'last_synced_at' => now(),
                ])->save();
                $stats['offers']++;
                if (! $product) {
                    continue;
                }
                $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                if ($row['rrc'] <= 0) {
                    // No РРЦ in the list: the card keeps its price.
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
                $this->line(sprintf('audit.teplotop.error=%s:%s', Str::slug($row['code']), Str::slug(class_basename($e).' '.Str::limit($e->getMessage(), 60, ''))));
            }
        }
        // Rows missing from the new lists are not offered any more.
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
            'notes' => 'Импортёр LEMAX (котлы, колонки, радиаторы), KRATS, EKF и радиаторной арматуры, ttop.by. Прайсы опт и РРЦ — кнопка «Загрузить прайс» на этой странице. Остатки по запросу.',
        ]);
    }
}
