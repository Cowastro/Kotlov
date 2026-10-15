<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\ProductPurchasePriceSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOleProPurchasePricesCommand extends Command
{
    protected $signature = 'catalog:import-ole-pro-purchase-prices {--apply : Save confirmed purchase prices only}';

    protected $description = 'Import user-confirmed OLE-PRO costs from order УТ-353 dated 2026-03-13 without modifying retail or 1C.';

    // Original unit prices in BYN excluding 20% VAT, Лист_1, columns L/AV, rows 11–25.
    private const ROWS = [
        'УТ-00002511' => [100.53, 'KOTLOV-022774', 'ОГС-Р-2-Н-НГ-i'],
        'УТ-00002513' => [83.52, 'KOTLOV-022775', 'ОГС-Р-2-НР'],
        'УТ-00000126' => [116.22, 'KOTLOV-022772', 'ОГС-Р-2-НР-i'],
        'УТ-00000127' => [129.67, 'KOTLOV-022773', 'ОГС-Р-3-НР-i'],
        'УТ-00002510' => [116.24, 'KOTLOV-022776', 'ОГС-Р-9-НР'],
        'УТ-00002519' => [92.18, null, 'ОКС-К-3-3-К-НР'],
        'УТ-00001342' => [121.48, null, 'ОКС-К-6-2-НР'],
        'УТ-00000775' => [171.54, 'KOTLOV-022777', 'ОКС-К-3-3-К-НГ-i'],
        'УТ-00000776' => [238.81, 'KOTLOV-022778', 'ОКС-К-3-5-НГ-i'],
        'УТ-00000777' => [328.50, 'KOTLOV-022779', 'ОКС-К-3-7-НГ-i'],
        'УТ-00002515' => [257.21, 'KOTLOV-022780', 'ОКС-КР-2-2-НГ-i'],
        'УТ-00002516' => [275.69, 'KOTLOV-022781', 'ОКС-КР-2-3-НГ-i'],
        'УТ-00002512' => [22.51, null, 'ОКР-2-i'],
        'УТ-00002517' => [22.51, null, 'ОКК-3-i'],
        'УТ-00002521' => [39.05, null, 'ОХР-3-2'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLY purchase prices only' : 'DRY RUN purchase prices only');
        $planned = [];
        foreach (self::ROWS as $code => [$price, $sku, $model]) {
            $items = IntegrationProduct::query()->with(['source.supplier', 'product.brand'])
                ->where('external_code', $code)
                ->whereHas('source', fn ($q) => $q->where('name', '1С СанБизнесГруп'))->get();
            if ($items->count() !== 1) {
                $this->error($code.': unique source row required');

                return self::FAILURE;
            }
            $item = $items->first();
            if (! $item->product_id) {
                $this->line($code.': pending product link; no price imported');

                continue;
            }
            $product = $item->product;
            if ($item->match_status !== 'matched' || ! $product
                || $item->source->partnerName() !== 'ООО «СанБизнесГруп»'
                || ! $item->source->supplier_id
                || $product->brand?->slug !== 'ole-pro'
                || ($sku !== null && $product->sku !== $sku)
                || ! str_contains($this->identity($item->name), $this->identity($model))
                || ! str_contains($this->identity($product->name), $this->identity($model))) {
                $this->error($code.': product or supplier identity guard failed');

                return self::FAILURE;
            }
            $planned[] = [$item, $price];
            $this->line($code.' '.$product->sku.' net='.number_format($price, 2, '.', '').' gross='.number_format(round($price * 1.2, 2), 2, '.', ''));
        }
        if ($apply) {
            DB::transaction(function () use ($planned): void {
                foreach ($planned as [$item, $price]) {
                    // Never save Product, IntegrationProduct or IntegrationSource here.
                    $key = [
                        'product_id' => $item->product_id,
                        'supplier_id' => $item->source->supplier_id,
                        'document_number' => 'УТ-353',
                    ];
                    $source = ProductPurchasePriceSource::query()->where($key)
                        ->whereDate('document_date', '2026-03-13')->first()
                        ?? new ProductPurchasePriceSource($key + ['document_date' => '2026-03-13']);
                    $source->fill([
                        'source_type' => ProductPurchasePriceSource::TYPE_SUPPLIER_INVOICE,
                        'supplier_name' => 'ООО «СанБизнесГруп»',
                        'source_price' => $price, 'currency' => 'BYN', 'currency_rate' => 1,
                        'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
                        'vat_rate' => 20, 'price_byn' => round($price * 1.2, 2),
                        'notes' => 'Заказ клиента УТ-353 от 13.03.2026, файл «заказ Максимова.xls», Лист_1. Пользователь подтвердил использование цен как закупочных 15.10.2026. Цена за единицу без НДС; НДС 20%. Не является ценой обмена 1С.',
                        'is_active' => true,
                    ]);
                    if (! $source->exists) {
                        $source->confirmed_at = now();
                    }
                    if ($source->isDirty()) {
                        $source->save();
                    }
                }
            });
        }
        $this->info('verified_purchase_prices='.count($planned));

        return self::SUCCESS;
    }

    private function identity(string $value): string
    {
        return strtr(mb_strtoupper($value), ['О' => 'O', 'К' => 'K', 'С' => 'C', 'Р' => 'P', 'Н' => 'H', 'Х' => 'X', 'І' => 'I']);
    }
}
