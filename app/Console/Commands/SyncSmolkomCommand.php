<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\SmolkomPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * «Смолком»: Smolcom portals and Dimplex, Royal Flame, Real Flame electric fireplaces with the free stock, опт and
 * РРЦ in BYN. The price list is a shared Google Drive file (--from-drive) or uploaded on the supplier page.
 * Every row is kept as an offer (code = the article). A row is linked by its earlier link, by the article or
 * barcode another supplier or the SKU of a card already has, or by the same model (SmolkomPriceList::modelKey)
 * and brand; a portal comes in colours, so a card naming a colour takes the row of that colour and a card without
 * a colour takes the only row of its model, else it is offered for confirmation. Free stock is «в наличии»,
 * the rest «под заказ». The РРЦ becomes the card price where Смолком is the card's only supplier.
 *
 *   php artisan supplier:sync-smolkom --from-drive            # dry run on the newest file
 *   php artisan supplier:sync-smolkom --from-drive --apply
 */
class SyncSmolkomCommand extends Command
{
    protected $signature = 'supplier:sync-smolkom
        {--file= : An xlsx instead of the last uploaded one}
        {--from-drive : Download the shared Google Drive file first}
        {--link=* : Link by hand: product id or slug:row code or a part of its name (16756:a-albv4bb)}
        {--apply : Write offers, links, stock and prices}';

    protected $description = 'Sync the Смолком price list (Smolcom portals, Dimplex, Royal Flame, Real Flame)';

    public const SUPPLIER_CODE = 'smolkom';

    private const SUPPLIER_NAME = 'ООО «Смолком» (Smolcom, Dimplex, Royal Flame, Real Flame)';

    public function handle(): int
    {
        if ($this->option('from-drive') && ! $this->option('file')) {
            $this->line('metric.smolkom.drive_downloaded='.(int) SmolkomPriceList::fetchFromDrive());
        }
        $path = (string) ($this->option('file') ?: SmolkomPriceList::storedPath());
        $rows = $path !== '' && is_file($path) ? SmolkomPriceList::parse($path) : [];
        if ($rows === []) {
            $this->line('metric.smolkom.rows=0');
            $this->error('Нет прайса «Смолком» или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $supplier, $this->manualLinks($rows));
        $slugs = DB::table('products')->whereIn('id', array_filter(array_merge(array_column($rows, 'product_id'), array_column($rows, 'suggested_product_id'))))->pluck('slug', 'id');
        foreach ($rows as $row) {
            $id = $row['product_id'] ?: $row['suggested_product_id'];
            $this->line(sprintf('audit.smolkom.row=%s:%s:%s:%s', $row['code'],
                $row['product_id'] ?: ($row['suggested_product_id'] ? 's'.$row['suggested_product_id'] : 'none'),
                $row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked'), $id ? (string) ($slugs[$id] ?? '') : ''));
        }
        $stats['in_stock'] = count(array_filter($rows, fn (array $r) => $r['free'] > 0));
        if ($this->option('apply')) {
            $stats += $this->apply($rows, $supplier);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.smolkom.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Our brand ids by price list brand: Smolcom => [..], Royal Flame => [..]. @return array<string, list<int>> */
    public static function brandIds(): array
    {
        $ids = [];
        foreach (DB::table('brands')->get(['id', 'name']) as $brand) {
            $key = Str::slug((string) $brand->name, '');
            $key = $key === 'smolkom' ? 'smolcom' : $key;
            if (isset(SmolkomPriceList::BRANDS[$key])) {
                $ids[SmolkomPriceList::BRANDS[$key]][] = (int) $brand->id;
            }
        }

        return $ids;
    }

    /** @return array<string, int> row code => product id */
    private function manualLinks(array $rows): array
    {
        $links = [];
        foreach ((array) $this->option('link') as $pair) {
            [$id, $needle] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $needle = mb_strtolower(trim($needle));
            $id = ctype_digit(trim($id)) ? (int) $id : (int) Product::query()->where('slug', trim($id))->value('id');
            if (! $id || $needle === '' || ! Product::query()->whereKey($id)->exists()) {
                continue;
            }
            $hits = array_values(array_filter($rows, fn (array $row) => $row['code'] === $needle
                || str_contains(mb_strtolower($row['name'].' '.$row['colour']), $needle)));
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
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->get(['supplier_article', 'product_id'])
            : collect();
        $byCode = $links->pluck('product_id', 'supplier_article');
        $brandIds = self::brandIds();
        $brandOf = [];
        foreach ($brandIds as $brand => $ids) {
            foreach ($ids as $id) {
                $brandOf[$id] = $brand;
            }
        }
        $cards = DB::table('products')->where('is_archived', false)
            ->where(fn ($q) => $q->whereIn('brand_id', array_keys($brandOf) ?: [0])->orWhereIn('id', $links->pluck('product_id')->all() ?: [0]))
            ->get(['id', 'name', 'sku', 'brand_id']);
        $cardInfo = [];
        $bySku = [];
        foreach ($cards as $card) {
            $cardInfo[(int) $card->id] = [
                'brand' => $brandOf[(int) $card->brand_id] ?? null,
                'key' => SmolkomPriceList::modelKey((string) $card->name),
                'colours' => SmolkomPriceList::colourWords((string) $card->name),
            ];
            if (filled($card->sku)) {
                $bySku[mb_strtolower((string) $card->sku)][] = (int) $card->id;
            }
        }
        // The same article or barcode with another supplier of one of these cards.
        $codes = array_values(array_filter(array_merge(array_column($rows, 'article'), array_column($rows, 'barcode'))));
        $byArticle = SupplierProduct::query()->whereIn('supplier_article', $codes ?: ['-'])->whereNotNull('product_id')
            ->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->get(['supplier_article', 'product_id'])->groupBy('supplier_article')->map(fn ($g) => $g->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        $stats = ['rows' => count($rows), 'manual' => 0, 'linked_before' => 0, 'article' => 0, 'model' => 0, 'model_base_colour' => 0, 'suggested' => 0, 'unlinked' => 0];
        // Rows of one model and brand: a card without a colour is that row only when the model has one row.
        $rowsOfModel = [];
        foreach ($rows as $i => $row) {
            $rows[$i]['key'] = SmolkomPriceList::modelKey($row['name']);
            $rows[$i]['colours'] = SmolkomPriceList::colourWords($row['colour']);
            $rowsOfModel[$row['brand'].'|'.$rows[$i]['key']][] = $i;
        }

        $proposals = [];
        foreach ($rows as $i => &$row) {
            $row['product_id'] = null;
            $row['match_reason'] = null;
            $row['suggested_product_id'] = null;
            if ($id = (int) ($manual[$row['code']] ?? 0)) {
                [$row['product_id'], $row['match_reason']] = [$id, 'manual'];

                continue;
            }
            if ($id = (int) ($byCode[$row['code']] ?? 0)) {
                [$row['product_id'], $row['match_reason']] = [$id, 'linked_before'];

                continue;
            }
            $article = array_values(array_unique(array_filter(array_merge(
                $byArticle[$row['article']] ?? [], $byArticle[$row['barcode']] ?? [],
                $bySku[mb_strtolower($row['article'])] ?? [], $row['barcode'] !== '' ? ($bySku[mb_strtolower($row['barcode'])] ?? []) : [],
            ), fn (int $id) => isset($cardInfo[$id]))));
            if (count($article) === 1) {
                $proposals[] = ['row' => $i, 'product' => $article[0], 'score' => 4, 'reason' => 'article'];

                continue;
            }
            if ($row['key'] === '') {
                continue;
            }
            $same = array_keys(array_filter($cardInfo, fn (array $c) => $c['brand'] === $row['brand'] && $c['key'] === $row['key']));
            $exact = array_values(array_filter($same, fn (int $id) => $cardInfo[$id]['colours'] !== [] && $cardInfo[$id]['colours'] === $row['colours']));
            $within = array_values(array_filter($same, fn (int $id) => $cardInfo[$id]['colours'] !== [] && array_diff($cardInfo[$id]['colours'], $row['colours']) === []));
            $plain = array_values(array_filter($same, fn (int $id) => $cardInfo[$id]['colours'] === []));
            $siblings = $rowsOfModel[$row['brand'].'|'.$row['key']];
            if (count($exact) === 1) {
                $proposals[] = ['row' => $i, 'product' => $exact[0], 'score' => 3, 'reason' => 'model'];
            } elseif (count($within) === 1) {
                $proposals[] = ['row' => $i, 'product' => $within[0], 'score' => 2, 'reason' => 'model'];
            } elseif (count($plain) === 1 && count($siblings) === 1) {
                $proposals[] = ['row' => $i, 'product' => $plain[0], 'score' => 2, 'reason' => 'model'];
            } elseif (count($plain) === 1 && $i === $this->baseRow($rows, $siblings)) {
                // A card without a colour for a model in colours stands for the white one (бьянко белый), else the first.
                $proposals[] = ['row' => $i, 'product' => $plain[0], 'score' => 1, 'reason' => 'model_base_colour'];
            }
        }
        unset($row);

        // One card takes one row: the best proposal wins, a tie links neither.
        $taken = array_filter(array_column($rows, 'product_id'));
        $byProduct = collect($proposals)->groupBy('product');
        foreach ($byProduct as $productId => $group) {
            $best = $group->sortByDesc('score')->values();
            if (in_array((int) $productId, $taken, true)) {
                continue;
            }
            if ($best->count() > 1 && $best[0]['score'] === $best[1]['score']) {
                $rows[$best[0]['row']]['suggested_product_id'] = (int) $productId;

                continue;
            }
            $rows[$best[0]['row']]['product_id'] = (int) $productId;
            $rows[$best[0]['row']]['match_reason'] = $best[0]['reason'];
        }
        foreach ($rows as &$row) {
            if ($row['product_id']) {
                $row['suggested_product_id'] = null;
            }
            $stats[$row['match_reason'] ?? ($row['suggested_product_id'] ? 'suggested' : 'unlinked')]++;
        }
        unset($row);

        return [$rows, $stats];
    }

    /** The row a colourless card of a model takes: бьянко белый, then plain white, then the first row. */
    private function baseRow(array $rows, array $siblings): int
    {
        foreach ([['белый', 'бьянко'], ['белый']] as $colours) {
            foreach ($siblings as $i) {
                if ($rows[$i]['colours'] === $colours) {
                    return $i;
                }
            }
        }

        return $siblings[0];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'smolkom_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Смолком: порталы и электрокамины',
            'description' => 'Прайс «Смолком Камин» (Google Drive): свободный остаток, опт 3 и РРЦ в BYN. Обновляется ежедневно, вручную — кнопка на странице поставщика.',
            'command' => 'supplier:sync-smolkom --from-drive',
            'source_url' => 'https://drive.google.com/file/d/'.SmolkomPriceList::DRIVE_FILE_ID.'/view',
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'colour_set' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'gone_from_file' => 0, 'errors' => 0];
        $touched = [];
        $seen = [];
        foreach ($rows as $row) {
            try {
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $link = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', $row['code'])->first()
                    ?? new SupplierProduct(['supplier_id' => $supplier->id]);
                $seen[] = $row['code'];
                $free = $row['free'];
                $link->fill([
                    'supplier_article' => $row['code'],
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => $row['code'],
                    'supplier_article_compact' => str_replace('-', '', $row['code']),
                    'supplier_name' => trim($row['brand'].' '.$row['name'].($row['colour'] !== '' ? ', '.$row['colour'] : '')),
                    'source_url' => $row['link'] ?: null,
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $free > 0,
                    'stock_quantity' => $free > 0 ? $free : null,
                    'stock_status' => $free > 0 ? 'in_stock' : 'preorder',
                    'stock_text' => $free > 0 ? "На складе поставщика: {$free} шт." : ($row['stock'] > 0 ? 'В резерве, под заказ' : 'Под заказ'),
                    'warehouse_name' => 'Смолком',
                    'last_stock_synced_at' => now(),
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : ($link->match_confidence === 'created_from_price' ? 'created_from_price' : $row['match_reason'])) : null,
                    'raw' => array_merge(Arr::only((array) $link->raw, ['photo_searched_at', 'deal_by_id', 'no_auto_link']), [
                        'suggested_product_id' => $product ? null : $row['suggested_product_id'],
                        'brand' => $row['brand'], 'group' => $row['group'], 'section' => $row['section'], 'name' => $row['name'],
                        'colour' => $row['colour'], 'article' => $row['article'], 'barcode' => $row['barcode'], 'retail' => $row['rrc'],
                        'sizes' => $row['sizes'], 'stock' => $row['stock'], 'reserve' => $row['reserve'], 'row' => $row['row'],
                        'model' => trim($row['group'].' · '.$row['section'], ' ·'),
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
                } elseif ($row['rrc'] > 0 && (abs((float) $product->price - $row['rrc']) > 0.005 || $product->requires_price_confirmation)) {
                    $product->forceFill(['price' => $row['rrc'], 'requires_price_confirmation' => false])->save();
                    $stats['retail_updated']++;
                }
                if ($row['colour'] !== '' && ($specs = self::withColour((array) $product->specs, $row['colour'])) !== null) {
                    // The colour of the row: the card joins «Выберите цвет» with the other colours of its model.
                    $product->forceFill(['specs' => $specs])->save();
                    $stats['colour_set'] = ($stats['colour_set'] ?? 0) + 1;
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
        foreach (SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotIn('supplier_article', $seen)->where('stock_status', '!=', 'out_of_stock')->get() as $gone) {
            $gone->forceFill(['in_stock' => false, 'stock_quantity' => null, 'stock_status' => 'out_of_stock', 'stock_text' => 'Нет в прайсе поставщика', 'last_stock_synced_at' => now()])->save();
            $stats['gone_from_file']++;
            if ($gone->product_id) {
                $touched[] = (int) $gone->product_id;
            }
        }

        app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
        $sync->forceFill(['last_status' => $stats['errors'] ? 'partial' : 'updated', 'last_exit_code' => $stats['errors'] ? 1 : 0, 'last_run_at' => now()])->save();

        return $stats;
    }

    /** The specs with «Цвет» added, or null when the card already names a colour. */
    public static function withColour(array $specs, string $colour): ?array
    {
        foreach ($specs as $key => $spec) {
            if ((is_array($spec) && mb_strtolower(trim((string) ($spec['key'] ?? ''))) === 'цвет') || (is_string($key) && mb_strtolower(trim($key)) === 'цвет')) {
                return null;
            }
        }
        $colour = mb_strtolower(trim($colour));
        if ($specs !== [] && ! array_is_list($specs)) {
            $specs['Цвет'] = $colour;

            return $specs;
        }
        $specs[] = ['key' => 'Цвет', 'value' => $colour];

        return $specs;
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->firstOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Порталы Smolcom и электрокамины Dimplex, Royal Flame, Real Flame. Прайс с остатками — общий файл Google Drive, обновляется ежедневно; вручную — кнопка «Загрузить прайс».',
        ]);
    }
}
