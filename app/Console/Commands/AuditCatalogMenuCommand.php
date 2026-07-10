<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditCatalogMenuCommand extends Command
{
    protected $signature = 'catalog:audit-menu
        {--root= : Root category slug or id to audit}
        {--empty-only : Show only categories with no orderable products in the whole branch}
        {--limit=200 : Max rows to show, 0 means no limit}';

    protected $description = 'Audit public catalog menu categories against orderable product counts.';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $emptyOnly = (bool) $this->option('empty-only');

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'parent_id', 'name', 'slug', 'sort_order']);

        $categoriesByParent = $categories->groupBy('parent_id');
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

        $root = $this->resolveRoot($categories);
        $roots = $root ? collect([$root]) : $categoriesByParent->get(0, collect());

        $rows = [];
        foreach ($roots as $category) {
            $this->appendCategoryRows($rows, $category, 0, $categoriesByParent, $directProductCounts, $countBranchProducts, $emptyOnly, $limit);
        }

        $emptyBranches = $categories
            ->filter(fn (Category $category) => $countBranchProducts((int) $category->id) === 0)
            ->count();

        $this->table(['metric', 'count'], [
            ['active_categories', $categories->count()],
            ['empty_active_branches', $emptyBranches],
            ['shown_rows', count($rows)],
        ]);

        if ($rows !== []) {
            $this->table(
                ['depth', 'id', 'parent', 'slug', 'name', 'direct', 'branch', 'children'],
                $rows
            );
        }

        return self::SUCCESS;
    }

    private function resolveRoot(Collection $categories): ?Category
    {
        $root = trim((string) $this->option('root'));

        if ($root === '') {
            return null;
        }

        return $categories->first(function (Category $category) use ($root) {
            return (string) $category->id === $root || $category->slug === $root;
        });
    }

    private function appendCategoryRows(
        array &$rows,
        Category $category,
        int $depth,
        Collection $categoriesByParent,
        Collection $directProductCounts,
        callable $countBranchProducts,
        bool $emptyOnly,
        int $limit
    ): void {
        if ($limit > 0 && count($rows) >= $limit) {
            return;
        }

        $children = $categoriesByParent->get($category->id, collect());
        $directCount = (int) ($directProductCounts[$category->id] ?? 0);
        $branchCount = $countBranchProducts((int) $category->id);

        if (! $emptyOnly || $branchCount === 0) {
            $rows[] = [
                $depth,
                $category->id,
                $category->parent_id,
                $category->slug,
                mb_strimwidth((string) $category->name, 0, 48, '...'),
                $directCount,
                $branchCount,
                $children->count(),
            ];
        }

        foreach ($children as $child) {
            $this->appendCategoryRows($rows, $child, $depth + 1, $categoriesByParent, $directProductCounts, $countBranchProducts, $emptyOnly, $limit);
        }
    }
}
