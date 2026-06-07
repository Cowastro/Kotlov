<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPriceItem;
use App\Models\SupplierProductMapping;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Сматчить товары из последнего прайса с товарами сайта.
 *
 * Использование:
 *   php artisan supplier:match teplov
 *   php artisan supplier:match teplov --import-id=5
 *
 * Методы матчинга (по приоритету):
 *   1. mapping  — по таблице supplier_product_mappings (article → product_id)
 *   2. sku      — по products.sku == supplier_article
 *   3. name     — нечёткое совпадение по названию (только похожесть > 80%)
 */
class SupplierMatchCommand extends Command
{
    protected $signature = 'supplier:match
        {supplier : Код поставщика}
        {--import-id= : ID конкретного импорта (по умолчанию — последний)}
        {--only-unmatched : Матчить только ещё не сматченные}';

    protected $description = 'Сматчить товары прайса с товарами сайта';

    public function handle(): int
    {
        $supplierCode = $this->argument('supplier');
        $supplier     = Supplier::where('code', $supplierCode)->first();

        if (!$supplier) {
            $this->error("Поставщик '$supplierCode' не найден");
            return 1;
        }

        // Найти импорт
        $importId = $this->option('import-id');
        $import   = $importId
            ? $supplier->imports()->find($importId)
            : $supplier->imports()->latest()->first();

        if (!$import) {
            $this->error('Импорт не найден. Сначала выполните: php artisan supplier:import ' . $supplierCode . ' <file>');
            return 1;
        }

        $this->info("Поставщик: {$supplier->name}");
        $this->info("Импорт #{$import->id} от {$import->imported_at} ({$import->total_rows} строк)");

        // Загрузить items
        $query = SupplierPriceItem::where('import_id', $import->id);
        if ($this->option('only-unmatched')) {
            $query->where('match_status', 'unmatched');
        }
        $items = $query->get();

        // Загрузить маппинги для поставщика
        $mappings = \DB::table('supplier_product_mappings')
            ->where('supplier_code', $supplierCode)
            ->where('is_active', 1)
            ->pluck('product_id', 'supplier_article'); // article => product_id

        // Загрузить SKU товаров
        $skuMap = Product::whereNotNull('sku')
            ->pluck('id', 'sku'); // sku => id

        $stats = ['mapping' => 0, 'sku' => 0, 'name' => 0, 'unmatched' => 0];

        $this->output->progressStart($items->count());

        foreach ($items as $item) {
            $productId = null;
            $method    = null;

            // 1. По маппингу
            if (isset($mappings[$item->article])) {
                $productId = $mappings[$item->article];
                $method    = 'mapping';
            }

            // 2. По SKU (article == sku сайта)
            if (!$productId && isset($skuMap[$item->article])) {
                $productId = $skuMap[$item->article];
                $method    = 'sku';
            }

            // 3. Нечёткое по названию
            if (!$productId) {
                $productId = $this->fuzzyMatchByName($item->name);
                if ($productId) {
                    $method = 'name';
                }
            }

            if ($productId) {
                $product = Product::find($productId);
                $item->update([
                    'product_id'   => $productId,
                    'product_sku'  => $product?->sku,
                    'match_status' => 'matched',
                    'match_method' => $method,
                ]);
                $stats[$method]++;
            } else {
                $item->update(['match_status' => 'unmatched']);
                $stats['unmatched']++;
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        $matched = $stats['mapping'] + $stats['sku'] + $stats['name'];
        $import->update([
            'matched'   => $matched,
            'unmatched' => $stats['unmatched'],
        ]);

        $this->info("\n✅ Результат матчинга:");
        $this->table(['Метод', 'Кол-во'], [
            ['Маппинг (mapping)',     $stats['mapping']],
            ['По SKU',                $stats['sku']],
            ['По названию (fuzzy)',   $stats['name']],
            ['Не сматчено',           $stats['unmatched']],
        ]);

        if ($stats['unmatched'] > 0) {
            $this->line("Несматченные: php artisan supplier:report $supplierCode");
            $this->line("После ручного маппинга: php artisan supplier:match $supplierCode --only-unmatched");
        }

        $this->line("Применить цены: php artisan prices:update $supplierCode");

        return 0;
    }

    private function fuzzyMatchByName(string $supplierName): ?int
    {
        if (strlen($supplierName) < 5) {
            return null;
        }

        // Берём первые значимые слова (без артикулов/цифр в конце)
        $cleanName = preg_replace('/\s+\S*\d+\S*$/', '', $supplierName);
        $cleanName = trim($cleanName);

        if (strlen($cleanName) < 5) {
            return null;
        }

        $product = Product::where('name', 'LIKE', '%' . $cleanName . '%')
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return null;
        }

        // Проверяем схожесть названий
        similar_text(
            mb_strtolower($supplierName),
            mb_strtolower($product->name),
            $percent
        );

        return $percent >= 75 ? $product->id : null;
    }
}
