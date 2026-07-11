<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeactivateEmptyCatalogCategoriesCommand extends Command
{
    protected $signature = 'catalog:deactivate-empty-categories
        {--apply : Write is_active=false for empty active category branches}
        {--root= : Limit by root category slug or id}
        {--limit=200 : Max rows to show, 0 means no limit}';

    protected $description = 'Deactivate active catalog categories whose whole branch has no orderable products.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'slug', 'name', 'is_active']);

        $root = $this->resolveRoot($categories);
        $categoriesByParent = $categories->groupBy(fn (Category $category) => (int) $category->parent_id);
        $directProductCounts = Product::query()
            ->orderable()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $branchCounts = [];
        $countBranchProducts = function (int $categoryId) use (&$countBranchProducts, &$branchCounts, $categoriesByParent, $directProductCounts): int {
            if (array_key_exists($categoryId, $branchCounts)) {
                return $branchCounts[$categoryId];
            }

            $count = (int) ($directProductCounts[$categoryId] ?? 0);
            foreach ($categoriesByParent->get($categoryId, collect()) as $child) {
                $count += $countBranchProducts((int) $child->id);
            }

            return $branchCounts[$categoryId] = $count;
        };

        $candidateIds = [];
        $rows = [];
        $roots = $root ? collect([$root]) : $categories->where('parent_id', 0)->values();

        foreach ($roots as $category) {
            $this->appendRows($rows, $candidateIds, $category, 0, $categoriesByParent, $directProductCounts, $countBranchProducts, $limit);
        }

        $this->line($apply ? 'APPLY: empty active catalog categories will be deactivated.' : 'DRY RUN: database will not be changed.');
        $this->table([
            'metric',
            'count',
        ], [
            ['active_categories', $categories->count()],
            ['empty_branches', count($candidateIds)],
            ['shown_rows', count($rows)],
        ]);

        if ($rows !== []) {
            $this->table(['depth', 'id', 'parent', 'slug', 'name', 'direct', 'branch', 'children'], $rows);
        }

        if ($apply && $candidateIds !== []) {
            DB::table('categories')
                ->whereIn('id', array_values(array_unique($candidateIds)))
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);

            $this->info('Deactivated categories: ' . count(array_unique($candidateIds)));
        } elseif (! $apply && $candidateIds !== []) {
            $this->line('Re-run with --apply to deactivate these empty category branches.');
        }

        return self::SUCCESS;
    }

    private function resolveRoot(Collection $categories): ?Category
    {
        $root = trim((string) $this->option('root'));
        if ($root === '') {
            return null;
        }

        return $categories->first(function (Category $category) use ($root): bool {
            return (string) $category->slug === $root || (string) $category->id === $root;
        });
    }

    private function appendRows(
        array &$rows,
        array &$candidateIds,
        Category $category,
        int $depth,
        Collection $categoriesByParent,
        Collection $directProductCounts,
        callable $countBranchProducts,
        int $limit
    ): void {
        $children = $categoriesByParent->get($category->id, collect());
        $branchCount = $countBranchProducts((int) $category->id);

        if ($branchCount === 0) {
            $candidateIds[] = (int) $category->id;

            if ($limit === 0 || count($rows) < $limit) {
                $rows[] = [
                    $depth,
                    $category->id,
                    $category->parent_id,
                    $category->slug,
                    mb_strimwidth((string) $category->name, 0, 40, '...'),
                    (int) ($directProductCounts[$category->id] ?? 0),
                    $branchCount,
                    $children->count(),
                ];
            }
        }

        foreach ($children as $child) {
            $this->appendRows($rows, $candidateIds, $child, $depth + 1, $categoriesByParent, $directProductCounts, $countBranchProducts, $limit);
        }
    }
}
