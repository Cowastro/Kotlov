<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Создаёт/обновляет поставщика S-TANK (currency=EUR) и привязывает товары.
 *
 * Артикулы из прайса январь 2026:
 *   BER, TA90/TA line (→ AT в каталоге), ET, SOLAR SS (→ Solar SS),
 *   HFWT, HFWT DUO, FRESH
 *
 * Использование:
 *   php artisan supplier:sync-stank
 *   php artisan supplier:sync-stank --dry-run
 *   php artisan supplier:sync-stank --rate=3.5738   # задать курс вручную
 */
class SyncStankCommand extends Command
{
    protected $signature = 'supplier:sync-stank
        {--dry-run   : Показать что изменится без записи}
        {--rate=     : Курс EUR/BYN вместо НБРБ API}';

    protected $description = 'Sync S-TANK EUR prices: создаёт поставщика, supplier_products и обновляет products.price';

    private const SUPPLIER_CODE = 'stank';
    private const SUPPLIER_NAME = 'S-TANK';
    private const NBRB_URL      = 'https://api.nbrb.by/exrates/rates/EUR?parammode=2';

    /**
     * EUR РРЦ из прайса S-TANK (январь 2026).
     * Ключ = нормализованный артикул (СЕРИЯ-РАЗМЕР, верхний регистр, без пробелов).
     */
    private const EUR_PRICES = [
        // BER — баки косвенного нагрева
        'BER-150'  => 484.00,
        'BER-200'  => 542.00,
        'BER-300'  => 842.00,
        'BER-400'  => 1212.00,
        'BER-500'  => 1370.00,
        'BER-750'  => 1792.00,
        'BER-1000' => 2133.00,

        // TA90 / TA line → в каталоге "AT-xxx" и "Prestige AT-xxx"
        'AT-200'   => 442.00,
        'AT-300'   => 476.00,
        'AT-500'   => 542.00,
        'AT-750'   => 655.00,
        'AT-1000'  => 755.00,
        'AT-1200'  => 1069.00,
        'AT-1500'  => 1138.00,
        'AT-2000'  => 1785.00,
        'AT-3000'  => 2814.00,
        'AT-5000'  => 4090.00,

        // ET — электро-буферные (те же цены что TA90)
        'ET-200'   => 442.00,
        'ET-300'   => 476.00,
        'ET-500'   => 542.00,
        'ET-750'   => 655.00,
        'ET-1000'  => 755.00,
        'ET-1200'  => 1069.00,
        'ET-1500'  => 1138.00,
        'ET-2000'  => 1785.00,
        'ET-3000'  => 2814.00,
        'ET-5000'  => 4090.00,

        // SOLAR SS — солнечные
        'SOLARSS-150'  => 1043.91,
        'SOLARSS-200'  => 1116.26,
        'SOLARSS-300'  => 1574.00,
        'SOLARSS-500'  => 2054.00,
        'SOLARSS-750'  => 2491.00,
        'SOLARSS-1000' => 2944.00,
        'SOLARSS-1200' => 3495.00,
        'SOLARSS-1500' => 3603.00,
        'SOLARSS-2000' => 4566.00,

        // HFWT — водонагреватели с ТЭН
        'HFWT-300'  => 598.00,
        'HFWT-500'  => 811.00,
        'HFWT-750'  => 975.00,
        'HFWT-1000' => 1160.00,
        'HFWT-1200' => 1529.00,
        'HFWT-1500' => 1689.00,
        'HFWT-2000' => 2202.00,

        // FRESH
        'FRESH-200' => 543.00,
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // 1. Получить курс EUR/BYN
        $rate = $this->fetchRate();
        if ($rate === null) {
            $this->error('Не удалось получить курс EUR/BYN от НБРБ. Передайте --rate=X.XXXX');
            return 1;
        }
        $this->info("💱 Курс EUR/BYN (НБРБ): {$rate}");

        // 2. Создать/обновить поставщика
        if (!$dryRun) {
            $supplier = Supplier::firstOrCreate(
                ['code' => self::SUPPLIER_CODE],
                [
                    'name'          => self::SUPPLIER_NAME,
                    'currency'      => 'EUR',
                    'currency_rate' => $rate,
                    'is_active'     => true,
                ]
            );
            if (!$supplier->wasRecentlyCreated) {
                $supplier->update(['currency_rate' => $rate, 'currency' => 'EUR']);
            }
            $this->info("📦 Поставщик #{$supplier->id} «{$supplier->name}» (EUR × {$rate})");
        } else {
            $supplier = Supplier::where('code', self::SUPPLIER_CODE)->first();
            $supplierId = $supplier?->id ?? '(будет создан)';
            $this->line("[DRY-RUN] Поставщик stank: EUR × {$rate}");
        }

        // 3. Загрузить все продукты бренда S-TANK
        $products = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('b.name', 'S-TANK')
            ->select('p.id', 'p.name', 'p.price as current_price', 'p.sku')
            ->get();

        $this->info("🔍 Найдено товаров S-TANK в каталоге: " . $products->count());

        // 4. Матчинг и обновление
        $stats = ['matched' => 0, 'updated' => 0, 'no_price' => 0];
        $rows = [];

        foreach ($products as $product) {
            $artKey = $this->extractArticleKey($product->name);
            if ($artKey === null || !isset(self::EUR_PRICES[$artKey])) {
                $this->warn("  ⚠️  Не найдена EUR цена для: {$product->name} (ключ: " . ($artKey ?? 'null') . ")");
                $stats['no_price']++;
                continue;
            }

            $eurPrice = self::EUR_PRICES[$artKey];
            $bynPrice = round($eurPrice * $rate, 2);
            $stats['matched']++;

            $rows[] = [
                'product'   => $product,
                'artKey'    => $artKey,
                'eurPrice'  => $eurPrice,
                'bynPrice'  => $bynPrice,
            ];
        }

        // 5. Показать таблицу
        $tableData = [];
        foreach ($rows as $row) {
            $tableData[] = [
                $row['product']->id,
                mb_substr($row['product']->name, 0, 55),
                $row['artKey'],
                $row['eurPrice'],
                $row['bynPrice'],
                $row['product']->current_price,
                abs($row['bynPrice'] - $row['product']->current_price) > 0.5 ? '⬆ изменится' : '≈ без изм.',
            ];
        }
        $this->table(['ID', 'Товар', 'Артикул', 'EUR', 'BYN новая', 'BYN текущая', 'Статус'], $tableData);

        if ($dryRun) {
            $this->warn('[DRY-RUN] Изменения не применены. Уберите --dry-run для записи.');
            return 0;
        }

        // 6. Применить
        foreach ($rows as $row) {
            $product   = $row['product'];
            $eurPrice  = $row['eurPrice'];
            $bynPrice  = $row['bynPrice'];
            $artKey    = $row['artKey'];

            // supplier_products — upsert по product_id + supplier_id.
            // Если запись уже есть — обновляем. Если нет — вставляем.
            // Не используем updateOrInsert т.к. он пытается INSERT, который падает
            // на unique(supplier_id, supplier_article) когда два товара имеют одинаковый артикул.
            $spData = [
                'supplier_article'            => $artKey,
                'supplier_article_normalized' => strtolower($artKey),
                'supplier_article_compact'    => preg_replace('/[^a-z0-9]/', '', strtolower($artKey)),
                'supplier_name'               => $product->name,
                'price'                       => $eurPrice,
                'currency'                    => 'EUR',
                'currency_rate'               => $rate,
                'price_byn'                   => $bynPrice,
                'in_stock'                    => 1,
                'match_status'                => 'matched',
                'match_confidence'            => 'manual',
                'last_synced_at'              => now(),
                'updated_at'                  => now(),
            ];
            $exists = DB::table('supplier_products')
                ->where('supplier_id', $supplier->id)
                ->where('product_id', $product->id)
                ->exists();
            if ($exists) {
                DB::table('supplier_products')
                    ->where('supplier_id', $supplier->id)
                    ->where('product_id', $product->id)
                    ->update($spData);
            } else {
                try {
                    DB::table('supplier_products')->insert(array_merge($spData, [
                        'supplier_id' => $supplier->id,
                        'product_id'  => $product->id,
                        'created_at'  => now(),
                    ]));
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Другой товар уже занял этот артикул у поставщика.
                    // Цена на products будет обновлена ниже; supplier_products пропускаем.
                    $this->line("  ⚠ Артикул {$artKey} уже занят другим товаром, supplier_products пропущен для #{$product->id}");
                }
            }

            // products.price
            DB::table('products')->where('id', $product->id)->update([
                'price'      => $bynPrice,
                'currency'   => 'BYN',
                'updated_at' => now(),
            ]);

            $stats['updated']++;
        }

        $this->info("✅ Обновлено: {$stats['updated']} | Без EUR цены: {$stats['no_price']}");
        return 0;
    }

    /**
     * Извлечь нормализованный ключ артикула из названия товара.
     * Формат: СЕРИЯ-РАЗМЕР (верхний регистр, без пробелов).
     */
    private function extractArticleKey(string $name): ?string
    {
        $name = ' ' . $name . ' '; // добавляем пробелы для граничных случаев

        // SOLAR SS / Solar SS → SOLARSS-NNN
        if (preg_match('/Solar\s+SS[-\s]?(\d+)/i', $name, $m)) {
            return 'SOLARSS-' . $m[1];
        }

        // FRESH NNN
        if (preg_match('/FRESH\s+(\d+)/i', $name, $m)) {
            return 'FRESH-' . $m[1];
        }

        // HFWT NNN (включая "HFWT - 300" и "HFWT 1000 литров")
        if (preg_match('/HFWT[\s\-]+(\d+)/i', $name, $m)) {
            return 'HFWT-' . $m[1];
        }

        // BER-NNN (не путать с BER2)
        if (preg_match('/\bBER-(\d+)\b/i', $name, $m)) {
            return 'BER-' . $m[1];
        }

        // ET-NNN
        if (preg_match('/\bET[-\s](\d+)\b/i', $name, $m)) {
            return 'ET-' . $m[1];
        }

        // Prestige AT-NNN или AT-NNN (TA90 в прайсе)
        if (preg_match('/(?:Prestige\s+)?AT[-\s](\d+)/i', $name, $m)) {
            return 'AT-' . $m[1];
        }

        return null;
    }

    private function fetchRate(): ?float
    {
        if ($manual = $this->option('rate')) {
            return (float) $manual;
        }

        try {
            $resp = Http::timeout(10)->get(self::NBRB_URL);
            if ($resp->ok()) {
                $rate = $resp->json('Cur_OfficialRate');
                if ($rate > 0) {
                    return round((float) $rate, 4);
                }
            }
            $this->warn('НБРБ вернул некорректный ответ: ' . $resp->body());
        } catch (\Throwable $e) {
            $this->warn('Ошибка НБРБ API: ' . $e->getMessage());
        }

        return null;
    }
}
