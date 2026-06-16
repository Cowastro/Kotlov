<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?string $navigationLabel = 'Отчёты импорта';
    protected static ?string $title = 'Отчёты импорта';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.pages.import-reports';

    public string $supplier = '';
    public string $type = '';
    public string $search = '';
    public ?string $selectedFile = null;
    public int $perPage = 100;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->selectedFile = $this->reports()[0]['relative_path'] ?? null;
    }

    public function updatedSupplier(): void
    {
        $this->selectedFile = $this->reports()[0]['relative_path'] ?? null;
    }

    public function updatedType(): void
    {
        $this->selectedFile = $this->reports()[0]['relative_path'] ?? null;
    }

    public function updatedSearch(): void
    {
        $this->selectedFile ??= $this->reports()[0]['relative_path'] ?? null;
    }

    public function supplierOptions(): array
    {
        return collect($this->allReports())
            ->pluck('supplier')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function typeOptions(): array
    {
        return collect($this->allReports())
            ->pluck('type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function reports(): array
    {
        return array_values(array_filter($this->allReports(), function (array $report): bool {
            if ($this->supplier !== '' && $report['supplier'] !== $this->supplier) {
                return false;
            }

            if ($this->type !== '' && $report['type'] !== $this->type) {
                return false;
            }

            if ($this->search !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $report['supplier'],
                    $report['type'],
                    $report['file_name'],
                    $report['relative_path'],
                ]));

                if (! str_contains($haystack, mb_strtolower($this->search))) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function selectedReport(): ?array
    {
        $reports = $this->reports();
        if ($reports === []) {
            return null;
        }

        foreach ($reports as $report) {
            if ($report['relative_path'] === $this->selectedFile) {
                return $report;
            }
        }

        return $reports[0];
    }

    public function selectedRows(): array
    {
        $report = $this->selectedReport();
        if (! $report) {
            return [];
        }

        return array_slice($this->readCsv($report['absolute_path']), 0, $this->perPage);
    }

    public function selectedHeaders(): array
    {
        $rows = $this->selectedRows();
        return $rows !== [] ? array_keys($rows[0]) : [];
    }

    public function downloadSelected(): ?StreamedResponse
    {
        $report = $this->selectedReport();
        if (! $report || ! is_file($report['absolute_path'])) {
            return null;
        }

        return response()->streamDownload(function () use ($report): void {
            readfile($report['absolute_path']);
        }, $report['file_name'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function productAdminUrl(array $row): ?string
    {
        $productId = trim((string) ($row['product_id'] ?? $row['possible_product_id'] ?? ''));
        if ($productId === '' || ! ctype_digit($productId)) {
            return null;
        }

        return url('/admin/products/' . $productId . '/edit');
    }

    public function sourceUrl(array $row): ?string
    {
        $url = trim((string) ($row['source_url'] ?? ''));
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : null;
    }

    private function allReports(): array
    {
        $root = $this->reportsRoot();
        if (! is_dir($root)) {
            return [];
        }

        $files = glob($root . DIRECTORY_SEPARATOR . '{*,*/*}.csv', GLOB_BRACE) ?: [];
        $reports = [];

        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $relative = str_replace('\\', '/', Str::after($path, $root . DIRECTORY_SEPARATOR));
            $supplier = str_contains($relative, '/') ? Str::before($relative, '/') : 'general';
            $fileName = basename($path);

            $reports[] = [
                'absolute_path' => $path,
                'relative_path' => $relative,
                'supplier' => $supplier,
                'type' => $this->reportType($fileName),
                'file_name' => $fileName,
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ?: 0,
                'attention_count' => $this->attentionCount($path),
            ];
        }

        usort($reports, fn (array $left, array $right): int => $right['modified_at'] <=> $left['modified_at']);

        return $reports;
    }

    private function reportsRoot(): string
    {
        return storage_path('app/reports');
    }

    private function reportType(string $fileName): string
    {
        return match (true) {
            str_contains($fileName, 'manual-review') => 'manual-review',
            str_contains($fileName, 'ai-review') => 'ai-review',
            str_contains($fileName, 'archive') => 'archive',
            str_contains($fileName, 'audit') => 'audit',
            str_contains($fileName, 'price-list') => 'price-list',
            str_contains($fileName, 'sync') => 'sync',
            str_contains($fileName, 'import') => 'import',
            default => 'report',
        };
    }

    private function attentionCount(string $path): int
    {
        $count = 0;
        foreach ($this->readCsv($path, 500) as $row) {
            $action = mb_strtolower((string) ($row['action'] ?? $row['recommended_action'] ?? ''));
            $note = mb_strtolower((string) ($row['note'] ?? $row['reason'] ?? ''));

            if (
                str_contains($action, 'manual')
                || str_contains($action, 'error')
                || str_contains($action, 'cost_above_retail')
                || str_contains($action, 'keep_manual_review')
                || str_contains($note, 'manual')
                || str_contains($note, 'check')
            ) {
                $count++;
            }
        }

        return $count;
    }

    private function readCsv(string $path, int $limit = 1000): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            return [];
        }

        $headers = array_map(
            fn ($header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header)),
            $headers
        );

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (! is_array($values)) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }
            $rows[] = $row;

            if (count($rows) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }
}
