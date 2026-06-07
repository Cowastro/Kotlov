<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPriceItem;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Загрузить ручной маппинг артикулов поставщика → SKU сайта.
 *
 * CSV формат (заполнить колонку site_sku из отчёта supplier:report):
 *   supplier_article;supplier_name;price;site_sku;notes
 *   RP120/1000;Труба Darco...;12.50;PS-000.123;
 *
 * Использование:
 *   php artisan supplier:mapping-import teplov storage/prices/mapping_teplov.csv
 */
class SupplierMappingImportCommand extends Command
{
    protected $signature = 'supplier:mapping-import
        {supplier : Код поставщика}
        {file : CSV файл с маппингом}
        {--delimiter=; : Разделитель}';

    protected $description = 'Загрузить ручной маппинг артикул → SKU сайта';

    public function handle(): int
    {
        $supplier = Supplier::where('code', $this->argument('supplier'))->first();
        if (!$supplier) {
            $this->error("Поставщик не найден");
            return 1;
        }

        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("Файл не найден: $file");
            return 1;
        }

        $delimiter = $this->option('delimiter');
        $handle    = fopen($file, 'r');
        $headers   = array_map('trim', fgetcsv($handle, 0, $delimiter));

        $idxArticle = array_search('supplier_article', $headers);
        $idxSku     = array_search('site_sku', $headers);

        if ($idxArticle === false || $idxSku === false) {
            $this->error("Нужны колонки: supplier_article, site_sku");
            fclose($handle);
            return 1;
        }

        $added   = 0;
        $skipped = 0;
        $errors  = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $article = trim($row[$idxArticle] ?? '');
            $sku     = trim($row[$idxSku] ?? '');

            if (!$article || !$sku) {
                $skipped++;
                continue;
            }

            $product = Product::where('sku', $sku)->first();
            if (!$product) {
                $this->warn("SKU не найден: $sku (артикул: $article)");
                $errors++;
                continue;
            }

            // Upsert маппинга
            \DB::table('supplier_product_mappings')->updateOrInsert(
                ['supplier_code' => $supplier->code, 'supplier_article' => $article],
                [
                    'product_id'  => $product->id,
                    'product_sku' => $sku,
                    'supplier_name' => $supplier->name,
                    'confidence'  => 'manual',
                    'is_active'   => 1,
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );

            $added++;
        }

        fclose($handle);

        $this->info("✅ Маппинг загружен: +$added | Пропущено: $skipped | Ошибок: $errors");
        $this->line("Теперь повторите матчинг: php artisan supplier:match {$supplier->code} --only-unmatched");

        return 0;
    }
}
