<?php

namespace App\Console\Commands;

use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RepairRubSupplierPricesCommand extends Command
{
    protected $signature = 'repair:rub-supplier-prices
        {--apply : Write recalculated prices to the database}
        {--supplier= : Limit repair to one supplier code}
        {--rate= : Explicit RUB to BYN rate; defaults to the NBRB rate for one RUB}';

    protected $description = 'Recalculate BYN prices for all linked supplier products priced in RUB.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rate = $this->resolveRubRate();
        $supplierCode = trim((string) $this->option('supplier'));
        $now = now();

        $suppliers = DB::table('suppliers')
            ->where(function ($query): void {
                $query->whereRaw('UPPER(currency) = ?', ['RUB'])
                    ->orWhereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('supplier_products as sp')
                            ->whereColumn('sp.supplier_id', 'suppliers.id')
                            ->whereRaw('UPPER(sp.currency) = ?', ['RUB']);
                    });
            })
            ->when($supplierCode !== '', fn ($query) => $query->where('code', $supplierCode))
            ->orderBy('code')
            ->get();

        if ($suppliers->isEmpty()) {
            $this->warn($supplierCode !== '' ? "No RUB supplier found for code {$supplierCode}." : 'No RUB suppliers found.');

            return self::SUCCESS;
        }

        $totalRows = 0;
        $totalChangedRows = 0;
        $totalProducts = 0;
        $preview = [];

        foreach ($suppliers as $supplier) {
            $supplierCurrency = CurrencyPriceConverter::normalizeCurrency($supplier->currency ?? null);

            $rows = DB::table('supplier_products as sp')
                ->join('products as p', 'p.id', '=', 'sp.product_id')
                ->where('sp.supplier_id', $supplier->id)
                ->whereNotNull('sp.product_id')
                ->where('sp.price', '>', 0)
                ->where(function ($query) use ($supplierCurrency): void {
                    $query->whereRaw('UPPER(sp.currency) = ?', ['RUB']);

                    if ($supplierCurrency === 'RUB') {
                        $query->orWhereNull('sp.currency')
                            ->orWhere('sp.currency', '');
                    }
                })
                ->select([
                    'sp.id as supplier_product_id',
                    'sp.product_id',
                    'sp.price as supplier_price',
                    'sp.currency as supplier_product_currency',
                    'sp.price_byn',
                    'p.name',
                    'p.price as product_price',
                    'p.price_old',
                ])
                ->orderBy('p.id')
                ->get();

            if ($rows->isEmpty()) {
                $this->line(sprintf('%s: no linked RUB rows.', $supplier->code));
                continue;
            }

            $changedRows = 0;
            $productIds = [];

            if ($apply && $supplierCurrency === 'RUB') {
                DB::table('suppliers')->where('id', $supplier->id)->update([
                    'currency_rate' => $rate,
                    'updated_at' => $now,
                ]);
            }

            foreach ($rows as $row) {
                $newPriceByn = CurrencyPriceConverter::convertToByn($row->supplier_price, 'RUB', $rate);

                if ($newPriceByn === null || $newPriceByn <= 0) {
                    continue;
                }

                $productIds[(int) $row->product_id] = true;
                $rowChanged = abs((float) $row->price_byn - $newPriceByn) >= 0.01;

                if ($rowChanged) {
                    $changedRows++;
                }

                if (count($preview) < 30 && ($rowChanged || abs((float) $row->product_price - $newPriceByn) >= 0.01)) {
                    $preview[] = [
                        $supplier->code,
                        $row->product_id,
                        mb_substr((string) $row->name, 0, 52),
                        number_format((float) $row->supplier_price, 2, '.', ''),
                        number_format((float) $row->product_price, 2, '.', ''),
                        number_format($newPriceByn, 2, '.', ''),
                    ];
                }

                if ($apply) {
                    DB::table('supplier_products')->where('id', $row->supplier_product_id)->update([
                        'currency' => 'RUB',
                        'currency_rate' => $rate,
                        'price_byn' => $newPriceByn,
                        'updated_at' => $now,
                    ]);
                }
            }

            $updatedProducts = $this->refreshProductPrices(array_keys($productIds), $apply, $now);

            $totalRows += $rows->count();
            $totalChangedRows += $changedRows;
            $totalProducts += $updatedProducts;

            $this->line(sprintf(
                '%s %s rows=%d changed_rows=%d refreshed_products=%d',
                $apply ? 'APPLIED' : 'DRY-RUN',
                $supplier->code,
                $rows->count(),
                $changedRows,
                $updatedProducts
            ));
        }

        $this->info(sprintf(
            '%s RUB suppliers=%d rows=%d changed_rows=%d refreshed_products=%d rate=%s',
            $apply ? 'APPLIED' : 'DRY-RUN',
            $suppliers->count(),
            $totalRows,
            $totalChangedRows,
            $totalProducts,
            number_format($rate, 6, '.', '')
        ));

        if ($preview !== []) {
            $this->table(['supplier', 'product_id', 'name', 'rub', 'current_byn', 'rub_to_byn'], $preview);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int,int> $productIds
     */
    private function refreshProductPrices(array $productIds, bool $apply, mixed $now): int
    {
        $updated = 0;

        foreach (array_unique($productIds) as $productId) {
            $product = DB::table('products')->where('id', $productId)->first(['id', 'price', 'price_old']);

            if (! $product) {
                continue;
            }

            $bestPrice = DB::table('supplier_products')
                ->where('product_id', $productId)
                ->where('price_byn', '>', 0)
                ->min('price_byn');

            if ($bestPrice === null) {
                continue;
            }

            $bestPrice = round((float) $bestPrice, 2);
            $clearOldPrice = $product->price_old !== null
                && (float) $product->price_old > 0
                && (float) $product->price_old <= $bestPrice;

            if (abs((float) $product->price - $bestPrice) < 0.01 && ! $clearOldPrice) {
                continue;
            }

            $updated++;

            if ($apply) {
                DB::table('products')->where('id', $productId)->update([
                    'price' => $bestPrice,
                    'price_old' => $clearOldPrice ? null : $product->price_old,
                    'is_sale' => $clearOldPrice ? false : ((float) $product->price_old > $bestPrice),
                    'updated_at' => $now,
                ]);
            }
        }

        return $updated;
    }

    private function resolveRubRate(): float
    {
        $explicitRate = trim((string) $this->option('rate'));

        if ($explicitRate !== '') {
            return CurrencyPriceConverter::rateFor('RUB', str_replace(',', '.', $explicitRate));
        }

        $resp = Http::timeout(10)->get('https://api.nbrb.by/exrates/rates/RUB?parammode=2');

        if (! $resp->ok()) {
            throw new \RuntimeException('Unable to fetch NBRB RUB rate.');
        }

        $officialRate = (float) $resp->json('Cur_OfficialRate');
        $scale = (int) ($resp->json('Cur_Scale') ?: 1);

        if ($officialRate <= 0 || $scale <= 0) {
            throw new \RuntimeException('Invalid NBRB RUB rate response.');
        }

        return CurrencyPriceConverter::rateFor('RUB', round($officialRate / $scale, 6));
    }
}
