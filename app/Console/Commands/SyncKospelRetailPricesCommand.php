<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncKospelRetailPricesCommand extends Command
{
    private const SOURCE_PATH = 'imports/kospel-2026-09-23.json';

    protected $signature = 'supplier:sync-kospel-retail
        {--apply : Update matched product prices and supplier links}
        {--report : Write the full matching report to storage/app/imports/kospel-matching-report.json}';

    protected $description = 'Strictly match Kospel price-list models and sync EUR retail prices converted to BYN';

    public function handle(): int
    {
        try {
            $source = $this->loadSource();
            $supplier = Supplier::query()->where('code', 'kospel')->firstOrFail();
            $brand = Brand::query()->whereRaw('LOWER(name) = ?', ['kospel'])->firstOrFail();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rate = (float) $supplier->currency_rate;
        if ($rate <= 0) {
            $this->error('Kospel supplier currency rate must be greater than zero.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->orderBy('id')
            ->get(['id', 'sku', 'name', 'price']);

        $report = $this->buildReport($source, $products, $rate);
        $this->renderSummary($source, $supplier, $rate, $report);

        if ($this->option('report')) {
            file_put_contents(
                storage_path('app/imports/kospel-matching-report.json'),
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
            $this->line('Report: storage/app/imports/kospel-matching-report.json');
        }

        if (! $this->option('apply')) {
            $this->warn('[DRY-RUN] No database records were changed.');

            return self::SUCCESS;
        }

        if ($report['ambiguous'] !== [] || $report['conflicts'] !== []) {
            $this->error('Apply aborted: unresolved ambiguous or conflicting matches remain.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($report, $supplier, $rate, $source) {
            foreach ($report['matched'] as $row) {
                Product::query()->whereKey($row['product_id'])->update([
                    'price' => $row['price_byn'],
                ]);

                $article = $row['model'];
                SupplierProduct::query()->updateOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'supplier_article' => $article,
                    ],
                    [
                        'product_id' => $row['product_id'],
                        'product_sku' => $row['product_sku'],
                        'supplier_article_normalized' => $this->normalizeArticle($article),
                        'supplier_article_compact' => $this->canonical($article),
                        'supplier_name' => $row['product_name'],
                        'price' => $row['price_eur'],
                        'currency' => 'EUR',
                        'currency_rate' => $rate,
                        'price_byn' => $row['price_byn'],
                        'match_status' => 'matched',
                        'match_confidence' => $row['duplicate_model'] ? 'exact_model_duplicate' : 'exact_model',
                        'raw' => [
                            'source_file' => $source['source_file'],
                            'price_date' => $source['price_date'],
                            'sheet' => $row['sheet'],
                            'row' => $row['source_row'],
                            'price_cell' => $row['price_cell'],
                            'price_status' => $row['status'],
                        ],
                        'last_synced_at' => now(),
                    ],
                );
            }
        });

        $this->info(sprintf(
            'Applied %d matched prices (%d changed, %d already current).',
            count($report['matched']),
            $report['changed_count'],
            $report['unchanged_count'],
        ));

        return self::SUCCESS;
    }

    private function loadSource(): array
    {
        $path = storage_path('app/'.self::SOURCE_PATH);
        if (! is_file($path)) {
            throw new RuntimeException('Missing source file: storage/app/'.self::SOURCE_PATH);
        }

        $source = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! isset($source['rows']) || ! is_array($source['rows'])) {
            throw new RuntimeException('Invalid Kospel source JSON: rows are missing.');
        }

        return $source;
    }

    private function buildReport(array $source, $products, float $rate): array
    {
        $matched = [];
        $unmatched = [];
        $ambiguous = [];
        $duplicateModels = [];
        $conflicts = [];
        $claimedProducts = [];

        foreach ($source['rows'] as $sourceRow) {
            $model = trim((string) ($sourceRow['model'] ?? ''));
            $priceEur = (float) ($sourceRow['price_eur'] ?? 0);
            $modelKey = $this->canonical($model);

            if ($model === '' || $priceEur <= 0 || $modelKey === '') {
                $unmatched[] = $this->sourceIdentity($sourceRow) + ['reason' => 'invalid_source_row'];
                continue;
            }

            $candidates = $products->filter(
                fn (Product $product) => str_contains($this->canonical($product->name), $modelKey),
            )->values();

            if ($candidates->isEmpty()) {
                $unmatched[] = $this->sourceIdentity($sourceRow) + ['reason' => 'no_exact_model'];
                continue;
            }

            $candidates = $candidates
                ->reject(fn (Product $product) => $this->isDifferentVariant($model, $product->name))
                ->values();

            if ($candidates->isEmpty()) {
                $unmatched[] = $this->sourceIdentity($sourceRow) + ['reason' => 'only_different_variants'];
                continue;
            }

            $isDuplicateModel = $candidates->count() > 1;
            if ($isDuplicateModel) {
                $duplicateModels[] = $this->sourceIdentity($sourceRow) + [
                    'candidates' => $candidates->map(fn (Product $product) => [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                    ])->all(),
                ];
            }

            foreach ($candidates as $product) {
                if (isset($claimedProducts[$product->id])) {
                    $conflicts[] = $this->sourceIdentity($sourceRow) + [
                        'reason' => 'product_already_claimed',
                        'product_id' => $product->id,
                        'claimed_by' => $claimedProducts[$product->id],
                    ];
                    continue;
                }
                $claimedProducts[$product->id] = $model;

                $priceByn = round($priceEur * $rate, 2);
                $oldPrice = round((float) $product->price, 2);
                $matched[] = $this->sourceIdentity($sourceRow) + [
                    'product_id' => $product->id,
                    'product_sku' => $product->sku,
                    'product_name' => $product->name,
                    'price_eur' => $priceEur,
                    'old_price_byn' => $oldPrice,
                    'price_byn' => $priceByn,
                    'changed' => abs($oldPrice - $priceByn) >= 0.01,
                    'duplicate_model' => $isDuplicateModel,
                ];
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'currency_rate' => $rate,
            'matched_count' => count($matched),
            'changed_count' => count(array_filter($matched, fn (array $row) => $row['changed'])),
            'unchanged_count' => count(array_filter($matched, fn (array $row) => ! $row['changed'])),
            'unmatched_count' => count($unmatched),
            'ambiguous_count' => count($ambiguous),
            'duplicate_model_count' => count($duplicateModels),
            'conflict_count' => count($conflicts),
            'matched' => $matched,
            'unmatched' => $unmatched,
            'ambiguous' => $ambiguous,
            'duplicate_models' => $duplicateModels,
            'conflicts' => $conflicts,
        ];
    }

    private function renderSummary(array $source, Supplier $supplier, float $rate, array $report): void
    {
        $this->info(sprintf(
            'Kospel retail price sync: %s, %d numeric rows, rate %.4f BYN/EUR.',
            $source['price_date'] ?? 'unknown date',
            count($source['rows']),
            $rate,
        ));
        $this->table(['supplier', 'matched cards', 'changed', 'current', 'unmatched models', 'duplicate models', 'ambiguous', 'conflicts'], [[
            $supplier->name,
            $report['matched_count'],
            $report['changed_count'],
            $report['unchanged_count'],
            $report['unmatched_count'],
            $report['duplicate_model_count'],
            $report['ambiguous_count'],
            $report['conflict_count'],
        ]]);

        if ($report['matched'] !== []) {
            $this->table(
                ['model', 'product id', 'product', 'EUR', 'old BYN', 'new BYN', 'state'],
                array_map(fn (array $row) => [
                    $row['model'],
                    $row['product_id'],
                    $row['product_name'],
                    number_format($row['price_eur'], 2, '.', ''),
                    number_format($row['old_price_byn'], 2, '.', ''),
                    number_format($row['price_byn'], 2, '.', ''),
                    $row['changed'] ? 'change' : 'current',
                ], $report['matched']),
            );
        }

        foreach (['unmatched', 'ambiguous', 'conflicts'] as $section) {
            if ($report[$section] !== []) {
                $this->warn(strtoupper($section).': '.implode(', ', array_column($report[$section], 'model')));
            }
        }

        if ($report['ambiguous'] !== []) {
            $rows = [];
            foreach ($report['ambiguous'] as $sourceRow) {
                foreach ($sourceRow['candidates'] as $candidate) {
                    $rows[] = [
                        $sourceRow['model'],
                        $candidate['id'],
                        $candidate['sku'],
                        $candidate['name'],
                    ];
                }
            }
            $this->table(['source model', 'product id', 'sku', 'candidate'], $rows);
        }

        if ($report['duplicate_models'] !== []) {
            $rows = [];
            foreach ($report['duplicate_models'] as $sourceRow) {
                foreach ($sourceRow['candidates'] as $candidate) {
                    $rows[] = [$sourceRow['model'], $candidate['id'], $candidate['sku'], $candidate['name']];
                }
            }
            $this->warn('Exact duplicate product cards will all receive the same current price:');
            $this->table(['source model', 'product id', 'sku', 'duplicate card'], $rows);
        }
    }

    private function sourceIdentity(array $row): array
    {
        return [
            'sheet' => $row['sheet'] ?? null,
            'source_row' => $row['row'] ?? null,
            'model' => trim((string) ($row['model'] ?? '')),
            'price_cell' => $row['price_cell'] ?? null,
            'status' => $row['status'] ?? null,
        ];
    }

    private function canonical(string $value): string
    {
        preg_match_all('/[\pL\pN]+/u', mb_strtoupper($value), $matches);

        return implode('', array_map(
            static function (string $token): string {
                if (preg_match('/^\d+$/', $token)) {
                    return (string) ((int) $token);
                }

                return $token;
            },
            $matches[0] ?? [],
        ));
    }

    private function normalizeArticle(string $article): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $article) ?? $article));
    }

    private function isDifferentVariant(string $sourceModel, string $productName): bool
    {
        if (preg_match('/^EPS2-/i', $sourceModel) && ! preg_match('/P$/i', $sourceModel)) {
            return (bool) preg_match('/EPS2[\s._-]*\d+(?:[.,]\d+)?P\b/ui', $productName);
        }

        return false;
    }
}
