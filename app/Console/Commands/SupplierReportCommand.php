<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPriceItem;
use Illuminate\Console\Command;

/**
 * Отчёт по несматченным товарам — для ручного маппинга.
 *
 * Использование:
 *   php artisan supplier:report teplov
 *   php artisan supplier:report teplov --export=storage/prices/unmatched_teplov.csv
 */
class SupplierReportCommand extends Command
{
    protected $signature = 'supplier:report
        {supplier : Код поставщика}
        {--import-id= : ID конкретного импорта}
        {--export= : Экспортировать в CSV файл}
        {--limit=50 : Количество строк в выводе}';

    protected $description = 'Показать несматченные товары из последнего прайса';

    public function handle(): int
    {
        $supplier = Supplier::where('code', $this->argument('supplier'))->first();
        if (!$supplier) {
            $this->error("Поставщик не найден");
            return 1;
        }

        $importId = $this->option('import-id');
        $import   = $importId
            ? $supplier->imports()->find($importId)
            : $supplier->imports()->latest()->first();

        if (!$import) {
            $this->error('Импорт не найден');
            return 1;
        }

        $this->info("Поставщик: {$supplier->name} | Импорт #{$import->id}");
        $this->info("Всего: {$import->total_rows} | Сматчено: {$import->matched} | Несматчено: {$import->unmatched}");

        $unmatched = SupplierPriceItem::where('import_id', $import->id)
            ->where('match_status', 'unmatched')
            ->orderBy('name')
            ->limit((int) $this->option('limit'))
            ->get(['article', 'name', 'price', 'in_stock']);

        if ($unmatched->isEmpty()) {
            $this->info('✅ Все товары сматчены!');
            return 0;
        }

        $this->table(
            ['Артикул', 'Название', 'Цена', 'Наличие'],
            $unmatched->map(fn($i) => [
                $i->article,
                mb_substr($i->name, 0, 60),
                number_format($i->price, 2),
                $i->in_stock ? '✓' : '✗',
            ])->toArray()
        );

        // Экспорт в CSV
        $exportPath = $this->option('export');
        if ($exportPath) {
            $all = SupplierPriceItem::where('import_id', $import->id)
                ->where('match_status', 'unmatched')
                ->get(['article', 'name', 'price']);

            $fp = fopen($exportPath, 'w');
            fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM для Excel
            fputcsv($fp, ['supplier_article', 'supplier_name', 'price', 'site_sku', 'notes'], ';');
            foreach ($all as $item) {
                fputcsv($fp, [$item->article, $item->name, $item->price, '', ''], ';');
            }
            fclose($fp);

            $this->info("CSV экспортирован: $exportPath");
            $this->line("Заполните колонку site_sku и загрузите маппинг:");
            $this->line("  php artisan supplier:mapping-import {$supplier->code} $exportPath");
        }

        return 0;
    }
}
