<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductPurchasePriceSource;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ImportConfirmedPurchasePricesCommand extends Command
{
    protected $signature = 'products:import-confirmed-purchase-prices
        {supplier : Exact supplier code}
        {--document= : Document number}
        {--date= : Document date YYYY-MM-DD}
        {--sha256= : SHA-256 of the original source file}
        {--tax= : exclusive or inclusive}
        {--vat=20 : VAT percentage}
        {--row=* : Exact model:unit-price; append ~Protect to distinguish Protect variants}
        {--apply : Save confirmed purchase prices; default only writes a preview report}';

    protected $description = 'Record document-backed BYN purchase prices without changing retail prices, links or stock.';

    public function handle(): int
    {
        try {
            $supplier = Supplier::query()->where('code', $this->argument('supplier'))->sole();
            $document = trim((string) $this->option('document'));
            $date = (string) $this->option('date');
            $hash = strtolower((string) $this->option('sha256'));
            $tax = (string) $this->option('tax');
            $vat = filter_var($this->option('vat'), FILTER_VALIDATE_FLOAT);
            if ($document === '' || mb_strlen($document) > 100
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || date('Y-m-d', strtotime($date)) !== $date
                || ! preg_match('/^[a-f0-9]{64}$/', $hash)
                || ! in_array($tax, ['exclusive', 'inclusive'], true)
                || $vat === false || $vat < 0 || $vat > 100) {
                throw new RuntimeException('Required: document, valid date, original file SHA-256, explicit tax mode and VAT.');
            }
            $input = (array) $this->option('row');
            if ($input === [] || count($input) > 100) {
                throw new RuntimeException('Provide 1–100 model:unit-price rows.');
            }
            $products = Product::query()->where('is_active', true)->where('is_archived', false)
                ->get(['id', 'sku', 'name', 'price']);
            $rows = [];
            $seen = [];
            foreach ($input as $line) {
                if (! preg_match('/^([^:]{2,150}):(\d+(?:\.\d{1,2})?)$/u', $line, $parts) || (float) $parts[2] <= 0) {
                    throw new RuntimeException('Invalid model:unit-price row.');
                }
                $model = $parts[1];
                $protect = str_ends_with(mb_strtolower($model), '~protect');
                $code = $this->normalize(explode('~', $model)[0]);
                $pattern = '/(?<![\p{L}\p{N}-])'.preg_quote($code, '/').'(?![\p{L}\p{N}-])/u';
                $matches = $products->filter(function (Product $p) use ($pattern, $protect): bool {
                    return preg_match($pattern, $this->normalize($p->name.' '.$p->sku)) === 1
                        && (! $protect || str_contains(mb_strtolower($p->name), 'protect'));
                });
                $price = (float) $parts[2];
                $product = $matches->count() === 1 ? $matches->first() : null;
                $status = $matches->isEmpty() ? 'not_found' : ($product ? 'matched' : 'ambiguous');
                if ($product && isset($seen[$product->id])) {
                    $status = 'duplicate_product';
                }
                if ($product) {
                    $seen[$product->id] = true;
                }
                $rows[] = [
                    'supplier_code' => $supplier->code, 'supplier_article' => $model,
                    'product_id' => $product?->id, 'product_sku' => $product?->sku,
                    'product_name' => $product?->name,
                    'candidate_names' => $matches->pluck('name')->implode(' / '),
                    'source_unit_price' => $price,
                    'vat_rate' => (float) $vat, 'price_tax_mode' => $tax,
                    'purchase_price_byn' => round($price * ($tax === 'exclusive' ? 1 + $vat / 100 : 1), 2),
                    'retail_price_unchanged' => $product?->price,
                    'status' => $status,
                ];
            }
            $errors = count(array_filter($rows, fn ($row) => $row['status'] !== 'matched'));
            if ($this->option('apply') && $errors === 0) {
                $lock = Cache::lock('confirmed-purchase-import:'.$supplier->id, 120);
                if (! $lock->get()) {
                    throw new RuntimeException('Another purchase price import is running for this supplier.');
                }
                try {
                    DB::transaction(function () use (&$rows, $supplier, $document, $date, $hash, $tax, $vat): void {
                        foreach ($rows as &$row) {
                            $existing = ProductPurchasePriceSource::query()
                                ->where('product_id', $row['product_id'])->where('supplier_id', $supplier->id)
                                ->where('document_number', $document)->whereDate('document_date', $date)
                                ->lockForUpdate()->get();
                            if ($existing->isNotEmpty()) {
                                $same = $existing->count() === 1 && $existing->first()->is_active
                                    && (float) $existing->first()->source_price === $row['source_unit_price']
                                    && $existing->first()->currency === 'BYN'
                                    && $existing->first()->price_tax_mode === $tax
                                    && (float) $existing->first()->vat_rate === (float) $vat
                                    && (float) $existing->first()->price_byn === $row['purchase_price_byn']
                                    && str_contains((string) $existing->first()->notes, $hash);
                                if (! $same) {
                                    throw new RuntimeException('Conflicting price evidence for the same product/document. No prices were saved.');
                                }
                                $row['status'] = 'unchanged';

                                continue;
                            }
                            ProductPurchasePriceSource::query()->create([
                                'product_id' => $row['product_id'], 'supplier_id' => $supplier->id,
                                'source_type' => ProductPurchasePriceSource::TYPE_SUPPLIER_INVOICE,
                                'supplier_name' => $supplier->name, 'document_number' => $document,
                                'document_date' => $date, 'source_price' => $row['source_unit_price'],
                                'currency' => 'BYN', 'currency_rate' => 1, 'price_tax_mode' => $tax,
                                'vat_rate' => $vat, 'price_byn' => $row['purchase_price_byn'],
                                'notes' => 'Пакетный импорт по поручению менеджера. SHA-256 исходного файла: '.$hash.'. Модель: '.$row['supplier_article'].'. Наличие не подтверждено.',
                                'is_active' => true, 'confirmed_at' => now(),
                            ]);
                            $row['status'] = 'updated';
                        }
                    });
                } finally {
                    $lock->release();
                }
            }
            $directory = storage_path('app/reports/'.$supplier->code);
            File::ensureDirectoryExists($directory);
            $path = $directory.'/confirmed_purchase_'.($this->option('apply') ? 'apply' : 'preview').'_'.now()->format('Ymd_His').'.csv';
            $handle = fopen($path, 'wb');
            fputcsv($handle, array_keys($rows[0]), ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '');
            }
            fclose($handle);
            $this->table(['Model', 'Product', 'BYN incl. VAT', 'Status'], array_map(fn ($r) => [
                $r['supplier_article'], $r['product_id'], $r['purchase_price_byn'], $r['status'],
            ], $rows));
            $this->info('Report: '.$path);
            $this->info('Retail prices, inventory and supplier links were not modified.');
            if ($errors > 0) {
                $this->error('Unmatched/ambiguous/duplicate rows: '.$errors.'. No purchase prices were saved.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function normalize(string $value): string
    {
        return strtr(mb_strtoupper(str_replace(['–', '—', '‑'], '-', $value)), [
            'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M',
            'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T',
            'У' => 'Y', 'Х' => 'X', 'І' => 'I',
        ]);
    }
}
