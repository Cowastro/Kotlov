<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\StoveCatalogClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyStoveCatalogProductsCommand extends Command
{
    private const TARGET_CATEGORY_ALIASES = [
        'drovyanye-pechi-dlya-bani' => [
            'drovyanye-pechi-dlya-bani',
            'drovianye-peci-bannye',
            'pechi-dlya-bani',
        ],
        'mangalyi' => ['mangalyi'],
        'aksessuary-kaminy' => ['aksessuary-kaminy'],
    ];

    protected $signature = 'catalog:classify-stove-products
        {--apply : Move unambiguous non-heating products; the default is a dry run}
        {--sample=50 : Number of planned moves to show}';

    protected $description = 'Move clearly misplaced products out of the heating-stove category';

    public function handle(StoveCatalogClassifier $classifier): int
    {
        $source = Category::query()->where('slug', 'pechi-kaminy')->first();

        if (! $source) {
            $this->error('The pechi-kaminy category was not found.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->where('category_id', $source->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'category_id']);

        $plans = $products
            ->map(function (Product $product) use ($classifier) {
                $targetSlug = $classifier->targetCategorySlug($product->name);

                return $targetSlug ? ['product' => $product, 'target_slug' => $targetSlug] : null;
            })
            ->filter()
            ->values();

        $targetSlugs = $plans
            ->pluck('target_slug')
            ->unique()
            ->flatMap(fn (string $slug) => self::TARGET_CATEGORY_ALIASES[$slug] ?? [$slug])
            ->unique()
            ->values();
        $targets = Category::query()
            ->whereIn('slug', $targetSlugs)
            ->get()
            ->keyBy('slug');
        $resolvedTargets = $plans
            ->pluck('target_slug')
            ->unique()
            ->mapWithKeys(function (string $slug) use ($targets) {
                $aliases = self::TARGET_CATEGORY_ALIASES[$slug] ?? [$slug];
                $category = collect($aliases)
                    ->map(fn (string $alias) => $targets->get($alias))
                    ->first();

                return [$slug => $category];
            });
        $missingTargets = $resolvedTargets
            ->filter(fn ($category) => $category === null)
            ->keys()
            ->values();

        $this->table(['Metric', 'Count'], [
            ['products_checked', $products->count()],
            ['planned_moves', $plans->count()],
            ['missing_target_categories', $missingTargets->count()],
        ]);
        $this->line('Mode: '.($this->option('apply') ? 'APPLY' : 'DRY RUN'));
        $this->line('Only unambiguous product types are classified; prices, stock, images and characteristics are untouched.');

        if ($plans->isNotEmpty() && (int) $this->option('sample') > 0) {
            $this->table(
                ['ID', 'Product', 'Slug', 'From', 'To'],
                $plans->take(max(0, (int) $this->option('sample')))->map(fn (array $plan) => [
                    $plan['product']->id,
                    $plan['product']->name,
                    $plan['product']->slug,
                    $source->slug,
                    $resolvedTargets[$plan['target_slug']]?->slug ?? $plan['target_slug'],
                ])->all()
            );
        }

        if ($missingTargets->isNotEmpty()) {
            $this->error('Missing target categories: '.$missingTargets->implode(', '));
            $this->line('No products were changed.');

            return self::FAILURE;
        }

        if ($this->option('apply') && $plans->isNotEmpty()) {
            DB::transaction(function () use ($plans, $resolvedTargets) {
                foreach ($plans as $plan) {
                    $plan['product']->update([
                        'category_id' => $resolvedTargets[$plan['target_slug']]->id,
                    ]);
                }
            });

            $this->info('Moved products: '.$plans->count());
        }

        return self::SUCCESS;
    }
}
