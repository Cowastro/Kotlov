<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AuditTeplodarBaniaCommand extends Command
{
    protected $signature = 'supplier:audit-teplodar-bania
        {--stock-file= : BANIA stock workbook path}
        {--retail-file= : BANIA retail workbook path}
        {--output=public/exports/teplodar-bania-audit.csv : CSV report path relative to the application root}';

    protected $description = 'Audit Teplodar catalogue products against BANIA article, cost, stock and retail price lists.';

    public function handle(): int
    {
        $stockFile = $this->absolutePath((string) ($this->option('stock-file') ?: storage_path('app/supplier-cache/bania-pricelist.xlsx')));
        $retailFile = $this->absolutePath((string) ($this->option('retail-file') ?: storage_path('app/supplier-cache/bania-retail-pricelist.xlsx')));

        if (! is_file($stockFile) || ! is_file($retailFile)) {
            $this->error('BANIA stock or retail workbook is missing. Refresh the supplier cache first.');
            return self::FAILURE;
        }

        $stock = $this->indexWorkbook($stockFile, 0, 2, 5, 6);
        $retail = $this->indexWorkbook($retailFile, 0, 1, 3, null);
        $supplierId = (int) DB::table('suppliers')->where('code', 'bania')->value('id');

        if (! $supplierId) {
            $this->error('BANIA supplier is not registered.');
            return self::FAILURE;
        }

        $products = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where(function ($query) {
                $query->where('b.slug', 'teplodar')
                    ->orWhere('p.name', 'like', '%Теплодар%')
                    ->orWhere('p.name', 'like', '%КУППЕР%')
                    ->orWhere('p.name', 'like', '%БЫЛИНА%')
                    ->orWhere('p.name', 'like', '%САХАРА%')
                    ->orWhere('p.name', 'like', '%СИБИРСКИЙ УТЕС%')
                    ->orWhere('p.name', 'like', '%СИЕСТА%');
            })
            ->select([
                'p.id', 'p.sku', 'p.name', 'p.slug', 'p.price', 'p.in_stock',
                'p.is_active', 'p.is_archived', 'b.name as brand', 'c.name as category',
            ])
            ->orderBy('p.name')
            ->orderBy('p.id')
            ->get();

        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $links = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => (int) $row->product_id);

        $articleProductCounts = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('supplier_article_normalized')
            ->where('supplier_article_normalized', '<>', '')
            ->selectRaw('supplier_article_normalized, COUNT(DISTINCT product_id) as product_count')
            ->groupBy('supplier_article_normalized')
            ->pluck('product_count', 'supplier_article_normalized');

        $rows = [];
        foreach ($products as $product) {
            $productLinks = $links->get((int) $product->id, collect());
            if ($productLinks->isEmpty()) {
                $rows[] = $this->reportRow($product, null, null, null, 'no_bania_link');
                continue;
            }

            foreach ($productLinks as $link) {
                $article = $this->normalizeArticle((string) ($link->supplier_article ?? ''));
                $stockRows = $article !== '' ? ($stock[$article] ?? []) : [];
                $retailRows = $article !== '' ? ($retail[$article] ?? []) : [];
                $status = [];

                if ($article === '') {
                    $status[] = 'missing_article';
                }
                if (count($stockRows) === 0) {
                    $status[] = 'missing_from_stock_price';
                } elseif (count($stockRows) > 1) {
                    $status[] = 'duplicate_stock_article';
                }
                if (count($retailRows) === 0) {
                    $status[] = 'missing_rrp';
                } elseif (count($retailRows) > 1) {
                    $status[] = 'duplicate_rrp_article';
                }
                if ((int) ($articleProductCounts[$article] ?? 0) > 1) {
                    $status[] = 'article_linked_to_multiple_products';
                }

                $stockRow = count($stockRows) === 1 ? $stockRows[0] : null;
                $retailRow = count($retailRows) === 1 ? $retailRows[0] : null;
                if ($stockRow && abs((float) $link->price_byn - (float) $stockRow['price']) > 0.009) {
                    $status[] = 'cost_mismatch';
                }
                if ($retailRow && abs((float) $product->price - (float) $retailRow['price']) > 0.009) {
                    $status[] = 'retail_price_mismatch';
                }
                if ($stockRow && (bool) $product->is_archived && $this->available((string) $stockRow['stock'])) {
                    $status[] = 'archived_but_supplier_available';
                }

                $rows[] = $this->reportRow(
                    $product,
                    $link,
                    $stockRow,
                    $retailRow,
                    $status === [] ? 'ok' : implode('|', $status)
                );
            }
        }

        $output = $this->absolutePath((string) $this->option('output'));
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }
        $this->writeCsv($output, $rows);

        $issues = count(array_filter($rows, fn (array $row) => $row['status'] !== 'ok'));
        $this->table(['metric', 'count'], [
            ['teplodar_products', $products->count()],
            ['audit_rows', count($rows)],
            ['ok_rows', count($rows) - $issues],
            ['issue_rows', $issues],
            ['no_bania_link', count(array_filter($rows, fn (array $row) => str_contains($row['status'], 'no_bania_link')))],
            ['retail_price_mismatch', count(array_filter($rows, fn (array $row) => str_contains($row['status'], 'retail_price_mismatch')))],
            ['cost_mismatch', count(array_filter($rows, fn (array $row) => str_contains($row['status'], 'cost_mismatch')))],
            ['duplicate_article_links', count(array_filter($rows, fn (array $row) => str_contains($row['status'], 'article_linked_to_multiple_products')))],
        ]);
        $this->info('Report written: ' . $output);

        return self::SUCCESS;
    }

    private function indexWorkbook(string $path, int $nameColumn, int $articleColumn, int $priceColumn, ?int $stockColumn): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        $index = [];

        foreach ($rows as $offset => $cells) {
            $articleRaw = trim((string) ($cells[$articleColumn] ?? ''));
            $article = $this->normalizeArticle($articleRaw);
            $price = $this->money($cells[$priceColumn] ?? null);
            if ($article === '' || $price === null || $price <= 0) {
                continue;
            }

            $index[$article][] = [
                'row' => $offset + 1,
                'name' => trim((string) ($cells[$nameColumn] ?? '')),
                'article' => $articleRaw,
                'price' => $price,
                'stock' => $stockColumn === null ? '' : trim((string) ($cells[$stockColumn] ?? '')),
            ];
        }

        return $index;
    }

    private function reportRow(object $product, ?object $link, ?array $stock, ?array $retail, string $status): array
    {
        return [
            'status' => $status,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'category' => $product->category,
            'is_active' => (int) $product->is_active,
            'is_archived' => (int) $product->is_archived,
            'product_in_stock' => (int) $product->in_stock,
            'site_price' => $this->decimal((float) $product->price),
            'supplier_article' => $link->supplier_article ?? '',
            'stored_stock_status' => $link->stock_status ?? '',
            'stock_price_row' => $stock['row'] ?? '',
            'stock_title' => $stock['name'] ?? '',
            'current_stock' => $stock['stock'] ?? '',
            'retail_price_row' => $retail['row'] ?? '',
            'retail_title' => $retail['name'] ?? '',
            'current_rrp' => isset($retail['price']) ? $this->decimal((float) $retail['price']) : '',
        ];
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot write audit report: ' . $path);
        }
        fwrite($handle, "\xEF\xBB\xBF");
        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
        }
        fclose($handle);
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path)) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    private function normalizeArticle(string $value): string
    {
        return mb_strtoupper(preg_replace('/[^0-9A-ZА-ЯЁ]+/u', '', trim($value)) ?? '');
    }

    private function money(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }
        $text = preg_replace('/[^0-9,.\-]/u', '', str_replace(["\xc2\xa0", ' '], '', trim((string) $value))) ?? '';
        if ($text === '' || $text === '-') {
            return null;
        }
        $text = str_contains($text, ',') && str_contains($text, '.')
            ? str_replace(',', '', $text)
            : str_replace(',', '.', $text);

        return is_numeric($text) ? round((float) $text, 2) : null;
    }

    private function available(string $stock): bool
    {
        $stock = mb_strtolower($stock);
        return $stock !== '' && ! str_contains($stock, 'нет') && ! str_contains($stock, 'отсутств') && ! str_contains($stock, 'снят');
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
