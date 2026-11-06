<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Suppliers\StankPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сопоставляет все опубликованные товары S-TANK с фиксированными РРЦ в BYN.
 *
 * Прайс: «Прайс S-TANK октябрь бел.руб. 2026г РБ РРЦ.xlsx» → database/data/stank_price_2026-10.json (StankPriceList).
 * Модель карточки — по артикулу привязки S-TANK, иначе по названию.
 * Новые цены действуют с 07.10.2026.
 *
 * Использование:
 *   php artisan supplier:sync-stank --dry-run
 *   php artisan supplier:sync-stank
 */
class SyncStankCommand extends Command
{
    protected $signature = 'supplier:sync-stank
        {--dry-run : Показать полное сопоставление без записи}
        {--apply-before-effective-date : Применить цены до 07.10.2026 только по явному разрешению}
        {--only-series= : Ограничить проверку сериями через запятую, например BER,HFWT}';

    protected $description = 'Sync S-TANK fixed BYN retail prices from the price list effective 2026-10-07';

    private const SUPPLIER_CODE = 'stank';
    private const SUPPLIER_NAME = 'S-TANK';
    private const EFFECTIVE_FROM = '2026-10-07';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlySeries = array_values(array_filter(array_map(
            static fn (string $series): string => strtoupper(trim($series)),
            explode(',', (string) $this->option('only-series'))
        )));

        $applyBeforeEffectiveDate = (bool) $this->option('apply-before-effective-date');

        if (! $dryRun && ! $applyBeforeEffectiveDate && now('Europe/Minsk')->toDateString() < self::EFFECTIVE_FROM) {
            $this->warn('Новые РРЦ S-TANK действуют с ' . self::EFFECTIVE_FROM . '. Запись до этой даты остановлена.');
            return self::SUCCESS;
        }

        if (! $dryRun && $applyBeforeEffectiveDate && now('Europe/Minsk')->toDateString() < self::EFFECTIVE_FROM) {
            $this->warn('РРЦ применяются досрочно по явному разрешению пользователя.');
        }

        $products = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('b.name', self::SUPPLIER_NAME)
            // A merged duplicate is archived and redirects: it has no price of its own.
            ->where('p.is_archived', false)
            ->select('p.id', 'p.name', 'p.price as current_price', 'p.sku')
            ->orderBy('p.id')
            ->get();

        $this->info('Найдено товаров S-TANK на сервере: ' . $products->count());

        $rows = [];
        $unmatched = [];

        $linked = DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('s.code', self::SUPPLIER_CODE)->whereNotNull('sp.product_id')->pluck('sp.supplier_article', 'sp.product_id');

        foreach ($products as $product) {
            $linkedArticle = $linked[$product->id] ?? null;
            $article = $linkedArticle !== null && StankPriceList::row((string) $linkedArticle) !== null
                ? (string) $linkedArticle
                : StankPriceList::key((string) $product->name);

            if ($article !== null && $onlySeries !== [] && ! collect($onlySeries)->contains(
                static fn (string $series): bool => str_starts_with($article, $series . '-')
            )) {
                continue;
            }

            if ($article === null || StankPriceList::row($article) === null) {
                $unmatched[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'key' => $article,
                ];
                continue;
            }

            $rows[] = [
                'product' => $product,
                'article' => $article,
                'price' => StankPriceList::price($article),
            ];
        }

        $this->table(
            ['ID', 'Товар', 'Артикул', 'РРЦ BYN', 'Текущая цена', 'Статус'],
            collect($rows)->map(function (array $row): array {
                $newPrice = $row['price'];
                $current = (float) $row['product']->current_price;
                $target = $newPrice ?? 0.0;
                $changed = abs($target - $current) > 0.005;

                return [
                    $row['product']->id,
                    mb_substr($row['product']->name, 0, 64),
                    $row['article'],
                    $newPrice === null ? 'по запросу' : number_format($newPrice, 2, '.', ''),
                    number_format($current, 2, '.', ''),
                    $changed ? 'изменится' : 'без изменений',
                ];
            })->all()
        );

        // Safe lines for the server log: every card, its key and prices.
        $this->line('metric.stank.products='.$products->count());
        $this->line('metric.stank.matched='.count($rows));
        $this->line('metric.stank.unmatched='.count($unmatched));
        foreach ($rows as $row) {
            $this->line(sprintf('audit.stank.card=%d:%s:%s:%.2f:%s', $row['product']->id, $row['article'],
                $row['price'] === null ? 'request' : number_format($row['price'], 2, '.', ''), (float) $row['product']->current_price,
                \Illuminate\Support\Str::slug((string) DB::table('products')->where('id', $row['product']->id)->value('slug'))));
        }
        foreach ($unmatched as $item) {
            $this->line(sprintf('audit.stank.unmatched=%d:%s', $item['id'], \Illuminate\Support\Str::slug((string) DB::table('products')->where('id', $item['id'])->value('slug'))));
        }

        if ($unmatched !== []) {
            $this->error('Синхронизация остановлена: есть несопоставленные товары S-TANK.');
            $this->table(
                ['ID', 'Товар', 'SKU', 'Распознанный ключ'],
                array_map(static fn (array $item): array => [
                    $item['id'],
                    $item['name'],
                    $item['sku'] ?: '-',
                    $item['key'] ?: '-',
                ], $unmatched)
            );
            $this->error('Несопоставлено: ' . count($unmatched) . '. Цены не записаны.');
            return self::FAILURE;
        }

        $priceRequestCount = collect($rows)->whereNull('price')->count();
        $this->info('Сопоставлено: ' . count($rows) . '; по запросу: ' . $priceRequestCount . '; несопоставлено: 0.');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Изменения не применены.');
            return self::SUCCESS;
        }

        $supplier = Supplier::firstOrCreate(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => self::SUPPLIER_NAME,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'is_active' => true,
            ]
        );
        $supplier->update([
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
        ]);

        $updated = 0;

        DB::transaction(function () use ($rows, $supplier, &$updated): void {
            foreach ($rows as $row) {
                $product = $row['product'];
                $article = $row['article'];
                $retailPrice = $row['price'];
                $storedPrice = $retailPrice ?? 0.0;

                $supplierData = [
                    'supplier_article' => $article,
                    'supplier_article_normalized' => strtolower($article),
                    'supplier_article_compact' => preg_replace('/[^a-z0-9]/', '', strtolower($article)),
                    'supplier_name' => $product->name,
                    'price' => $storedPrice,
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $storedPrice,
                    'match_status' => 'matched',
                    'match_confidence' => 'manual',
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ];

                $existing = DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->where('product_id', $product->id)
                    ->exists();

                if ($existing) {
                    DB::table('supplier_products')
                        ->where('supplier_id', $supplier->id)
                        ->where('product_id', $product->id)
                        ->update($supplierData);
                } else {
                    try {
                        DB::table('supplier_products')->insert(array_merge($supplierData, [
                            'supplier_id' => $supplier->id,
                            'product_id' => $product->id,
                            'created_at' => now(),
                        ]));
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                        // На сервере одновременно опубликованы обычные AT и Prestige AT
                        // с одинаковыми заводскими артикулами. Обе карточки получают
                        // одну РРЦ, но единичная связь поставщика остаётся за первой.
                        $this->line('Артикул ' . $article . ' уже связан с другой карточкой; цена текущего товара всё равно обновится.');
                    }
                }

                if (abs((float) $product->current_price - $storedPrice) > 0.005) {
                    DB::table('products')->where('id', $product->id)->update([
                        'price' => $storedPrice,
                        'currency' => 'BYN',
                        'updated_at' => now(),
                    ]);
                    $updated++;
                }
            }
        });

        $this->info('Готово. Сопоставлено: ' . count($rows) . '; изменено цен: ' . $updated . '; несопоставлено: 0.');

        return self::SUCCESS;
    }
}
