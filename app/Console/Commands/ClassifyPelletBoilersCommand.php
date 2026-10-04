<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\PelletBoilerCatalogClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyPelletBoilersCommand extends Command
{
    protected $signature = 'catalog:classify-pellet-boilers
        {--apply : Move unambiguous pellet boilers; the default is a dry run}
        {--sample=100 : Number of products to show}';

    protected $description = 'Move clearly identified pellet boilers into the pellet-boiler category';

    public function handle(PelletBoilerCatalogClassifier $classifier): int
    {
        $target = Category::query()
            ->where('slug', 'kotly-na-pelletah')
            ->where('is_active', true)
            ->first();

        if (! $target) {
            $this->error('The active kotly-na-pelletah category was not found.');

            return self::FAILURE;
        }

        $candidates = Product::query()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function ($query) {
                $query->where('name', 'like', '%пеллет%')
                    ->orWhere('name', 'like', '%Пеллет%')
                    ->orWhere('name', 'like', '%PELLET%')
                    ->orWhere('name', 'like', '%Pellet%');
            })
            ->with('category:id,name,slug')
            ->orderBy('id')
            ->get(['id', 'category_id', 'name', 'slug', 'price', 'in_stock']);

        $pelletBoilers = $candidates
            ->filter(fn (Product $product) => $classifier->isPelletBoiler($product->name))
            ->values();
        $plans = $pelletBoilers
            ->filter(fn (Product $product) => $product->category_id !== $target->id)
            ->values();

        $this->table(['Metric', 'Count'], [
            ['pellet_named_products_checked', $candidates->count()],
            ['unambiguous_pellet_boilers', $pelletBoilers->count()],
            ['already_in_target_category', $pelletBoilers->count() - $plans->count()],
            ['planned_moves', $plans->count()],
        ]);
        $this->line('Mode: '.($this->option('apply') ? 'APPLY' : 'DRY RUN'));
        $this->line('Only active, non-archived products explicitly named as pellet boilers are eligible.');

        if ($pelletBoilers->isNotEmpty() && (int) $this->option('sample') > 0) {
            $this->table(
                ['ID', 'Product', 'Current category', 'Target', 'Stock', 'Price'],
                $pelletBoilers->take(max(0, (int) $this->option('sample')))
                    ->map(fn (Product $product) => [
                        $product->id,
                        $product->name,
                        $product->category?->slug,
                        $target->slug,
                        $product->in_stock ? 'yes' : 'no',
                        $product->price,
                    ])->all()
            );
        }

        if ($this->option('apply') && $plans->isNotEmpty()) {
            DB::transaction(function () use ($plans, $target) {
                Product::query()
                    ->whereIn('id', $plans->pluck('id'))
                    ->update(['category_id' => $target->id]);
            });

            $this->info('Moved pellet boilers: '.$plans->count());
        }

        return self::SUCCESS;
    }
}
