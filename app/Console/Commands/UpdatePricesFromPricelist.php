<?php

namespace App\Console\Commands;

use App\Models\SupplierProductMapping;
use App\Services\PriceList\SupplierAdapter;
use Illuminate\Console\Command;

class UpdatePricesFromPricelist extends Command
{
    protected $signature = 'prices:update-from-pricelist
                            {file : Path to the Excel price list file}
                            {--supplier=berezka : Supplier adapter code}
                            {--dry-run : Preview without modifying the database (default mode)}
                            {--apply : Actually apply price updates to the database}';

    protected $description = 'Update product prices via supplier_product_mappings table. Use --apply to persist changes.';

    public function handle(): int
    {
        $filePath = $this->resolveFilePath($this->argument('file'));
        $supplier = $this->option('supplier');
        $apply    = (bool) $this->option('apply');

        $this->line('');
        $this->line('  <fg=cyan;options=bold>Обновление цен из прайс-листа</>');
        $this->line('  ─────────────────────────────────────────────────');
        $this->line("  Файл:       <fg=yellow>{$filePath}</>");
        $this->line("  Поставщик:  <fg=yellow>{$supplier}</>");
        $this->line("  Режим:      " . ($apply
            ? '<fg=red;options=bold>APPLY — цены будут записаны в базу</>'
            : '<fg=yellow;options=bold>DRY-RUN (база не изменяется)</>'));
        $this->line('  ─────────────────────────────────────────────────');
        $this->line('');

        if ($apply && ! $this->confirm('Обновить цены в базе данных?', false)) {
            $this->line('  <fg=yellow>Отменено.</>');
            return self::SUCCESS;
        }

        $config = SupplierAdapter::getConfig($supplier);

        try {
            $rows = SupplierAdapter::readExcel($filePath, $config);
        } catch (\Throwable $e) {
            $this->error('Ошибка чтения файла: ' . $e->getMessage());
            return self::FAILURE;
        }

        $stats = [
            'total'         => 0,
            'updated'       => 0,
            'unchanged'     => 0,
            'not_mapped'    => 0,
            'invalid_price' => 0,
            'skipped'       => 0,
        ];

        $csvRows  = [];
        $seenSkus = [];

        foreach ($rows as $row) {
            $article      = trim((string) ($row[$config['sku_col']] ?? ''));
            $supplierName = trim((string) ($row[$config['name_col']] ?? ''));
            $rrc          = SupplierAdapter::parsePrice($row[$config['rrc_col']] ?? null);
            $opt          = SupplierAdapter::parsePrice($row[$config['opt_col']] ?? null);

            if ($article === '') {
                $stats['skipped']++;
                continue;
            }

            $stats['total']++;

            // Validate price
            if ($rrc === null) {
                $stats['invalid_price']++;
                $csvRows[] = $this->makeRow($supplier, $article, $supplierName, null, null, null, null, $rrc, $opt, 'invalid_price', 'Некорректный РРЦ');
                continue;
            }

            // Find via mapping table — never touch products.sku
            $mapping = SupplierProductMapping::query()
                ->where('supplier_code', $supplier)
                ->where('supplier_article', $article)
                ->where('is_active', true)
                ->with('product')
                ->first();

            if (! $mapping || ! $mapping->product) {
                $stats['not_mapped']++;
                $csvRows[] = $this->makeRow($supplier, $article, $supplierName, null, null, null, null, $rrc, $opt, 'not_mapped', 'Нет маппинга в supplier_product_mappings');
                continue;
            }

            $product  = $mapping->product;
            $oldPrice = (float) $product->price;

            if (abs($oldPrice - $rrc) < 0.001) {
                $stats['unchanged']++;
                $csvRows[] = $this->makeRow($supplier, $article, $supplierName, $product->id, $product->sku, $product->name, $oldPrice, $rrc, $opt, 'unchanged', 'Цена не изменилась');
                continue;
            }

            if ($apply) {
                $product->price = $rrc;
                $product->save();
            }

            $stats['updated']++;
            $csvRows[] = $this->makeRow(
                $supplier, $article, $supplierName, $product->id, $product->sku, $product->name,
                $oldPrice, $rrc, $opt,
                $apply ? 'updated' : 'would_update',
                $apply ? 'Обновлено' : 'Будет обновлено'
            );
        }

        // Summary
        $this->line('  <fg=cyan;options=bold>Итоговый отчёт</>');
        $this->line('  ─────────────────────────────────────────────────');

        $summary = [
            ['Всего строк с артикулом',  $stats['total'],         'white'],
            [$apply ? 'Обновлено цен' : 'Будет обновлено', $stats['updated'], 'green'],
            ['Без изменений',             $stats['unchanged'],     'white'],
            ['Нет маппинга',              $stats['not_mapped'],    'yellow'],
            ['Некорректная цена',         $stats['invalid_price'], 'red'],
            ['Пропущено (пустой арт.)',   $stats['skipped'],       'white'],
        ];

        foreach ($summary as [$label, $value, $color]) {
            $this->line(sprintf("  %-42s <fg=%s>%d</>", $label . ':', $color, $value));
        }
        $this->line('  ─────────────────────────────────────────────────');

        // Show changed/interesting rows
        $interesting = array_filter($csvRows, fn($r) => in_array($r['status'], ['updated', 'would_update', 'not_mapped', 'invalid_price']));
        if (! empty($interesting)) {
            $this->line('');
            $this->line('  <fg=cyan;options=bold>Детали:</>');
            $this->table(
                ['Артикул', 'Название поставщика', 'Нов. цена', 'Стар. цена', 'Статус'],
                array_map(fn($r) => [
                    $r['supplier_article'],
                    mb_substr($r['supplier_name'], 0, 45),
                    $r['new_price'] !== '' ? $r['new_price'] : '—',
                    $r['old_price'] !== '' ? $r['old_price'] : '—',
                    $r['status'],
                ], array_values($interesting))
            );
        }

        $csvPath = $this->saveCsvReport($supplier, $csvRows);
        $this->line('');
        $this->line("  CSV-отчёт: <fg=cyan>{$csvPath}</>");
        $this->line('');

        if (! $apply && $stats['updated'] > 0) {
            $this->line('  <fg=yellow>Это dry-run. Для применения добавьте --apply.</>');
            $this->line('');
        }

        if ($stats['not_mapped'] > 0 && $stats['updated'] === 0) {
            $this->line('  <fg=yellow>Подсказка: нет маппинга для артикулов из прайса.</>');
            $this->line('  Сначала запустите: <fg=yellow>php artisan supplier-mapping:analyze</> и заполните CSV-маппинг,');
            $this->line('  затем: <fg=yellow>php artisan supplier-mapping:import ... --apply</>');
            $this->line('');
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function makeRow(
        string $supplierCode,
        string $supplierArticle,
        string $supplierName,
        ?int $productId,
        ?string $productSku,
        ?string $productName,
        ?float $oldPrice,
        ?float $newPrice,
        ?float $optPrice,
        string $status,
        string $message,
    ): array {
        return [
            'supplier_code'      => $supplierCode,
            'supplier_article'   => $supplierArticle,
            'supplier_name'      => $supplierName,
            'product_id'         => $productId !== null ? (string) $productId : '',
            'product_sku'        => $productSku ?? '',
            'product_name'       => $productName ?? '',
            'old_price'          => $oldPrice !== null ? number_format($oldPrice, 2, '.', '') : '',
            'new_price'          => $newPrice !== null ? number_format($newPrice, 2, '.', '') : '',
            'supplier_opt_price' => $optPrice !== null ? number_format($optPrice, 2, '.', '') : '',
            'status'             => $status,
            'message'            => $message,
        ];
    }

    private function saveCsvReport(string $supplier, array $rows): string
    {
        $dir = storage_path('app/pricelists/reports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('%s/%s_%s.csv', $dir, $supplier, now()->format('Y-m-d_His'));
        $handle   = fopen($filename, 'w');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if (! empty($rows)) {
            fputcsv($handle, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row), ';');
            }
        }

        fclose($handle);
        return $filename;
    }

    private function resolveFilePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Z]:/i', $path)) {
            return $path;
        }
        return base_path($path);
    }
}
