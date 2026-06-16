<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
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

        return $this->enrichRowsWithProductSkus(
            array_slice($this->readCsv($report['absolute_path']), 0, $this->perPage)
        );
    }

    public function selectedHeaders(): array
    {
        $rows = $this->selectedRows();
        if ($rows === []) {
            return [];
        }

        $hidden = [
            'kotlov_sku',
            'product_sku',
            'possible_product_sku',
            'matched_product_sku',
        ];

        $preferred = [
            'price_row',
            'price_title',
            'price_article',
            'supplier_title',
            'supplier_sku',
            'supplier_url',
            'source_url',
            'possible_supplier_title',
            'possible_product_title',
            'matched_product_title',
            'title',
            'possible_supplier_product_id',
            'supplier_product_id',
            'possible_product_id',
            'product_id',
            'matched_product_id',
            'brand',
            'old_supplier_price',
            'new_supplier_cost',
            'supplier_price',
            'product_retail_price',
            'old_stock_status',
            'new_stock_status',
            'stock_status',
            'match_type',
            'confidence',
            'action',
            'recommended_action',
            'reason',
            'note',
            'error',
        ];

        $headers = array_values(array_filter(
            array_keys($rows[0]),
            fn (string $header): bool => ! in_array($header, $hidden, true)
        ));

        $ordered = array_values(array_filter(
            $preferred,
            fn (string $header): bool => in_array($header, $headers, true)
        ));

        $tail = array_values(array_filter(
            $headers,
            fn (string $header): bool => ! in_array($header, $ordered, true)
        ));

        return array_merge($ordered, $tail);
    }

    public function headerLabel(string $header): string
    {
        return [
            'price_row' => 'Строка прайса',
            'price_title' => 'Товар в прайсе',
            'price_article' => 'Артикул в прайсе',
            'price_value' => 'Цена в прайсе',
            'price_article_normalized' => 'Артикул норм.',
            'supplier' => 'Поставщик',
            'supplier_title' => 'Товар поставщика',
            'supplier_name' => 'Товар поставщика',
            'supplier_sku' => 'Артикул поставщика',
            'supplier_article' => 'Артикул поставщика',
            'supplier_url' => 'URL поставщика',
            'source_url' => 'URL источника',
            'source_category' => 'Раздел источника',
            'possible_supplier_product_id' => 'ID связки поставщика',
            'supplier_product_id' => 'ID связки поставщика',
            'possible_supplier_title' => 'Товар в связке поставщика',
            'possible_product_id' => 'ID товара KOTLOV',
            'product_id' => 'ID товара KOTLOV',
            'matched_product_id' => 'ID товара KOTLOV',
            'possible_product_title' => 'Товар KOTLOV',
            'matched_product_title' => 'Товар KOTLOV',
            'title' => 'Товар KOTLOV',
            'brand' => 'Бренд',
            'old_supplier_price' => 'Старая закупка',
            'new_supplier_cost' => 'Новая закупка',
            'supplier_price' => 'Цена поставщика',
            'product_retail_price' => 'Розница KOTLOV',
            'old_stock_status' => 'Старое наличие',
            'new_stock_status' => 'Новое наличие',
            'stock_status' => 'Наличие',
            'stock_text' => 'Текст наличия',
            'product_in_stock_before' => 'Товар был в наличии',
            'match_type' => 'Тип совпадения',
            'confidence' => 'Уверенность',
            'ai_confidence' => 'Уверенность AI',
            'ai_decision' => 'Решение AI',
            'action' => 'Действие',
            'recommended_action' => 'Рекомендация',
            'reason' => 'Причина',
            'note' => 'Примечание',
            'error' => 'Ошибка',
            'page' => 'Страница',
            'attributes_count' => 'Характеристик',
            'images_count' => 'Фото',
            'description_length' => 'Длина описания',
        ][$header] ?? Str::of($header)->replace('_', ' ')->title()->toString();
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
        $productId = $this->productId($row);
        if ($productId === '' || ! ctype_digit($productId)) {
            return null;
        }

        return url('/admin/products/' . $productId . '/edit');
    }

    public function kotlovSku(array $row): string
    {
        foreach (['kotlov_sku', 'product_sku', 'possible_product_sku', 'matched_product_sku'] as $key) {
            $sku = trim((string) ($row[$key] ?? ''));
            if ($sku !== '') {
                return $sku;
            }
        }

        return '';
    }

    public function sourceUrl(array $row): ?string
    {
        $url = trim((string) ($row['source_url'] ?? ''));
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : null;
    }

    private function enrichRowsWithProductSkus(array $rows): array
    {
        $missingIds = [];
        foreach ($rows as $row) {
            if ($this->kotlovSku($row) !== '') {
                continue;
            }

            $productId = $this->productId($row);
            if ($productId !== '' && ctype_digit($productId)) {
                $missingIds[] = (int) $productId;
            }
        }

        $skus = $missingIds !== []
            ? DB::table('products')->whereIn('id', array_values(array_unique($missingIds)))->pluck('sku', 'id')->all()
            : [];

        foreach ($rows as $index => $row) {
            $sku = $this->kotlovSku($row);
            if ($sku === '') {
                $productId = $this->productId($row);
                $sku = $productId !== '' ? (string) ($skus[(int) $productId] ?? '') : '';
            }

            $rows[$index] = ['kotlov_sku' => $sku] + $row;
        }

        return $rows;
    }

    private function productId(array $row): string
    {
        return trim((string) ($row['product_id'] ?? $row['possible_product_id'] ?? $row['matched_product_id'] ?? ''));
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
