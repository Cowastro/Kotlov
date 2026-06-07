<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPriceImport;
use App\Models\SupplierPriceItem;
use Illuminate\Console\Command;

/**
 * Загрузить прайс-лист поставщика в staging таблицу.
 *
 * Использование:
 *   php artisan supplier:import teplov storage/prices/teplov.csv
 *   php artisan supplier:import teplov storage/prices/teplov.csv --dry-run
 *
 * Формат CSV (первая строка — заголовки):
 *   article;name;price;in_stock
 *   или любой другой — настраивается через --col-* опции
 */
class SupplierImportCommand extends Command
{
    protected $signature = 'supplier:import
        {supplier : Код поставщика (teplov, darco, ferrum...)}
        {file : Путь к CSV файлу}
        {--delimiter=; : Разделитель колонок}
        {--col-article=article : Название колонки с артикулом}
        {--col-name=name : Название колонки с названием}
        {--col-price=price : Название колонки с ценой}
        {--col-stock=in_stock : Название колонки с наличием}
        {--col-qty=qty : Название колонки с количеством (опционально)}
        {--dry-run : Только показать первые 5 строк без сохранения}
        {--encoding=utf-8 : Кодировка файла (utf-8, windows-1251)}';

    protected $description = 'Загрузить прайс-лист поставщика в staging таблицу';

    public function handle(): int
    {
        $supplierCode = $this->argument('supplier');
        $filePath     = $this->argument('file');

        // Найти поставщика
        $supplier = Supplier::where('code', $supplierCode)->first();
        if (!$supplier) {
            $this->error("Поставщик '$supplierCode' не найден. Доступные: " .
                Supplier::pluck('code')->implode(', '));
            return 1;
        }

        // Проверить файл
        if (!file_exists($filePath)) {
            $this->error("Файл не найден: $filePath");
            return 1;
        }

        $delimiter = $this->option('delimiter');
        $encoding  = $this->option('encoding');
        $colArticle = $this->option('col-article');
        $colName    = $this->option('col-name');
        $colPrice   = $this->option('col-price');
        $colStock   = $this->option('col-stock');
        $colQty     = $this->option('col-qty');

        $this->info("Поставщик: {$supplier->name}");
        $this->info("Файл: $filePath");

        // Читать CSV
        $handle = fopen($filePath, 'r');
        if ($encoding === 'windows-1251') {
            stream_filter_append($handle, 'convert.iconv.windows-1251/utf-8');
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            $this->error('Не удалось прочитать заголовки файла');
            fclose($handle);
            return 1;
        }

        // Нормализовать заголовки
        $headers = array_map(fn($h) => mb_strtolower(trim($h)), $headers);
        $this->line('Колонки: ' . implode(', ', $headers));

        // Маппинг колонок
        $idxArticle = array_search(mb_strtolower($colArticle), $headers);
        $idxName    = array_search(mb_strtolower($colName), $headers);
        $idxPrice   = array_search(mb_strtolower($colPrice), $headers);
        $idxStock   = array_search(mb_strtolower($colStock), $headers);
        $idxQty     = $colQty ? array_search(mb_strtolower($colQty), $headers) : false;

        if ($idxArticle === false || $idxName === false || $idxPrice === false) {
            $this->error("Не найдены обязательные колонки. Проверьте --col-article, --col-name, --col-price");
            $this->line("Доступные колонки: " . implode(', ', $headers));
            fclose($handle);
            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info('--- DRY RUN: первые 5 строк ---');
            $i = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $i < 5) {
                $this->line(sprintf(
                    'article=%s | name=%s | price=%s | stock=%s',
                    $row[$idxArticle] ?? '?',
                    $row[$idxName] ?? '?',
                    $row[$idxPrice] ?? '?',
                    $idxStock !== false ? ($row[$idxStock] ?? '?') : 'n/a'
                ));
                $i++;
            }
            fclose($handle);
            return 0;
        }

        // Создать запись импорта
        $import = SupplierPriceImport::create([
            'supplier_id' => $supplier->id,
            'filename'    => basename($filePath),
            'status'      => 'processing',
        ]);

        $rate  = $supplier->currency_rate ?: 1.0;
        $batch = [];
        $total = 0;

        $this->output->write('Читаю строки...');
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $article = trim($row[$idxArticle] ?? '');
            $name    = trim($row[$idxName] ?? '');
            $price   = (float) str_replace([' ', ',', "\xc2\xa0"], ['', '.', ''], $row[$idxPrice] ?? '0');

            if (!$article || $price <= 0) {
                continue;
            }

            $inStock  = true;
            if ($idxStock !== false) {
                $s = mb_strtolower(trim($row[$idxStock] ?? ''));
                $inStock = !in_array($s, ['0', 'нет', 'no', 'false', '']);
            }

            $priceByn = round($price * $rate, 2);

            $batch[] = [
                'supplier_id'  => $supplier->id,
                'import_id'    => $import->id,
                'article'      => $article,
                'name'         => $name,
                'price'        => $price,
                'price_byn'    => $priceByn,
                'in_stock'     => $inStock,
                'stock_qty'    => $idxQty !== false ? ((int) ($row[$idxQty] ?? 0)) : null,
                'raw'          => json_encode(array_combine($headers, array_pad($row, count($headers), null))),
                'match_status' => 'unmatched',
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            $total++;

            if (count($batch) >= 500) {
                SupplierPriceItem::insert($batch);
                $batch = [];
                $this->output->write('.');
            }
        }

        if ($batch) {
            SupplierPriceItem::insert($batch);
        }
        fclose($handle);

        $import->update([
            'total_rows'  => $total,
            'status'      => 'pending',
            'imported_at' => now(),
        ]);

        $this->newLine();
        $this->info("✅ Загружено $total строк. Import ID: {$import->id}");
        $this->line("Следующий шаг: php artisan supplier:match $supplierCode");

        return 0;
    }
}
