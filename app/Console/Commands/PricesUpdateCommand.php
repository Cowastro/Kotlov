<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPriceItem;
use App\Models\Product;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Применить цены из последнего прайса к товарам сайта.
 *
 * Использование:
 *   php artisan prices:update teplov
 *   php artisan prices:update teplov --dry-run
 *   php artisan prices:update teplov --import-id=5
 *   php artisan prices:update --all
 */
class PricesUpdateCommand extends Command
{
    protected $signature = 'prices:update
        {supplier? : Код поставщика}
        {--all : Обновить все активные поставщики}
        {--import-id= : ID конкретного импорта}
        {--dry-run : Показать что изменится без применения}
        {--force : Обновлять даже если цена не изменилась}';

    protected $description = 'Применить цены из прайс-листа к товарам сайта';

    public function handle(): int
    {
        if ($this->option('all')) {
            $suppliers = Supplier::where('is_active', true)->get();
        } else {
            $code = $this->argument('supplier');
            if (!$code) {
                $this->error('Укажите поставщика или --all');
                return 1;
            }
            $supplier = Supplier::where('code', $code)->first();
            if (!$supplier) {
                $this->error("Поставщик '$code' не найден");
                return 1;
            }
            $suppliers = collect([$supplier]);
        }

        foreach ($suppliers as $supplier) {
            $this->processSupplier($supplier);
        }

        return 0;
    }

    private function processSupplier(Supplier $supplier): void
    {
        $importId = $this->option('import-id');
        $import   = $importId
            ? $supplier->imports()->find($importId)
            : $supplier->imports()->where('status', '!=', 'failed')->latest()->first();

        if (!$import) {
            $this->warn("⚠️  {$supplier->name}: нет импортов");
            return;
        }

        $supplierCurrency = CurrencyPriceConverter::normalizeCurrency($supplier->currency);
        $supplierRate     = CurrencyPriceConverter::rateFor($supplierCurrency, $supplier->currency_rate);

        $this->info("\n📦 Поставщик: {$supplier->name} (импорт #{$import->id}) | валюта: $supplierCurrency, курс к BYN: $supplierRate");

        $items = SupplierPriceItem::where('import_id', $import->id)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->get();

        if ($items->isEmpty()) {
            $this->warn('  Нет сматченных товаров. Запустите supplier:match сначала.');
            return;
        }

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->line('  [DRY RUN] Изменения не применяются');
        }

        $bar = $this->output->createProgressBar($items->count());

        foreach ($items as $item) {
            try {
                $product = Product::find($item->product_id);
                if (!$product) {
                    $stats['errors']++;
                    continue;
                }

                // Валюта/курс зафиксированы на строке при импорте; для старых строк — текущие настройки поставщика.
                $itemCurrency = CurrencyPriceConverter::normalizeCurrency($item->currency ?: $supplierCurrency);
                $itemRate     = (float) ($item->currency_rate ?: 0) > 0 ? (float) $item->currency_rate : $supplierRate;

                $newPrice = $item->price_byn ?? CurrencyPriceConverter::convertToByn($item->price, $itemCurrency, $itemRate);

                // Пропустить если цена не изменилась
                if (!$this->option('force') && abs($product->price - $newPrice) < 0.01) {
                    $stats['skipped']++;
                    $bar->advance();
                    continue;
                }

                // price_old: РРЦ * 1.15 (маркетинговая "старая цена"), иначе null
                $priceOld = null;
                if ($item->price_old > 0) {
                    $priceOld = round($item->price_old * 1.15, 2);
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        "\n  %s: %.2f %s × %s = %.2f BYN | на сайте %.2f → %.2f BYN%s",
                        $product->name,
                        $item->price,
                        $itemCurrency,
                        $itemRate,
                        $newPrice,
                        $product->price,
                        $newPrice,
                        $priceOld ? " (РРЦ: {$priceOld})" : ''
                    ));
                } else {
                    DB::table('products')->where('id', $product->id)->update([
                        'price_old'  => $priceOld,
                        'price'      => $newPrice,
                        'in_stock'   => $item->in_stock,
                        'stock_qty'  => $item->stock_qty,
                        'updated_at' => now(),
                    ]);
                }

                $stats['updated']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("\n  Ошибка для item #{$item->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();

        if (!$dryRun) {
            $import->update([
                'updated' => $stats['updated'],
                'skipped' => $stats['skipped'],
                'status'  => 'done',
            ]);
        }

        $this->newLine();
        $this->info("  ✅ Обновлено: {$stats['updated']} | Без изменений: {$stats['skipped']} | Ошибок: {$stats['errors']}");
    }
}
