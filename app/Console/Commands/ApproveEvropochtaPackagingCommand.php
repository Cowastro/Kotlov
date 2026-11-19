<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Shipping\EvropochtaPackaging;
use Illuminate\Console\Command;

class ApproveEvropochtaPackagingCommand extends Command
{
    protected $signature = 'evropochta:approve-packaging
        {--apply= : SHA-256 of the reviewed dry-run plan}
        {--exclude-category=* : Category slugs to leave for manual review}';

    protected $description = 'Preview or confirm shipping parcels from catalog measurements';

    public function handle(EvropochtaPackaging $packaging): int
    {
        $excluded = array_values(array_filter($this->option('exclude-category')));
        $candidates = Product::query()->active()->notArchived()
            ->whereNull('shipping_parcel')
            ->with(['attributeValues', 'category:id,name,slug'])
            ->when($excluded !== [], fn ($query) => $query->whereDoesntHave(
                'category', fn ($category) => $category->whereIn('slug', $excluded),
            ))
            ->lazyById(200);

        $plan = [];
        $categories = [];
        foreach ($candidates as $product) {
            $suggestion = $packaging->suggest($product);
            if ($suggestion === null) {
                continue;
            }

            $plan[$product->id] = $suggestion;
            $category = $product->category?->slug ?? 'uncategorized';
            $categories[$category]['count'] = ($categories[$category]['count'] ?? 0) + 1;
            if (count($categories[$category]['samples'] ?? []) < 4) {
                $categories[$category]['samples'][] = [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'box' => $suggestion['box'],
                    'weight' => $suggestion['weight'],
                ];
            }
        }

        $hash = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $this->line('packaging.candidates='.count($plan));
        $this->line('packaging.plan='.$hash);

        $expected = $this->option('apply');
        if ($expected === null) {
            ksort($categories);
            foreach ($categories as $slug => $category) {
                $this->line('packaging.category='.json_encode(
                    ['slug' => $slug] + $category, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
                ));
            }

            return self::SUCCESS;
        }

        if (! hash_equals($hash, $expected)) {
            $this->error('The catalog changed. Review the new dry-run plan before applying.');

            return self::FAILURE;
        }

        $approved = 0;
        foreach (array_chunk(array_keys($plan), 100) as $ids) {
            foreach (Product::query()->findMany($ids) as $product) {
                $approved += (int) $packaging->approve($product, $plan[$product->id]);
            }
        }

        $this->line('packaging.approved='.$approved);
        $this->line('packaging.skipped='.(count($plan) - $approved));

        return self::SUCCESS;
    }
}
