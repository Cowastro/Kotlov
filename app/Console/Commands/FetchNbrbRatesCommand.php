<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Pricing\CurrencyPriceConverter;
use App\Services\Pricing\KarinaPricing;
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

        // Строки в валюте, отличной от валюты карточки поставщика (например,
        // рублёвые цены ecokamin.ru у BYN-поставщика СанБизнесГруп): обновляем
        // только курс и price_byn, розничную цену товара не трогаем.
        $this->refreshForeignRows($rates, $dryRun);

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
                        $retail = round($sp->price_byn, 2);
                        if ($supplier->code === 'karina') {
                            // Procurement and retail remain separate when the daily rate changes.
                            // Missing source RRP must preserve the current retail price.
                            try {
                                $retail = KarinaPricing::quote(
                                    KarinaPricing::retailRubFromRaw($sp->raw), $newRate,
                                )['retail_byn'];
                            } catch (\\Throwable) {
                                $this->warn('KARINA source RRP missing or invalid; retail price preserved.');
                                continue;
                            }
                        }
                        DB::table('products')->where('id', $productId)->update([
                            'price'      => $retail,
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

    /** @param array<string, float> $rates */
    private function refreshForeignRows(array $rates, bool $dryRun): void
    {
        foreach ($rates as $currency => $rate) {
            $rows = DB::table('supplier_products as sp')
                ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->where('sp.currency', $currency)
                ->where('s.currency', '!=', $currency)
                ->where(fn ($query) => $query->whereNull('sp.currency_rate')->orWhereRaw('ABS(sp.currency_rate - ?) > 0.0001', [$rate]));
            $count = (clone $rows)->count();
            if ($count === 0) {
                continue;
            }
            $this->line(sprintf('  Строки %s у поставщиков в другой валюте: %d → курс %.4f', $currency, $count, $rate));
            if ($dryRun) {
                continue;
            }
            foreach ((clone $rows)->pluck('sp.id')->chunk(500) as $ids) {
                DB::table('supplier_products')->whereIn('id', $ids->all())->update([
                    'currency_rate' => $rate,
                    'price_byn' => DB::raw('ROUND(price * '.(float) $rate.', 2)'),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function fetchRate(string $currency): ?float
    {
        try {
            $resp = Http::timeout(10)->get(sprintf(self::NBRB_API, $currency));
            if ($resp->ok()) {
                $rate = (float) $resp->json('Cur_OfficialRate');
                $scale = (int) ($resp->json('Cur_Scale') ?: 1);

                if ($rate > 0 && $scale > 0) {
                    return round($rate / $scale, 6);
                }
            }
        } catch (\Throwable $e) {
            $this->warn("  НБРБ {$currency}: " . $e->getMessage());
        }
        return null;
    }
}
