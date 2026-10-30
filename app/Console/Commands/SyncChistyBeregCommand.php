<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSync;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\ChistyBeregPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Чистый берег (bereg.by): stock and prices sent every morning as xls, uploaded on the supplier page.
 * Every row is kept as a supplier offer (article = the supplier's 8-digit code); a row is linked to a
 * card only by a sure key — its earlier link, the manufacturer code (as a card SKU or another supplier's
 * article) or the exact name — and only within the same brand. The rest wait for manual linking.
 * The recommended retail becomes the card price where Чистый берег is the card's only supplier; where the file
 * gives no margin (РРЦ equals опт) the market price found by supplier:chisty-bereg-market-prices is used instead.
 *
 *   php artisan supplier:sync-chisty-bereg                 # dry run on the last uploaded file
 *   php artisan supplier:sync-chisty-bereg --apply
 *   php artisan supplier:sync-chisty-bereg --apply --if-new   # scheduler, every 10 minutes
 */
class SyncChistyBeregCommand extends Command
{
    protected $signature = 'supplier:sync-chisty-bereg
        {--file= : An xls instead of the last uploaded one}
        {--if-new : Only when a file was uploaded after the last run (the scheduler checks every 10 minutes)}
        {--apply : Write offers, links, prices and stock}';

    protected $description = 'Sync stock and prices of Чистый берег from the daily xls';

    public const SUPPLIER_CODE = 'chisty_bereg';

    private const SUPPLIER_NAME = 'Чистый берег';

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: ChistyBeregPriceList::storedPath());
        if ($this->option('if-new')) {
            $lastRun = SupplierSync::query()->where('key', 'chisty_bereg_price')->value('last_run_at');
            if ($path === '' || ! is_file($path) || ($lastRun && filemtime($path) <= strtotime((string) $lastRun))) {
                $this->line('metric.chisty_bereg.new_file=0');

                return self::SUCCESS;
            }
        }
        $rows = $path !== '' && is_file($path) ? ChistyBeregPriceList::parse($path) : [];
        if ($rows === []) {
            $this->line('metric.chisty_bereg.rows=0');
            $this->error('Нет загруженного файла Чистого берега или в нём нет строк.');

            return self::FAILURE;
        }

        $supplier = $this->option('apply') ? $this->supplier() : Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        [$rows, $stats] = $this->classify($rows, $supplier);
        if (! $this->option('apply')) {
            // Where the rows go: linked or not, per supplier category and per brand.
            foreach (['category' => 'category', 'brand' => 'brand'] as $label => $field) {
                foreach (collect($rows)->groupBy(fn ($row) => Str::slug((string) $row[$field]) ?: 'none') as $group => $items) {
                    $this->line(sprintf('audit.chisty_bereg.%s=%s:%d:%d', $label, Str::limit($group, 60, ''), $items->count(), $items->whereNotNull('product_id')->count()));
                }
            }
            foreach ($stats as $key => $value) {
                $this->line("metric.chisty_bereg.{$key}={$value}");
            }

            return self::SUCCESS;
        }

        $stats += $this->apply($rows, $supplier);
        foreach ($stats as $key => $value) {
            $this->line("metric.chisty_bereg.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @return array{0:list<array<string,mixed>>,1:array<string,int>} */
    private function classify(array $rows, ?Supplier $supplier): array
    {
        $links = $supplier
            ? SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('product_id', 'supplier_article')
            : collect();
        $brands = DB::table('brands')->pluck('name', 'id')->map(fn ($name) => self::brandKey((string) $name));
        $productBrand = [];
        $bySku = $byName = $byModel = [];
        foreach (DB::table('products')->where('is_archived', false)->get(['id', 'sku', 'name', 'brand_id']) as $product) {
            $productBrand[(int) $product->id] = (string) ($brands[$product->brand_id] ?? '');
            if ($productBrand[(int) $product->id] !== '' && ($model = self::modelKey((string) $product->name, $productBrand[(int) $product->id])) !== '') {
                $byModel[$productBrand[(int) $product->id]][$model][] = (int) $product->id;
            }
            if (($sku = self::key((string) $product->sku)) !== '') {
                $bySku[$sku][] = (int) $product->id;
            }
            $byName[self::nameKey((string) $product->name)][] = (int) $product->id;
        }
        $byArticle = [];
        foreach (DB::table('supplier_products')->whereNotNull('product_id')->when($supplier, fn ($q) => $q->where('supplier_id', '!=', $supplier->id))
            ->get(['supplier_article', 'product_id']) as $link) {
            if (($article = self::key((string) $link->supplier_article)) !== '') {
                $byArticle[$article][] = (int) $link->product_id;
            }
        }
        // Linked to another card of the same brand only: a manufacturer code repeats across brands.
        $sure = function (array $ids, string $brand) use ($productBrand): ?int {
            $ids = array_values(array_unique($ids));
            if (count($ids) !== 1) {
                return null;
            }
            $cardBrand = $productBrand[$ids[0]] ?? '';

            return $brand === '' || $cardBrand === '' || $cardBrand === $brand ? $ids[0] : null;
        };

        $stats = ['rows' => count($rows), 'linked_before' => 0, 'maker_code' => 0, 'exact_name' => 0, 'suggested' => 0, 'unlinked' => 0, 'in_stock' => 0];
        $claimed = $links->flip();
        foreach ($rows as &$row) {
            $brand = in_array(mb_strtolower($row['brand']), ['', 'без бренда'], true) ? '' : self::brandKey($row['brand']);
            $productId = (int) ($links[$row['code']] ?? 0);
            $reason = $productId ? 'linked_before' : null;
            $makerKey = self::key($row['maker_code']);
            if (! $productId && strlen($makerKey) >= 5 && preg_match('/\d/', $makerKey) === 1
                && ($id = $sure($bySku[$makerKey] ?? [], $brand) ?? $sure($byArticle[$makerKey] ?? [], $brand))) {
                [$productId, $reason] = [$id, 'maker_code'];
            }
            if (! $productId && ($id = $sure($byName[self::nameKey($row['name'])] ?? [], $brand))) {
                [$productId, $reason] = [$id, 'exact_name'];
            }
            $row['product_id'] = $productId ?: null;
            $row['match_reason'] = $reason;
            // Not sure enough to link: the one card of the brand with the same model is offered for confirmation.
            $row['suggested_product_id'] = null;
            if (! $productId && $brand !== '' && ($model = self::modelKey($row['name'], $brand)) !== '') {
                $candidates = array_values(array_unique(array_filter($byModel[$brand][$model] ?? [], fn (int $id) => ! isset($claimed[$id]))));
                if (count($candidates) === 1) {
                    $row['suggested_product_id'] = $candidates[0];
                    $reason = 'suggested';
                }
            }
            $stats[$reason ?? 'unlinked']++;
            $stats['in_stock'] += (int) ($row['free'] > 0);
        }
        unset($row);

        return [$rows, $stats];
    }

    /** @return array<string,int> */
    private function apply(array $rows, Supplier $supplier): array
    {
        $sync = SupplierSync::query()->updateOrCreate(['key' => 'chisty_bereg_price'], [
            'supplier_id' => $supplier->id,
            'name' => self::SUPPLIER_NAME,
            'code' => self::SUPPLIER_CODE,
            'title' => 'Чистый берег: остатки и цены',
            'description' => 'Ежедневный файл «Остатки и цены Чистого берега»: опт, РРЦ и свободный остаток. Загрузка на странице поставщика.',
            'command' => 'supplier:sync-chisty-bereg',
            'source_url' => 'https://www.bereg.by/',
            'is_active' => true,
            'last_run_at' => now(),
            'last_status' => 'running',
        ]);
        $stats = ['offers' => 0, 'retail_updated' => 0, 'retail_kept_other_supplier' => 0, 'retail_no_margin' => 0, 'retail_market' => 0, 'gone_from_file' => 0, 'errors' => 0];
        $touched = [];
        $seen = [];
        foreach ($rows as $row) {
            $seen[] = $row['code'];
            try {
                $link = SupplierProduct::query()->firstOrNew(['supplier_id' => $supplier->id, 'supplier_article' => $row['code']]);
                $product = $row['product_id'] ? Product::query()->find($row['product_id']) : null;
                $market = (array) $link->raw;
                $link->fill([
                    'supplier_sync_id' => $sync->id,
                    'product_id' => $product?->id,
                    'product_sku' => $product?->sku,
                    'supplier_article_normalized' => $row['code'],
                    'supplier_article_compact' => $row['code'],
                    'supplier_name' => $row['name'],
                    'source_url' => 'https://www.bereg.by/search/?q='.$row['code'],
                    'price' => $row['wholesale'] ?: null,
                    'price_byn' => $row['wholesale'] ?: null,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'in_stock' => $row['free'] > 0,
                    'stock_quantity' => $row['free'],
                    'stock_status' => $row['free'] > 0 ? 'in_stock' : 'out_of_stock',
                    'stock_text' => $row['free'] > 0 ? 'На складе Чистого берега: '.rtrim(rtrim(number_format($row['free'], 2, '.', ''), '0'), '.').' '.$row['unit'] : 'Нет на складе',
                    'warehouse_name' => 'Чистый берег',
                    'last_stock_synced_at' => now(),
                    // A manual link made in the admin keeps its mark.
                    'match_status' => $product ? 'matched' : ($row['suggested_product_id'] ? 'suggested' : 'unmatched'),
                    'match_confidence' => $product ? ($link->match_confidence === 'manual_admin' ? 'manual_admin' : $row['match_reason']) : null,
                    'raw' => ['suggested_product_id' => $product ? null : $row['suggested_product_id'], 'maker_code' => $row['maker_code'], 'brand' => $row['brand'], 'category' => $row['category'], 'unit' => $row['unit'],
                        'pack' => $row['pack'], 'stock' => $row['stock'], 'reserve' => $row['reserve'], 'retail' => $row['rrc'] ?: null,
                        'market_price' => $market['market_price'] ?? null, 'market_checked_at' => $market['market_checked_at'] ?? null,
                        // Marks of the background jobs: a new file must not send the cards back to their queues.
                        'photo_searched_at' => $market['photo_searched_at'] ?? null, 'specs_refilled_at' => $market['specs_refilled_at'] ?? null,
                        'model' => trim(($row['maker_code'] !== '' ? 'Код произв. '.$row['maker_code'] : '').' · '.$row['brand'].' · '.$row['category'], ' ·')],
                    'last_synced_at' => now(),
                ])->save();
                $stats['offers']++;
                if (! $product) {
                    continue;
                }
                if ($row['rrc'] > 0) {
                    $otherSupplier = $product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
                    if ($otherSupplier) {
                        $stats['retail_kept_other_supplier']++;
                    } else {
                        $retail = self::retailPrice((float) $row['wholesale'], (float) $row['rrc'], $market['market_price'] ?? null);
                        if ($retail !== (float) $row['rrc']) {
                            $stats['retail_market']++;
                        } elseif ($row['rrc'] <= $row['wholesale'] + 0.005) {
                            $stats['retail_no_margin']++;
                        }
                        if (abs((float) $product->price - $retail) > 0.005 || $product->requires_price_confirmation) {
                            $product->forceFill(['price' => $retail, 'requires_price_confirmation' => false])->save();
                            $stats['retail_updated']++;
                        }
                    }
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
        // Rows missing from today's file are not on the supplier's stock any more.
        foreach (SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotIn('supplier_article', $seen)->where('in_stock', true)->get() as $gone) {
            $gone->forceFill(['in_stock' => false, 'stock_quantity' => 0, 'stock_status' => 'out_of_stock', 'stock_text' => 'Нет в файле поставщика', 'last_stock_synced_at' => now()])->save();
            $stats['gone_from_file']++;
            if ($gone->product_id) {
                $touched[] = (int) $gone->product_id;
            }
        }

        app(SupplierProductAvailabilityService::class)->refreshMany(array_values(array_unique($touched)));
        $sync->forceFill(['last_status' => $stats['errors'] ? 'partial' : 'updated', 'last_exit_code' => $stats['errors'] ? 1 : 0, 'last_run_at' => now()])->save();

        return $stats;
    }

    /** The РРЦ, or the market price above опт where the file leaves no margin. */
    public static function retailPrice(float $wholesale, float $rrc, mixed $market): float
    {
        if ($rrc > $wholesale + 0.005 || ! is_numeric($market) || (float) $market <= $wholesale + 0.005) {
            return $rrc;
        }

        return round((float) $market, 2);
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->firstOrCreate(['code' => self::SUPPLIER_CODE], [
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Инженерная сантехника и отопление, bereg.by. Остатки и цены — ежедневный xls, загрузка на этой странице.',
        ]);
    }

    /** Words around the model in a title («Насос циркуляционный …», «Радиатор стальной панельный …»). */
    private const DESCRIPTIVE = ['насос', 'насосы', 'циркуляционный', 'поверхностный', 'скважинный', 'дренажный', 'погружной', 'насосная', 'станция',
        'котел', 'котёл', 'газовый', 'настенный', 'напольный', 'двухконтурный', 'одноконтурный', 'электрический', 'электрокотел', 'твердотопливный',
        'конденсационный', 'радиатор', 'радиаторы', 'стальной', 'панельный', 'биметаллический', 'алюминиевый', 'секционный', 'водонагреватель',
        'бойлер', 'косвенного', 'нагрева', 'накопительный', 'электрический', 'бак', 'расширительный', 'мембранный', 'для', 'отопления', 'водоснабжения',
        'с', 'и', 'в', 'на', 'гвс', 'л', 'литров', 'вт', 'квт', 'мм',
        // The supplier ends every title with the country of make.
        'россия', 'рф', 'китай', 'италия', 'германия', 'беларусь', 'рб', 'польша', 'турция', 'словения', 'чехия', 'сербия', 'франция', 'испания',
        'дания', 'швеция', 'финляндия', 'корея', 'япония', 'украина', 'венгрия', 'австрия', 'швейцария', 'нидерланды', 'бельгия', 'португалия', 'литва',
        'латвия', 'эстония', 'болгария', 'румыния', 'словакия', 'великобритания', 'сша', 'индия', 'тайвань', 'вьетнам', 'малайзия', 'израиль', 'казахстан'];

    /** Model tokens without the brand, the descriptive words and the country; empty when the title names no number. */
    public static function modelKey(string $name, string $brand): string
    {
        $text = mb_strtolower(str_replace(['ё', 'Ё'], 'е', $name));
        $text = preg_replace('/(?<=\d)(?=\p{L})|(?<=\p{L})(?=\d)/u', ' ', $text) ?? $text;
        $brandTokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(str_replace('-', ' ', $brand))) ?: [];
        $tokens = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [] as $token) {
            if ($token === '' || in_array($token, self::DESCRIPTIVE, true) || in_array($token, $brandTokens, true) || Str::slug($token, '') === $brand) {
                continue;
            }
            $tokens[] = ltrim($token, '0') === '' ? '0' : (preg_match('/^\d+$/', $token) ? ltrim($token, '0') : $token);
        }

        // The words in any order: «Котел электрический MK-S 21 ZOTA» and «Электрокотел ZOTA MK-S 21» are one model.
        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return count($tokens) >= 2 && preg_grep('/\d/', $tokens) ? implode(' ', $tokens) : '';
    }

    private static function key(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', mb_strtoupper($value)) ?? '';
    }

    private static function nameKey(string $value): string
    {
        return trim(preg_replace('/[^a-zа-я0-9]+/u', ' ', mb_strtolower(str_replace('ё', 'е', $value))) ?? '');
    }

    /** «РЕХАУ» and «REHAU», «PRO AQUA» and «Pro Aqua» are one brand. */
    private static function brandKey(string $brand): string
    {
        $key = Str::slug($brand, '');

        return match ($key) {
            'rekhau', 'rehau' => 'rehau',
            default => $key,
        };
    }
}
