<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\PrestizhTeploPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «Престиж Тепло»: Vaillant, Protherm, Kospel, Drazice, Reflex, Flamco, Galmet, Afriso, Meibes, Comisa, Conex and
 * more in one price list (opt and retail in EUR, sold in BYN at the supplier's rate), uploaded on the supplier page. Every row is kept as an offer (code = the article). A row links to a card by its earlier
 * link, by the article (in the card SKU or title, or another supplier's article) or by the same model
 * (PrestizhTeploPriceList::modelKey) among the cards of its brand; a card whose model words all stand in one row only is
 * offered for confirmation. One card is one row. The list has no stock: everything is on order. The retail price
 * becomes the card price where «Престиж Тепло» is the card's only supplier.
 *
 *   php artisan supplier:sync-prestizh-teplo            # dry run on the last uploaded file
 *   php artisan supplier:sync-prestizh-teplo --apply
 */
class SyncPrestizhTeploCommand extends Command
{
    protected $signature = 'supplier:sync-prestizh-teplo
        {--file= : The price list xlsx instead of the last uploaded one}
        {--link=* : Link by hand: product id or slug:row code (vaillant-eloblock-ve-6:0010023654)}
        {--unlink=* : A row code that must not link to any card (a wrong automatic link), kept with --apply}
        {--apply : Write offers, links and prices}';

    protected $description = 'Sync the price list of Престиж Тепло (Vaillant, Protherm, Kospel, Drazice…)';

    public const SUPPLIER_CODE = 'prestizh_teplo';

    private const SUPPLIER_NAME = 'Престиж Тепло';

    /** Our brand names a row brand also stands for. */
    private const BRAND_ALIASES = [
        'vaillant' => ['вайлант'], 'protherm' => ['протерм'], 'drazice' => ['дражице'], 'conexbanninger' => ['conex', 'conexbänninger'],
        'grundfos' => ['грундфос'], 'wilo' => ['вило'],
    ];

    public function handle(): int
    {
        $file = (string) ($this->option('file') ?: PrestizhTeploPriceList::storedPath());
        $rows = $file !== '' && is_file($file) ? PrestizhTeploPriceList::parse($file) : [];
        if ($rows === []) {
            $this->line('metric.prestizh.rows=0');
            $this->error('Нет загруженного прайса Престиж Тепло или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $supplier, $this->manualLinks($rows));
        // The card address next to each row, to check a link by eye.
        $slugs = DB::table('products')->whereIn('id', array_filter(array_merge(array_column($rows, 'product_id'), array_column($rows, 'suggested_product_id'))))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $id = $row['product_id'] ?: $row['suggested_product_id'];
            $this->line(sprintf('audit.prestizh.row=%s:%s:%s:%s', Str::slug($row['code']),
                $row['product_id'] ?: ($row['suggested_product_id'] ? 's'.$row['suggested_product_id'] : 'none'),
                $row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked'), $id ? (string) ($slugs[$id] ?? '') : ''));
        }
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.prestizh.{$key}={$value}");
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
        // A card's brand as the row brand it is: the slug of the name, or an alias of ours.
        $rowBrands = array_values(array_unique(array_filter(array_column($rows, 'brand'))));
        $brandOf = [];
        foreach (DB::table('brands')->get(['id', 'name']) as $brand) {
            $slug = Str::slug((string) $brand->name, '');
            foreach ($rowBrands as $rowBrand) {
                $rowSlug = Str::slug($rowBrand, '');
                if ($slug === $rowSlug || in_array($slug, self::BRAND_ALIASES[$rowSlug] ?? [], true)) {
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
            $key = PrestizhTeploPriceList::modelKey((string) $card->name);
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

        $stats = ['rows' => count($rows), 'manual' => 0, 'linked_before' => 0, 'article' => 0, 'model' => 0, 'suggested' => 0, 'unlinked' => 0];
        $claimed = [];
        foreach ($rows as &$row) {
            $row['key'] = PrestizhTeploPriceList::modelKey($row['name']);
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
            $stats[$row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked')]++;
        }

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'prestizh_teplo_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Престиж Тепло: прайс опт и розница',
            'description' => 'Прайс Престиж Тепло (Vaillant, Protherm, Kospel, Drazice, Reflex, Galmet, Afriso, Meibes…): опт и розница в EUR по курсу поставщика в BYN, загрузка файла на странице поставщика. Наличия в прайсе нет — под заказ.',
            'command' => 'supplier:sync-prestizh-teplo',
            'source_url' => null,
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
                    'source_url' => null,
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    // The list has no stock: everything is ordered.
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'preorder',
                    'stock_text' => 'Под заказ, наличие уточняет менеджер',
                    'warehouse_name' => 'Престиж Тепло, Минск',
                    'last_stock_synced_at' => now(),
                    // A manual link made in the admin keeps its mark.
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : $row['match_reason']) : null,
                    // The photo search mark and a sbg.by pair made by hand survive a new price list.
                    'raw' => array_merge(\Illuminate\Support\Arr::only((array) $link->raw, ['photo_searched_at', 'deal_by_id']), ['suggested_product_id' => $product ? null : $row['suggested_product_id'], 'sheet' => $row['sheet'], 'section' => $row['section'], 'article' => $row['article'],
                        'name' => $row['name'], 'description' => $row['description'], 'retail' => $row['rrc'], 'wholesale' => $row['wholesale'], 'retail_eur' => $row['rrc_eur'], 'wholesale_eur' => $row['wholesale_eur'],
                        'brand' => $row['brand'], 'no_auto_link' => $row['blocked'], 'model' => trim('Арт. '.$row['article'].' · '.$row['section'], ' ·')]),
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
                $this->line(sprintf('audit.prestizh.error=%s:%s', Str::slug($row['code']), Str::slug(class_basename($e).' '.Str::limit($e->getMessage(), 60, ''))));
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
            'notes' => 'Дистрибьютор Vaillant, Protherm, Kospel, Drazice, Reflex, Flamco, Galmet, Afriso, Meibes, Comisa, Conex Banninger. Прайс (xlsm, приходит рассылкой со ссылкой на Dropbox) — кнопка «Загрузить прайс» на этой странице.',
        ]);
    }
}
