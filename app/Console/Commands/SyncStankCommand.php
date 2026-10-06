<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сопоставляет все опубликованные товары S-TANK с фиксированными РРЦ в BYN.
 *
 * Прайс: «Прайс S-TANK октябрь бел.руб. 2026г РБ РРЦ.xlsx».
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
        {--only-series= : Ограничить проверку сериями через запятую, например BER,HFWT}';

    protected $description = 'Sync S-TANK fixed BYN retail prices from the price list effective 2026-10-07';

    private const SUPPLIER_CODE = 'stank';
    private const SUPPLIER_NAME = 'S-TANK';
    private const EFFECTIVE_FROM = '2026-10-07';

    /**
     * Фиксированные РРЦ в белорусских рублях.
     *
     * null означает официальное значение «по запросу» и является валидным
     * сопоставлением, а не отсутствующей ценой.
     *
     * SOLAR SS на сайте укомплектованы титановым анодом, поэтому для этой
     * серии используется колонка РРЦ с титановым анодом.
     */
    private const BYN_PRICES = [
        'BER-150' => 1828.80,
        'BER-200' => 2048.40,
        'BER-300' => 3182.40,
        'BER-400' => 4582.80,
        'BER-500' => 5180.40,
        'BER-750' => 6775.20,
        'BER-1000' => 8064.00,

        'AT-200' => 1591.20,
        'AT-300' => 1713.60,
        'AT-500' => 1951.20,
        'AT-750' => 2358.00,
        'AT-1000' => 2718.00,
        'AT-1200' => 3848.40,
        'AT-1500' => 4096.80,
        'AT-2000' => 6426.00,
        'AT-3000' => 10130.40,
        'AT-5000' => 14724.00,

        'ET-200' => 1591.20,
        'ET-300' => 1713.60,
        'ET-500' => 1951.20,
        'ET-750' => 2358.00,
        'ET-1000' => 2718.00,
        'ET-1200' => 3848.40,
        'ET-1500' => 4096.80,
        'ET-2000' => 6426.00,
        'ET-3000' => 10130.40,
        'ET-5000' => 14724.00,

        'SOLARSS-150' => 3758.00,
        'SOLARSS-200' => 4019.00,
        'SOLARSS-300' => 5666.00,
        'SOLARSS-500' => 7394.00,
        'SOLARSS-750' => 8968.00,
        'SOLARSS-1000' => 10598.00,
        'SOLARSS-1200' => 12582.00,
        'SOLARSS-1500' => 12971.00,
        'SOLARSS-2000' => 16438.00,
        'SOLARSS-3000' => null,

        'HFWT-300' => 2152.80,
        'HFWT-500' => 2919.60,
        'HFWT-750' => 3510.00,
        'HFWT-1000' => 4176.00,
        'HFWT-1200' => 5504.40,
        'HFWT-1500' => 6080.40,
        'HFWT-2000' => 7927.20,
        'HFWT-3000' => null,

        'FRESH-200' => 1954.80,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlySeries = array_values(array_filter(array_map(
            static fn (string $series): string => strtoupper(trim($series)),
            explode(',', (string) $this->option('only-series'))
        )));

        if (! $dryRun && now('Europe/Minsk')->toDateString() < self::EFFECTIVE_FROM) {
            $this->warn('Новые РРЦ S-TANK действуют с ' . self::EFFECTIVE_FROM . '. Запись до этой даты остановлена.');
            return self::SUCCESS;
        }

        $products = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('b.name', self::SUPPLIER_NAME)
            ->select('p.id', 'p.name', 'p.price as current_price', 'p.sku')
            ->orderBy('p.id')
            ->get();

        $this->info('Найдено товаров S-TANK на сервере: ' . $products->count());

        $rows = [];
        $unmatched = [];

        foreach ($products as $product) {
            $article = $this->extractArticleKey($product->name);

            if ($article !== null && $onlySeries !== [] && ! collect($onlySeries)->contains(
                static fn (string $series): bool => str_starts_with($article, $series . '-')
            )) {
                continue;
            }

            if ($article === null || ! array_key_exists($article, self::BYN_PRICES)) {
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
                'price' => self::BYN_PRICES[$article],
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

    private function extractArticleKey(string $name): ?string
    {
        if (preg_match('/Solar\s+SS(?!\s+DUO)[-\s]?(\d+)/i', $name, $matches)) {
            return 'SOLARSS-' . $matches[1];
        }

        if (preg_match('/FRESH[-\s]+(\d+)/i', $name, $matches)) {
            return 'FRESH-' . $matches[1];
        }

        if (preg_match('/HFWT[-\s]+(\d+)/i', $name, $matches)) {
            return 'HFWT-' . $matches[1];
        }

        if (preg_match('/\bBER(?!2)[-\s]+(\d+)\b/i', $name, $matches)) {
            return 'BER-' . $matches[1];
        }

        if (preg_match('/\bET[-\s]+(\d+)\b/i', $name, $matches)) {
            return 'ET-' . $matches[1];
        }

        if (preg_match('/(?:Prestige\s+)?AT[-\s]+(\d+)/i', $name, $matches)) {
            return 'AT-' . $matches[1];
        }

        return null;
    }
}
