<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProductMapping;
use Illuminate\Console\Command;

class ImportSupplierMapping extends Command
{
    protected $signature = 'supplier-mapping:import
                            {file : Path to the checked mapping CSV file}
                            {--supplier=berezka : Supplier adapter code}
                            {--dry-run : Preview without writing (default mode)}
                            {--apply : Write mappings to database}';

    protected $description = 'Import a manually-reviewed supplier mapping CSV into supplier_product_mappings table.';

    public function handle(): int
    {
        $filePath = $this->resolveFilePath($this->argument('file'));
        $supplier = $this->option('supplier');
        $apply    = (bool) $this->option('apply');

        $this->line('');
        $this->line("  <fg=cyan;options=bold>Импорт маппинга поставщика: {$supplier}</>");
        $this->line("  Файл: <fg=yellow>{$filePath}</>");
        $this->line("  Режим: " . ($apply
            ? '<fg=red;options=bold>APPLY — записи будут сохранены в базу</>'
            : '<fg=yellow;options=bold>DRY-RUN (база не изменяется)</>'));
        $this->line('');

        if ($apply && ! $this->confirm('Записать маппинг в базу данных?', false)) {
            $this->line('  <fg=yellow>Отменено.</>');
            return self::SUCCESS;
        }

        if (! file_exists($filePath)) {
            $this->error("Файл не найден: {$filePath}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($filePath);
        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $details = [];

        foreach ($rows as $row) {
            $stats['total']++;

            $supplierArticle = trim($row['supplier_article'] ?? '');
            $productId       = (int) ($row['matched_product_id'] ?? 0);
            $status          = trim($row['status'] ?? '');

            // Skip rules
            if ($supplierArticle === '') {
                $stats['skipped']++;
                $details[] = ['—', $supplierArticle, 'skipped', 'Пустой артикул'];
                continue;
            }
            if ($productId <= 0) {
                $stats['skipped']++;
                $details[] = [$supplierArticle, '—', 'skipped', 'Нет matched_product_id'];
                continue;
            }
            if ($status === 'not_found') {
                $stats['skipped']++;
                $details[] = [$supplierArticle, $productId, 'skipped', 'Статус not_found'];
                continue;
            }

            // Verify product exists
            $product = Product::find($productId);
            if (! $product) {
                $stats['errors']++;
                $details[] = [$supplierArticle, $productId, 'error', "Товар #{$productId} не найден в products"];
                continue;
            }

            $mappingData = [
                'supplier_code'    => $supplier,
                'supplier_article' => $supplierArticle,
                'product_id'       => $productId,
                'product_sku'      => $product->sku,
                'supplier_name'    => trim($row['supplier_name'] ?? ''),
                'confidence'       => trim($row['confidence'] ?? 'manual'),
                'is_active'        => true,
                'notes'            => trim($row['comment'] ?? ''),
            ];

            $existing = SupplierProductMapping::where('supplier_code', $supplier)
                ->where('supplier_article', $supplierArticle)
                ->first();

            if ($existing) {
                if ($apply) {
                    $existing->update($mappingData);
                }
                $stats['updated']++;
                $details[] = [$supplierArticle, $productId, $apply ? 'updated' : 'will_update', $product->sku . ' — ' . mb_substr($product->name, 0, 50)];
            } else {
                if ($apply) {
                    SupplierProductMapping::create($mappingData);
                }
                $stats['created']++;
                $details[] = [$supplierArticle, $productId, $apply ? 'created' : 'will_create', $product->sku . ' — ' . mb_substr($product->name, 0, 50)];
            }
        }

        if (! empty($details)) {
            $this->table(
                ['Артикул поставщика', 'Product ID', 'Статус', 'Товар'],
                $details
            );
        }

        $this->line('');
        $this->line('  <fg=cyan;options=bold>Итог:</>');
        $this->line("  Всего строк:  {$stats['total']}");
        $this->line("  Создано:      <fg=green>{$stats['created']}</>");
        $this->line("  Обновлено:    <fg=green>{$stats['updated']}</>");
        $this->line("  Пропущено:    <fg=yellow>{$stats['skipped']}</>");
        $this->line("  Ошибки:       <fg=red>{$stats['errors']}</>");
        $this->line('');

        if (! $apply && ($stats['created'] + $stats['updated']) > 0) {
            $this->line('  <fg=yellow>Dry-run. Для записи используйте --apply.</>');
            $this->line('');
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function readCsv(string $filePath): array
    {
        $rows    = [];
        $handle  = fopen($filePath, 'r');
        $headers = null;

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if ($headers === null) {
                $headers = $line;
                continue;
            }
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }

        fclose($handle);
        return $rows;
    }

    private function resolveFilePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Z]:/i', $path)) {
            return $path;
        }
        return base_path($path);
    }
}
