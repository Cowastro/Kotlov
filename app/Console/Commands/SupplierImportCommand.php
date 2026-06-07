<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierPriceImport;
use App\Models\SupplierPriceItem;
use App\Services\PriceList\SupplierAdapter;
use Illuminate\Console\Command;

/**
 * Загрузить прайс-лист поставщика в staging таблицу.
 *
 * Поддерживает CSV и Excel (.xlsx/.xls).
 *
 * Использование:
 *   php artisan supplier:import berezka "prices/berezka.xlsx" --col-article="Арт." --col-name="Наименование" --col-price="Цена с НДС" --col-price-old="РРЦ"
 *   php artisan supplier:import teplov prices/teplov.csv --dry-run
 */
class SupplierImportCommand extends Command
{
    protected $signature = 'supplier:import
        {supplier : Код поставщика}
        {file : Путь к файлу (CSV или XLSX)}
        {--delimiter=; : Разделитель для CSV}
        {--col-article=article : Название колонки с артикулом}
        {--col-name=name : Название колонки с названием}
        {--col-price=price : Название колонки с закупочной ценой}
        {--col-price-old= : Название колонки с РРЦ/рекомендованной ценой (для показа скидки)}
        {--col-stock= : Название колонки с наличием (опционально)}
        {--col-qty= : Название колонки с количеством (опционально)}
        {--dry-run : Показать первые 10 строк без сохранения}
        {--encoding=utf-8 : Кодировка для CSV (utf-8, windows-1251)}';

    protected $description = 'Загрузить прайс-лист поставщика в staging таблицу';

    public function handle(): int
    {
        $supplierCode = $this->argument('supplier');
        $filePath     = $this->argument('file');

        $supplier = Supplier::where('code', $supplierCode)->first();
        if (!$supplier) {
            $this->error("Поставщик '$supplierCode' не найден. Доступные: " .
                Supplier::pluck('code')->implode(', '));
            return 1;
        }

        if (!file_exists($filePath)) {
            $this->error("Файл не найден: $filePath");
            return 1;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $this->info("Поставщик: {$supplier->name} | Файл: " . basename($filePath));

        $colArticle  = $this->option('col-article');
        $colName     = $this->option('col-name');
        $colPrice    = $this->option('col-price');
        $colPriceOld = $this->option('col-price-old');
        $colStock    = $this->option('col-stock');
        $colQty      = $this->option('col-qty');

        // ── Читать строки ──────────────────────────────────────────────────────
        if (in_array($ext, ['xlsx', 'xls', 'ods'])) {
            $rows = $this->readExcel($filePath, $colArticle, $colName, $colPrice);
        } else {
            $rows = $this->readCsv($filePath, $colArticle, $colName, $colPrice,
                $this->option('delimiter'), $this->option('encoding'));
        }

        if (empty($rows)) {
            $this->error('Не удалось прочитать строки. Проверьте --col-article, --col-name, --col-price');
            return 1;
        }

        // ── Dry run ────────────────────────────────────────────────────────────
        if ($this->option('dry-run')) {
            $this->info('--- DRY RUN: первые 10 строк ---');
            $preview = array_slice($rows, 0, 10);
            $this->table(['article', 'name', 'price'], array_map(fn($r) => [
                $r['article'], mb_substr($r['name'], 0, 50), $r['price'],
            ], $preview));
            $this->line('Всего строк: ' . count($rows));
            return 0;
        }

        // ── Сохранить в staging ────────────────────────────────────────────────
        $import = SupplierPriceImport::create([
            'supplier_id' => $supplier->id,
            'filename'    => basename($filePath),
            'status'      => 'processing',
        ]);

        $rate  = $supplier->currency_rate ?: 1.0;
        $batch = [];
        $total = 0;

        $this->output->write('Сохраняю...');
        foreach ($rows as $row) {
            $priceByn    = round((float)$row['price'] * $rate, 2);
            $priceOldByn = null;
            if ($colPriceOld && isset($row['raw'][$colPriceOld])) {
                $parsed = SupplierAdapter::parsePrice($row['raw'][$colPriceOld]);
                if ($parsed > 0) {
                    $priceOldByn = round($parsed * $rate, 2);
                }
            }

            $inStock = true;
            if ($colStock && isset($row[$colStock])) {
                $s = mb_strtolower(trim((string)$row[$colStock]));
                $inStock = !in_array($s, ['0', 'нет', 'no', 'false', '']);
            }

            $batch[] = [
                'supplier_id'  => $supplier->id,
                'import_id'    => $import->id,
                'article'      => $row['article'],
                'name'         => $row['name'],
                'price'        => $row['price'],
                'price_byn'    => $priceByn,
                'price_old'    => $priceOldByn,
                'in_stock'     => $inStock,
                'stock_qty'    => $colQty && isset($row[$colQty]) ? (int)$row[$colQty] : null,
                'raw'          => json_encode($row['raw'] ?? []),
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

        if ($batch) SupplierPriceItem::insert($batch);

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

    // ── Excel ──────────────────────────────────────────────────────────────────
    private function readExcel(string $file, string $colArticle, string $colName, string $colPrice): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $rawRows     = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        // Найти строку заголовков (ищем колонку артикула)
        $headerIdx = null;
        $headers   = [];
        foreach ($rawRows as $i => $row) {
            foreach ($row as $cell) {
                if (trim((string)$cell) === $colArticle) {
                    $headerIdx = $i;
                    $headers   = $row;
                    break 2;
                }
            }
        }

        if ($headerIdx === null) {
            throw new \RuntimeException("Колонка '$colArticle' не найдена в файле");
        }

        $this->line('Заголовки: ' . implode(', ', array_filter($headers)));

        $result = [];
        for ($i = $headerIdx + 1; $i < count($rawRows); $i++) {
            $row = $rawRows[$i];
            $mapped = [];
            foreach ($headers as $j => $h) {
                if ($h !== null && trim((string)$h) !== '') {
                    $mapped[trim((string)$h)] = $row[$j] ?? null;
                }
            }

            $article = trim((string)($mapped[$colArticle] ?? ''));
            $name    = trim((string)($mapped[$colName] ?? ''));
            $price   = SupplierAdapter::parsePrice($mapped[$colPrice] ?? null);

            if (!$article || !$price) continue;

            $result[] = ['article' => $article, 'name' => $name, 'price' => $price, 'raw' => $mapped];
        }

        return $result;
    }

    // ── CSV ────────────────────────────────────────────────────────────────────
    private function readCsv(string $file, string $colArticle, string $colName, string $colPrice,
        string $delimiter, string $encoding): array
    {
        $handle = fopen($file, 'r');
        if ($encoding === 'windows-1251') {
            stream_filter_append($handle, 'convert.iconv.windows-1251/utf-8');
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        $headers    = array_map(fn($h) => trim((string)$h), $rawHeaders ?: []);

        $idxArt   = array_search($colArticle, $headers);
        $idxName  = array_search($colName, $headers);
        $idxPrice = array_search($colPrice, $headers);

        if ($idxArt === false || $idxName === false || $idxPrice === false) {
            fclose($handle);
            $this->error("Колонки не найдены. Доступные: " . implode(', ', $headers));
            return [];
        }

        $result = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $article = trim($row[$idxArt] ?? '');
            $name    = trim($row[$idxName] ?? '');
            $price   = SupplierAdapter::parsePrice($row[$idxPrice] ?? null);

            if (!$article || !$price) continue;

            $mapped = array_combine($headers, array_pad($row, count($headers), null));
            $result[] = ['article' => $article, 'name' => $name, 'price' => $price, 'raw' => $mapped];
        }
        fclose($handle);
        return $result;
    }
}
