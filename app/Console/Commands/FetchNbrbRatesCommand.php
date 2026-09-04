<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Загружает официальные курсы НБРБ и обновляет currency_rate у поставщиков.
 * После изменения курса пересчитывает price_byn в supplier_products
 * и price в products для привязанных товаров.
 *
 * Запуск вручную:
 *   php artisan currency:fetch-nbrb-rates
 *   php artisan currency:fetch-nbrb-rates --dry-run
 *
 * В крон — ежедневно (см. routes/console.php).
 */
class FetchNbrbRatesCommand extends Command
{
    protected $signature = 'currency:fetch-nbrb-rates
        {--dry-run : Показать новые курсы без записи}';

    protected $description = 'Обновляет курсы НБРБ (EUR/USD/RUB) у поставщиков и пересчитывает BYN цены';

    /** НБРБ API: parammode=2 → поиск по буквенному коду */
    private const NBRB_API = 'https://api.nbrb.by/exrates/rates/%s?parammode=2';

    /** Коды валют, для которых нужно обновлять курс */
    private const CURRENCIES = ['EUR', 'USD', 'RUB', 'PLN'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // 1. Загрузить курсы с НБРБ
        $rates = [];
        foreach (self::CURRENCIES as $currency) {
            $rate = $this->fetchRate($currency);
            if ($rate !== null) {
                $rates[$currency] = $rate;
                $this->info("💱 {$currency}/BYN = {$rate}");
            } else {
                $this->warn("⚠️  Не удалось получить курс {$currency}");
            }
        }

        if (empty($rates)) {
            $this->error('Не получено ни одного курса. Прерываем.');
            return 1;
        }

        // 2. Найти поставщиков с валютой != BYN
        $suppliers = Supplier::whereIn('currency', array_keys($rates))->get();

        if ($suppliers->isEmpty()) {
            $this->info('Нет поставщиков с иностранной валютой.');
            return 0;
        }

        foreach ($suppliers as $supplier) {
            $newRate = $rates[$supplier->currency] ?? null;
            if ($newRate === null) {
                continue;
            }

            $oldRate = (float) $supplier->currency_rate;
            $changed = abs($newRate - $oldRate) > 0.0001;

            $this->line(sprintf(
                "  %s (%s): %.4f → %.4f%s",
                $supplier->name,
                $supplier->currency,
                $oldRate,
                $newRate,
                $changed ? ' ← обновится' : ' (без изм.)'
            ));

            if (!$changed || $dryRun) {
                continue;
            }

            // 3. Обновить курс у поставщика
            $supplier->update(['currency_rate' => $newRate]);

            // 4. Пересчитать price_byn в supplier_products
            DB::table('supplier_products')
                ->where('supplier_id', $supplier->id)
                ->where('currency', $supplier->currency)
                ->update([
                    'currency_rate' => $newRate,
                    'price_byn'     => DB::raw("ROUND(price * {$newRate}, 2)"),
                    'updated_at'    => now(),
                ]);

            // 5. Обновить products.price для всех товаров этого поставщика
            $productIds = DB::table('supplier_products')
                ->where('supplier_id', $supplier->id)
                ->whereNotNull('product_id')
                ->pluck('product_id');

            if ($productIds->isNotEmpty()) {
                // Для каждого товара берём минимальную BYN цену из активных supplier_products
                foreach ($productIds as $productId) {
                    $sp = DB::table('supplier_products')
                        ->where('product_id', $productId)
                        ->where('supplier_id', $supplier->id)
                        ->first();

                    if ($sp && $sp->price_byn > 0) {
                        DB::table('products')->where('id', $productId)->update([
                            'price'      => round($sp->price_byn, 2),
                            'updated_at' => now(),
                        ]);
                    }
                }
                $this->info("    ✅ Обновлено {$productIds->count()} товаров {$supplier->name}");
            }
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Изменения не применены.');
        }

        return 0;
    }

    private function fetchRate(string $currency): ?float
    {
        try {
            $resp = Http::timeout(10)->get(sprintf(self::NBRB_API, $currency));
            if ($resp->ok()) {
                $rate = $resp->json('Cur_OfficialRate');
                if ($rate > 0) {
                    return round((float) $rate, 4);
                }
            }
        } catch (\Throwable $e) {
            $this->warn("  НБРБ {$currency}: " . $e->getMessage());
        }
        return null;
    }
}
