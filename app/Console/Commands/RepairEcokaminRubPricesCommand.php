<?php

namespace App\Console\Commands;

use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairEcokaminRubPricesCommand extends Command
{
    protected $signature = 'repair:ecokamin-rub-prices
        {--apply : Write recalculated prices to the database}';

    protected $description = 'Recalculate EcoKamin BYN prices from stored RUB supplier prices after currency-rate fixes.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $supplier = DB::table('suppliers')->where('code', 'ecokamin')->first();

        if (! $supplier) {
            $this->error('Supplier ecokamin not found.');

            return self::FAILURE;
        }

        $currency = CurrencyPriceConverter::normalizeCurrency($supplier->currency ?? 'RUB');
        $rate = CurrencyPriceConverter::rateFor($currency, $supplier->currency_rate ?? null);

        if ($currency !== 'RUB') {
            $this->warn(sprintf('Supplier ecokamin currency is %s, expected RUB. Continuing with configured rate.', $currency));
        }

        $rows = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplier->id)
            ->whereNotNull('sp.product_id')
            ->where('sp.price', '>', 0)
            ->select([
                'sp.id as supplier_product_id',
                'sp.product_id',
                'sp.price as supplier_price',
                'sp.price_byn',
                'p.name',
                'p.price as product_price',
                'p.price_old',
            ])
            ->orderBy('p.id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No linked EcoKamin supplier products with prices found.');

            return self::SUCCESS;
        }

        $changed = 0;
        $clearedOld = 0;
        $preview = [];
        $now = now();

        foreach ($rows as $row) {
            $newPrice = CurrencyPriceConverter::convertToByn($row->supplier_price, $currency, $rate);

            if ($newPrice === null || $newPrice <= 0) {
                continue;
            }

            $clearOldPrice = $row->price_old !== null
                && (float) $row->price_old > 0
                && (float) $row->price_old <= $newPrice;

            if (abs((float) $row->price_byn - $newPrice) >= 0.01 || abs((float) $row->product_price - $newPrice) >= 0.01 || $clearOldPrice) {
                $changed++;

                if ($clearOldPrice) {
                    $clearedOld++;
                }

                if (count($preview) < 25) {
                    $preview[] = [
                        $row->product_id,
                        mb_substr((string) $row->name, 0, 60),
                        number_format((float) $row->supplier_price, 2, '.', ''),
                        number_format((float) $row->product_price, 2, '.', ''),
                        number_format($newPrice, 2, '.', ''),
                        $clearOldPrice ? number_format((float) $row->price_old, 2, '.', '') . ' -> null' : '-',
                    ];
                }

                if ($apply) {
                    DB::table('supplier_products')->where('id', $row->supplier_product_id)->update([
                        'currency' => $currency,
                        'currency_rate' => $rate,
                        'price_byn' => $newPrice,
                        'updated_at' => $now,
                    ]);

                    DB::table('products')->where('id', $row->product_id)->update([
                        'price' => $newPrice,
                        'price_old' => $clearOldPrice ? null : $row->price_old,
                        'is_sale' => $clearOldPrice ? false : ((float) $row->price_old > $newPrice),
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        $this->info(sprintf(
            '%s ecokamin rows=%d changed=%d old_price_cleared=%d rate=%s',
            $apply ? 'APPLIED' : 'DRY-RUN',
            $rows->count(),
            $changed,
            $clearedOld,
            number_format($rate, 6, '.', '')
        ));

        if ($preview !== []) {
            $this->table(['product_id', 'name', 'rub', 'current_byn', 'new_byn', 'old_price'], $preview);
        }

        return self::SUCCESS;
    }
}
