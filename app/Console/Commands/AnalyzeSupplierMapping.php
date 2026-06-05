<?php

namespace App\Console\Commands;

use App\Services\PriceList\SupplierAdapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeSupplierMapping extends Command
{
    protected $signature = 'supplier-mapping:analyze
                            {file : Path to the Excel price list file}
                            {--supplier=berezka : Supplier adapter code}';

    protected $description = 'Analyze supplier pricelist and generate a CSV with suggested product mappings for manual review.';

    public function handle(): int
    {
        $filePath = $this->resolveFilePath($this->argument('file'));
        $supplier = $this->option('supplier');

        $this->line('');
        $this->line("  <fg=cyan;options=bold>Анализ маппинга поставщика: {$supplier}</>");
        $this->line("  Файл: <fg=yellow>{$filePath}</>");
        $this->line('');

        $config = SupplierAdapter::getConfig($supplier);

        try {
            $rows = SupplierAdapter::readExcel($filePath, $config);
        } catch (\Throwable $e) {
            $this->error('Ошибка чтения файла: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line("  Прочитано строк: <fg=yellow>" . count($rows) . "</>");

        $csvRows   = [];
        $stats     = ['total' => 0, 'skipped' => 0, 'matched_high' => 0, 'matched_low' => 0, 'not_found' => 0];

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

            [$matchedId, $matchedSku, $matchedName, $matchType, $confidence, $status, $comment]
                = $this->findCandidate($supplierName);

            if (in_array($confidence, ['high', 'manual'])) {
                $stats['matched_high']++;
            } elseif ($confidence === 'medium') {
                $stats['matched_low']++;
            } else {
                $stats['not_found']++;
            }

            $csvRows[] = [
                'supplier_code'        => $supplier,
                'supplier_article'     => $article,
                'supplier_name'        => $supplierName,
                'supplier_rrc'         => $rrc !== null ? number_format($rrc, 2, '.', '') : '',
                'supplier_opt_price'   => $opt !== null ? number_format($opt, 2, '.', '') : '',
                'matched_product_id'   => $matchedId,
                'matched_product_sku'  => $matchedSku,
                'matched_product_name' => $matchedName,
                'match_type'           => $matchType,
                'confidence'           => $confidence,
                'status'               => $status,
                'comment'              => $comment,
            ];
        }

        $csvPath = $this->saveCsv($supplier, $csvRows);

        $this->line('');
        $this->line('  <fg=cyan;options=bold>Результат анализа:</>');
        $this->line("  Обработано строк с артикулом: <fg=white>{$stats['total']}</>");
        $this->line("  Пропущено (пустой артикул):   <fg=yellow>{$stats['skipped']}</>");
        $this->line("  Уверенное совпадение:         <fg=green>{$stats['matched_high']}</>");
        $this->line("  Слабое совпадение:             <fg=yellow>{$stats['matched_low']}</>");
        $this->line("  Не найдено:                   <fg=red>{$stats['not_found']}</>");
        $this->line('');
        $this->line("  CSV для ручной проверки: <fg=cyan>{$csvPath}</>");
        $this->line('');
        $this->line('  Следующий шаг: откройте CSV, проверьте matched_product_id,');
        $this->line('  исправьте ошибки, сохраните как <fg=yellow>berezka_mapping_checked.csv</>');
        $this->line('  и запустите: <fg=yellow>php artisan supplier-mapping:import ... --apply</>');
        $this->line('');

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function findCandidate(string $supplierName): array
    {
        if ($supplierName === '') {
            return [null, null, null, 'none', null, 'not_found', 'Пустое название'];
        }

        // Extract model keyword from name (e.g. "Hors-9", "Воевода 20", "Витязь 18")
        $keyword = $this->extractKeyword($supplierName);

        // 1. Exact name match
        $exact = DB::table('products')
            ->where('name', $supplierName)
            ->select('id', 'sku', 'name')
            ->first();

        if ($exact) {
            return [$exact->id, $exact->sku, $exact->name, 'exact_name', 'high', 'suggested', 'Точное совпадение по названию'];
        }

        // 2. Partial name match on keyword
        if ($keyword) {
            $candidates = DB::table('products')
                ->where('name', 'like', "%{$keyword}%")
                ->select('id', 'sku', 'name')
                ->limit(5)
                ->get();

            if ($candidates->count() === 1) {
                $p = $candidates->first();
                return [$p->id, $p->sku, $p->name, 'partial_name', 'medium', 'needs_review', "Один кандидат по ключевой модели: {$keyword}"];
            }

            if ($candidates->count() > 1) {
                $names = $candidates->pluck('name')->implode(' | ');
                return [null, null, null, 'partial_name', 'low', 'needs_review', "Несколько кандидатов по '{$keyword}': {$names}"];
            }
        }

        // 3. Fuzzy: try each word >= 5 chars
        $words = array_filter(preg_split('/[\s\-\/,]+/', $supplierName) ?: [], fn($w) => mb_strlen($w) >= 5);
        foreach ($words as $word) {
            $hits = DB::table('products')
                ->where('name', 'like', "%{$word}%")
                ->select('id', 'sku', 'name')
                ->limit(3)
                ->get();

            if ($hits->count() === 1) {
                $p = $hits->first();
                return [$p->id, $p->sku, $p->name, 'fuzzy_word', 'low', 'needs_review', "Один кандидат по слову '{$word}'"];
            }
        }

        return [null, null, null, 'none', null, 'not_found', 'Совпадений не найдено'];
    }

    private function extractKeyword(string $name): ?string
    {
        // Patterns: "Hors-6", "Hors-9", "Hors-13", "Magma-8"
        if (preg_match('/\b(Hors-\d+|Magma-\d+|Black\s*Stove\s*Hors-\d+)/ui', $name, $m)) {
            return $m[1];
        }
        // "Verona 18", "Verona 26", "Varna 16", "Varna 26"
        if (preg_match('/\b(Verona\s*\d+|Varna\s*\d+)/ui', $name, $m)) {
            return $m[1];
        }
        // "Воевода 15", "Воевода 20", "Викинг 15", "Витязь 18", "Флагман 15"
        if (preg_match('/\b(Воевода\s*\d+|Викинг\s*\d+|Витязь\s*\d+|Флагман\s*\d+|Фаворит\s*\d+|Берёзка\s*\d+|Березка\s*\d+)/ui', $name, $m)) {
            return $m[1];
        }
        // "BulyK 100", "BulyK 150"
        if (preg_match('/\b(Buly[Kk]\s*\d+)/ui', $name, $m)) {
            return $m[1];
        }
        // "Тундра 14", "Тундра 16"
        if (preg_match('/\b(Тундра\s*\d+)/ui', $name, $m)) {
            return $m[1];
        }
        // "Корвет 15", "Корвет 24", "Оптима 12"
        if (preg_match('/\b(Корвет\s*\d+|Оптима\s*\d+)/ui', $name, $m)) {
            return $m[1];
        }

        return null;
    }

    private function saveCsv(string $supplier, array $rows): string
    {
        $dir = storage_path('app/pricelists/mappings');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('%s/%s_mapping_suggested_%s.csv', $dir, $supplier, now()->format('Y-m-d_His'));
        $handle   = fopen($filename, 'w');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

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
