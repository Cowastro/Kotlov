<?php

namespace App\Console\Commands;

use App\Models\SupplierReviewDecision;
use Illuminate\Console\Command;

class QueueSupplierReviewDecisionsCommand extends Command
{
    protected $signature = 'supplier:queue-review-decisions
        {--file= : Relative path under storage/app/reports or absolute CSV path}
        {--decision=link : link, retail, ignore}
        {--supplier=bania : Supplier code to store in queued decisions}
        {--limit= : Maximum rows to queue}
        {--row=* : Specific report row numbers to queue}
        {--apply : Create pending decisions}';

    protected $description = 'Queue supplier review decisions in bulk from a CSV import report.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $decision = $this->normalizeDecision((string) $this->option('decision'));
        $supplier = trim((string) $this->option('supplier')) ?: 'bania';
        $targetRows = $this->integerListOption('row');
        $limit = max(0, (int) $this->option('limit'));

        if (! $apply) {
            $this->warn('DRY RUN: decisions will not be created. Use --apply to enqueue them.');
        }

        $report = $this->resolveReportPath($decision, (string) $this->option('file'));
        if (! $report) {
            $this->error('CSV report not found.');
            return self::FAILURE;
        }

        $rows = $this->readCsv($report['absolute']);
        if ($rows === []) {
            $this->warn('The report has no rows.');
            return self::SUCCESS;
        }

        $metrics = [
            'rows_scanned' => 0,
            'eligible' => 0,
            'queued' => 0,
            'already_exists' => 0,
            'skipped_missing_ids' => 0,
            'skipped_missing_price' => 0,
            'skipped_duplicate_row' => 0,
            'skipped_duplicate_pair' => 0,
            'skipped_row_filter' => 0,
        ];

        $preview = [];
        $seenPairs = [];

        foreach ($rows as $index => $row) {
            $reportRow = $this->reportRow($row, $index);
            $metrics['rows_scanned']++;

            if ($targetRows !== [] && ! in_array($reportRow, $targetRows, true)) {
                $metrics['skipped_row_filter']++;
                continue;
            }

            if ($limit > 0 && $metrics['queued'] >= $limit) {
                break;
            }

            $payload = $this->buildPayload($decision, $row);
            if (! $payload['eligible']) {
                $metrics[$payload['skip_metric']]++;
                continue;
            }

            $metrics['eligible']++;

            $pendingForRow = SupplierReviewDecision::query()
                ->where('report_file', $report['relative'])
                ->where('report_row', $reportRow)
                ->where('status', SupplierReviewDecision::STATUS_PENDING)
                ->first();

            if ($pendingForRow) {
                $metrics['skipped_duplicate_row']++;
                continue;
            }

            $pairKey = $payload['decision'] . '|' . ($payload['supplier_product_id'] ?? '') . '|' . ($payload['product_id'] ?? '') . '|' . ($payload['price'] ?? '');
            if (isset($seenPairs[$pairKey])) {
                $metrics['skipped_duplicate_pair']++;
                continue;
            }

            $decisionKey = sha1(implode('|', [
                $report['relative'],
                $reportRow,
                $payload['decision'],
                $payload['supplier_product_id'] ?? '',
                $payload['product_id'] ?? '',
                $payload['price'] ?? '',
            ]));

            $existing = SupplierReviewDecision::query()->where('decision_key', $decisionKey)->first();
            if ($existing) {
                $metrics['already_exists']++;
                continue;
            }

            $preview[] = [
                'row' => $reportRow,
                'decision' => $this->decisionLabel($payload['decision']),
                'supplier_product_id' => $payload['supplier_product_id'] ?: '-',
                'product_id' => $payload['product_id'] ?: '-',
                'price' => $payload['price'] ?: '-',
                'supplier_title' => $payload['supplier_title'] ?: '-',
            ];

            if ($apply) {
                SupplierReviewDecision::query()->create([
                    'decision_key' => $decisionKey,
                    'supplier_code' => $supplier,
                    'report_file' => $report['relative'],
                    'report_row' => $reportRow,
                    'decision' => $payload['decision'],
                    'status' => SupplierReviewDecision::STATUS_PENDING,
                    'supplier_product_id' => $payload['supplier_product_id'],
                    'product_id' => $payload['product_id'],
                    'supplier_title' => $payload['supplier_title'],
                    'supplier_article' => $payload['supplier_article'],
                    'source_url' => $payload['source_url'],
                    'reason' => $payload['reason'],
                    'payload' => $payload['payload'],
                ]);
            }

            $seenPairs[$pairKey] = true;
            $metrics['queued']++;
        }

        $this->info('Report: ' . $report['relative']);

        if ($preview !== []) {
            $this->table(
                ['row', 'decision', 'supplier_product_id', 'product_id', 'price', 'supplier_title'],
                $preview
            );
        }

        $this->table(['metric', 'count'], collect($metrics)->map(fn (int $count, string $metric): array => [
            'metric' => $metric,
            'count' => $count,
        ])->values()->all());

        return self::SUCCESS;
    }

    private function normalizeDecision(string $decision): string
    {
        return match (trim(mb_strtolower($decision))) {
            'link', 'bind', 'match' => SupplierReviewDecision::DECISION_LINK,
            'retail', 'price', 'retail_price' => SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE,
            'ignore' => SupplierReviewDecision::DECISION_IGNORE,
            default => throw new \InvalidArgumentException('Unsupported decision. Use: link, retail, ignore'),
        };
    }

    private function resolveReportPath(string $decision, string $input): ?array
    {
        if (trim($input) !== '') {
            $path = trim($input);
            $absolute = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
                ? $path
                : storage_path('app/reports/' . ltrim(str_replace('\\', '/', $path), '/'));

            if (! is_file($absolute)) {
                return null;
            }

            return [
                'absolute' => $absolute,
                'relative' => $this->relativeReportPath($absolute),
            ];
        }

        $pattern = in_array($decision, [
            SupplierReviewDecision::DECISION_LINK,
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE,
        ], true)
            ? storage_path('app/reports/bania/price-list-manual-review-*.csv')
            : storage_path('app/reports/bania/manual-review-*.csv');

        $files = glob($pattern) ?: [];
        rsort($files);
        $absolute = $files[0] ?? null;

        if (! $absolute || ! is_file($absolute)) {
            return null;
        }

        return [
            'absolute' => $absolute,
            'relative' => $this->relativeReportPath($absolute),
        ];
    }

    private function relativeReportPath(string $absolute): string
    {
        $base = str_replace('\\', '/', storage_path('app/reports'));
        $path = str_replace('\\', '/', $absolute);

        return ltrim(str_replace($base, '', $path), '/');
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return [];
        }

        $rows = [];
        $headers = null;

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(function (?string $header): string {
                    $header = (string) $header;
                    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
                    return trim($header);
                }, $data);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function buildPayload(string $decision, array $row): array
    {
        $supplierProductId = $this->supplierProductId($row);
        $productId = $this->productId($row);
        $supplierTitle = $this->supplierTitle($row);
        $supplierArticle = $this->supplierArticle($row);
        $sourceUrl = $this->sourceUrl($row);
        $reason = trim((string) ($row['reason'] ?? $row['note'] ?? $row['error'] ?? ''));

        if ($decision === SupplierReviewDecision::DECISION_LINK) {
            if ($supplierProductId === null || $productId === null) {
                return ['eligible' => false, 'skip_metric' => 'skipped_missing_ids'];
            }

            return [
                'eligible' => true,
                'decision' => $decision,
                'supplier_product_id' => $supplierProductId,
                'product_id' => $productId,
                'price' => null,
                'supplier_title' => $supplierTitle,
                'supplier_article' => $supplierArticle,
                'source_url' => $sourceUrl,
                'reason' => $reason !== '' ? $reason : 'Массовая ручная привязка из отчёта импорта',
                'payload' => $row,
            ];
        }

        if ($decision === SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE) {
            if ($productId === null) {
                return ['eligible' => false, 'skip_metric' => 'skipped_missing_ids'];
            }

            $price = $this->parseMoney($this->firstFilled($row, ['suggested_retail_price', 'suggested_retail_simple']));
            if ($price === null || $price <= 0) {
                return ['eligible' => false, 'skip_metric' => 'skipped_missing_price'];
            }

            return [
                'eligible' => true,
                'decision' => $decision,
                'supplier_product_id' => $supplierProductId,
                'product_id' => $productId,
                'price' => number_format($price, 2, '.', ''),
                'supplier_title' => $supplierTitle,
                'supplier_article' => $supplierArticle,
                'source_url' => $sourceUrl,
                'reason' => 'Массовое обновление розницы из отчёта импорта',
                'payload' => [
                    'manual_retail_price' => number_format($price, 2, '.', ''),
                    'old_product_retail_price' => $this->firstFilled($row, ['product_retail_price', 'old_product_price', 'kotlov_retail']),
                    'row' => $row,
                ],
            ];
        }

        return [
            'eligible' => true,
            'decision' => SupplierReviewDecision::DECISION_IGNORE,
            'supplier_product_id' => $supplierProductId,
            'product_id' => $productId,
            'price' => null,
            'supplier_title' => $supplierTitle,
            'supplier_article' => $supplierArticle,
            'source_url' => $sourceUrl,
            'reason' => 'Массово отмечено как проверенное',
            'payload' => $row,
        ];
    }

    private function reportRow(array $row, int $index): int
    {
        $value = trim((string) ($row['price_row'] ?? $row['row'] ?? ''));
        return ctype_digit($value) ? (int) $value : $index + 2;
    }

    private function productId(array $row): ?int
    {
        $value = trim((string) ($row['product_id'] ?? $row['possible_product_id'] ?? $row['matched_product_id'] ?? ''));
        return ctype_digit($value) ? (int) $value : null;
    }

    private function supplierProductId(array $row): ?int
    {
        $value = trim((string) ($row['supplier_product_id'] ?? $row['possible_supplier_product_id'] ?? $row['matched_supplier_product_id'] ?? ''));
        return ctype_digit($value) ? (int) $value : null;
    }

    private function supplierTitle(array $row): ?string
    {
        $value = $this->firstFilled($row, ['supplier_title', 'price_title', 'supplier_item', 'title']);
        return $value !== '' ? $value : null;
    }

    private function supplierArticle(array $row): ?string
    {
        $value = $this->firstFilled($row, ['supplier_sku', 'supplier_article', 'price_article', 'supplier_article_short']);
        return $value !== '' ? $value : null;
    }

    private function sourceUrl(array $row): ?string
    {
        $value = trim((string) ($row['source_url'] ?? $row['supplier_url'] ?? ''));
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://') ? $value : null;
    }

    private function firstFilled(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function parseMoney(string $value): ?float
    {
        $normalized = trim(str_replace(["\xc2\xa0", 'BYN', 'byn', ' '], '', $value));
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function integerListOption(string $name): array
    {
        $values = (array) $this->option($name);
        $ids = [];

        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $id = (int) trim($part);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function decisionLabel(string $decision): string
    {
        return match ($decision) {
            SupplierReviewDecision::DECISION_LINK => 'связать',
            SupplierReviewDecision::DECISION_UPDATE_RETAIL_PRICE => 'обновить розницу',
            SupplierReviewDecision::DECISION_IGNORE => 'игнорировать',
            default => $decision,
        };
    }
}
