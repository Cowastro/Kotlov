<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class HoldUnlinkedCatalogProductsCommand extends Command
{
    protected $signature = 'catalog:hold-unlinked-products
        {--apply : Hide unverified public prices and put products on availability request}
        {--brand-summary : Print an aggregate brand ranking without product or price data}
        {--brand-limit=100 : Maximum number of brands in the aggregate ranking}';

    protected $description = 'Put active products without an operational supplier or price source on a safe consultation hold.';

    public function handle(): int
    {
        $candidateIds = [];
        $brandCounts = [];
        $metrics = [
            'active_products_without_supplier' => 0,
            'with_stored_price' => 0,
            'already_held' => 0,
            'marked_in_stock' => 0,
            'status_not_check' => 0,
            'products_updated' => 0,
        ];

        Product::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'brand:id,name',
                'supplierProducts.supplier.latestChannelTransition',
                'integrationProducts.source.supplier',
                'purchasePriceSources.supplier',
            ])
            ->orderBy('id')
            ->chunkById(250, function ($products) use (&$candidateIds, &$metrics, &$brandCounts): void {
                foreach ($products as $product) {
                    if ($product->hasAssignedSupplier()) {
                        continue;
                    }

                    $candidateIds[] = (int) $product->id;
                    $brand = trim((string) ($product->brand?->name ?? '')) ?: 'Без бренда';
                    $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                    $metrics['active_products_without_supplier']++;
                    $metrics['with_stored_price'] += (float) $product->price > 0 ? 1 : 0;
                    $metrics['already_held'] += $product->requires_price_confirmation ? 1 : 0;
                    $metrics['marked_in_stock'] += $product->in_stock ? 1 : 0;
                    $metrics['status_not_check'] += $product->availability_status !== Product::AVAILABILITY_CHECK ? 1 : 0;
                }
            });

        if ($this->option('apply')) {
            foreach (array_chunk($candidateIds, 500) as $ids) {
                $metrics['products_updated'] += Product::query()
                    ->whereIntegerInRaw('id', $ids)
                    ->where(function ($query): void {
                        $query->where('requires_price_confirmation', false)
                            ->orWhere('in_stock', true)
                            ->orWhere('availability_status', '!=', Product::AVAILABILITY_CHECK)
                            ->orWhereNull('availability_status');
                    })
                    ->update([
                        'requires_price_confirmation' => true,
                        'in_stock' => false,
                        'availability_status' => Product::AVAILABILITY_CHECK,
                        'updated_at' => now(),
                    ]);
            }
        }

        $this->line('metric.status='.($this->option('apply') ? 'applied' : 'dry_run'));
        foreach ($metrics as $name => $value) {
            $this->line("metric.{$name}={$value}");
        }

        if ((bool) $this->option('brand-summary')) {
            arsort($brandCounts, SORT_NUMERIC);
            $limit = max(1, min(500, (int) $this->option('brand-limit')));
            $rank = 0;

            foreach (array_slice($brandCounts, 0, $limit, true) as $brand => $count) {
                $rank++;
                $safeBrand = trim((string) preg_replace('/[\x00-\x1F\x7F|]+/u', ' ', $brand));
                $this->line("brand_unlinked.{$rank}={$safeBrand}|{$count}");
            }

            $this->line('metric.unlinked_brands='.count($brandCounts));
        }

        return self::SUCCESS;
    }
}
