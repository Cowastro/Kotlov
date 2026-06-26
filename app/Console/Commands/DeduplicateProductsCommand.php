<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeduplicateProductsCommand extends Command
{
    protected $signature = 'products:deduplicate
        {--apply : Archive duplicates and transfer data (default: dry-run)}
        {--brand= : Filter by brand name or slug}
        {--prefer-supplier= : Prefer products linked to this supplier code/name}
        {--require-preferred-keep : Skip duplicate groups unless the kept product is linked to --prefer-supplier}
        {--only-unbound : Archive only duplicate products without supplier links}
        {--fix-slugs : Move the cleaner/base slug to the kept product}
        {--model-family : Group products by normalized model family instead of exact normalized name}
        {--limit= : Limit duplicate groups}';

    protected $description = 'Find duplicate products, prefer supplier-linked cards, transfer useful data, and archive duplicates.';

    public function handle(): int
    {
        $apply           = (bool) $this->option('apply');
        $brandFilter     = $this->option('brand');
        $preferredFilter = $this->option('prefer-supplier');
        $requirePreferredKeep = (bool) $this->option('require-preferred-keep');
        $onlyUnbound     = (bool) $this->option('only-unbound');
        $fixSlugs        = (bool) $this->option('fix-slugs');
        $limit           = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;

        $this->line($apply
            ? '<fg=red;options=bold>APPLY - duplicates will be archived.</>'
            : '<fg=yellow;options=bold>DRY RUN - nothing will be changed.</>');

        $products = $this->loadProducts($brandFilter);

        if ($products->isEmpty()) {
            $this->info('No products found for the selected scope.');
            return self::SUCCESS;
        }

        $supplierLinks = $this->loadSupplierLinks($products->pluck('id')->all());

        $groups = $products
            ->groupBy(fn ($product) => $product->brand_id . '|' . $this->duplicateKey($product->name))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();

        if ($limit) {
            $groups = $groups->take($limit);
        }

        if ($groups->isEmpty()) {
            $this->info('No duplicates found.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d duplicate groups.', $groups->count()));

        $stats = [
            'groups'             => 0,
            'archived'           => 0,
            'skipped_bound_dupes' => 0,
            'supplier_moved'     => 0,
            'supplier_removed'   => 0,
            'attrs_moved'        => 0,
            'fields_copied'      => 0,
            'slugs_fixed'        => 0,
            'redirects_created'  => 0,
            'skipped_no_preferred_keep' => 0,
        ];

        foreach ($groups as $group) {
            /** @var \stdClass $keep */
            $keep = $this->selectProductToKeep($group, $supplierLinks, $preferredFilter);

            if ($requirePreferredKeep && $preferredFilter && ! $this->hasPreferredSupplierLink((int) $keep->id, $supplierLinks, $preferredFilter)) {
                $stats['skipped_no_preferred_keep']++;
                continue;
            }

            $dupes = $group->reject(fn ($product) => (int) $product->id === (int) $keep->id)->values();

            if ($onlyUnbound) {
                $boundDupes = $dupes->filter(fn ($product) => $this->hasAnySupplierLink((int) $product->id, $supplierLinks));
                $stats['skipped_bound_dupes'] += $boundDupes->count();
                $dupes = $dupes->reject(fn ($product) => $this->hasAnySupplierLink((int) $product->id, $supplierLinks))->values();
            }

            if ($dupes->isEmpty()) {
                continue;
            }

            $stats['groups']++;
            $keepLinks = $supplierLinks->get((int) $keep->id, collect());
            $keepLabel = $this->productLabel($keep, $keepLinks);

            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>KEEP %s</> | archive: %s',
                $keepLabel,
                $dupes->map(fn ($product) => $this->productLabel($product, $supplierLinks->get((int) $product->id, collect())))->implode(' ; ')
            ));

            if ($fixSlugs) {
                $slugResult = $this->fixSlug($keep, $dupes, $apply);
                $stats['slugs_fixed'] += $slugResult['fixed'];
                $stats['redirects_created'] += $slugResult['redirects'];
            }

            foreach ($dupes as $dupe) {
                $transferStats = $this->transferSupplierLinks((int) $dupe->id, (int) $keep->id, $apply);
                $stats['supplier_moved'] += $transferStats['moved'];
                $stats['supplier_removed'] += $transferStats['removed'];

                $stats['attrs_moved'] += $this->transferAttributeValues((int) $dupe->id, (int) $keep->id, $apply);
                $stats['fields_copied'] += $this->copyMissingProductFields((int) $dupe->id, (int) $keep->id, $apply);

                $this->line(sprintf('  -> archive id=%d sku=%s slug=%s', $dupe->id, $dupe->sku ?: '-', $dupe->slug ?: '-'));

                if ($apply) {
                    DB::table('products')->where('id', $dupe->id)->update([
                        'is_archived' => true,
                        'is_active'   => false,
                        'updated_at'  => now(),
                    ]);
                }

                $stats['archived']++;
            }
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            array_map(fn ($key, $value) => [$key, $value], array_keys($stats), array_values($stats))
        );

        if (! $apply && $stats['archived'] > 0) {
            $this->info('Re-run with --apply to archive ' . $stats['archived'] . ' duplicates.');
        }

        return self::SUCCESS;
    }

    private function loadProducts(?string $brandFilter): Collection
    {
        return DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('p.is_archived', false)
            ->when($brandFilter, function ($query) use ($brandFilter) {
                $needle = mb_strtolower(trim($brandFilter));

                $query->where(function ($scope) use ($brandFilter, $needle) {
                    $scope
                        ->where('b.name', 'like', '%' . $brandFilter . '%')
                        ->orWhere('b.slug', 'like', '%' . $needle . '%');
                });
            })
            ->select([
                'p.id',
                'p.brand_id',
                'p.category_id',
                'p.name',
                'p.slug',
                'p.sku',
                'p.images',
                'p.short_description',
                'p.content',
                'p.specs',
                'p.service_info',
                'p.documents',
                'p.video_url',
                'p.created_at',
                'p.updated_at',
                'b.name as brand_name',
                'b.slug as brand_slug',
            ])
            ->orderBy('p.brand_id')
            ->orderBy('p.name')
            ->get()
            ->filter(fn ($product) => $this->duplicateKey($product->name) !== '');
    }

    private function loadSupplierLinks(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        return DB::table('supplier_products as sp')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->whereIn('sp.product_id', $productIds)
            ->select([
                'sp.id',
                'sp.product_id',
                'sp.supplier_id',
                'sp.supplier_article',
                'sp.supplier_name',
                's.code as supplier_code',
                's.name as supplier_title',
            ])
            ->get()
            ->groupBy('product_id');
    }

    private function duplicateKey(?string $name): string
    {
        if ((bool) $this->option('model-family')) {
            return $this->modelFamilyDuplicateKey($name);
        }

        $name = mb_strtolower((string) $name);
        $name = str_replace(["\xc2\xa0", 'ё'], [' ', 'е'], $name);
        $name = preg_replace('/[^a-zа-я0-9]+/u', ' ', $name) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    private function modelFamilyDuplicateKey(?string $name): string
    {
        $name = mb_strtolower((string) $name);
        $name = str_replace(["\xc2\xa0", '+'], [' ', ' plus '], $name);

        preg_match_all('/[a-z0-9]+/u', $name, $matches);

        $drop = array_fill_keys([
            'ariston',
            'buderus',
            'fondital',
            'thermex',
            'viessmann',
            'vissman',
            'navien',
            'kiturami',
            'kotlov',
            'system',
            'new',
            'ng',
            'ff',
            'gas',
            'boiler',
            'kotel',
            'kotyol',
        ], true);

        $tokens = array_values(array_filter(
            $matches[0] ?? [],
            fn (string $token) => ! isset($drop[$token])
        ));

        return implode('', $tokens);
    }

    private function selectProductToKeep(Collection $group, Collection $supplierLinks, ?string $preferredFilter): object
    {
        return $group
            ->sortByDesc(fn ($product) => $this->scoreProduct($product, $supplierLinks, $preferredFilter))
            ->first();
    }

    private function scoreProduct(object $product, Collection $supplierLinks, ?string $preferredFilter): int
    {
        $productId = (int) $product->id;
        $score = $this->completenessScore($productId, $product);

        if ($this->hasPreferredSupplierLink($productId, $supplierLinks, $preferredFilter)) {
            $score += 1_000_000;
        } elseif ($this->hasAnySupplierLink($productId, $supplierLinks)) {
            $score += 500_000;
        }

        return $score;
    }

    private function completenessScore(int $productId, object $product): int
    {
        $attrCount = DB::table('product_attribute_values')->where('product_id', $productId)->count();
        $imgCount = count($this->decodeArray($product->images ?? null));
        $specCount = count($this->decodeArray($product->specs ?? null));

        return ($attrCount * 2)
            + ($imgCount * 3)
            + $specCount
            + (empty($product->short_description) ? 0 : 5)
            + (empty($product->content) ? 0 : 5)
            + (empty($product->video_url) ? 0 : 2);
    }

    private function hasPreferredSupplierLink(int $productId, Collection $supplierLinks, ?string $preferredFilter): bool
    {
        if (! $preferredFilter) {
            return $this->hasAnySupplierLink($productId, $supplierLinks);
        }

        $needle = $this->normalizeSupplierName($preferredFilter);

        return $supplierLinks
            ->get($productId, collect())
            ->contains(function ($link) use ($needle) {
                $values = [
                    $link->supplier_code ?? '',
                    $link->supplier_title ?? '',
                    $link->supplier_name ?? '',
                ];

                foreach ($values as $value) {
                    if (str_contains($this->normalizeSupplierName($value), $needle)) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function hasAnySupplierLink(int $productId, Collection $supplierLinks): bool
    {
        return $supplierLinks->get($productId, collect())->isNotEmpty();
    }

    private function normalizeSupplierName(?string $value): string
    {
        $value = mb_strtolower((string) $value);

        return preg_replace('/[^a-zа-я0-9]+/u', '', $value) ?? '';
    }

    private function productLabel(object $product, Collection $links): string
    {
        $suppliers = $links
            ->map(fn ($link) => $link->supplier_code ?: $link->supplier_title ?: $link->supplier_name)
            ->filter()
            ->unique()
            ->implode(',');

        return sprintf(
            'id=%d sku=%s slug=%s supplier=%s',
            $product->id,
            $product->sku ?: '-',
            $product->slug ?: '-',
            $suppliers ?: '-'
        );
    }

    private function fixSlug(object $keep, Collection $dupes, bool $apply): array
    {
        $bestSlug = $this->selectBestSlug(collect([$keep])->merge($dupes));

        if (! $bestSlug || $bestSlug === $keep->slug) {
            return ['fixed' => 0, 'redirects' => 0];
        }

        $holder = DB::table('products')->where('slug', $bestSlug)->first(['id', 'slug']);

        if (! $holder || ! $dupes->pluck('id')->map(fn ($id) => (int) $id)->contains((int) $holder->id)) {
            $this->warn(sprintf('  -> skip slug move, slug %s is not owned by a duplicate in this group', $bestSlug));
            return ['fixed' => 0, 'redirects' => 0];
        }

        $oldKeepSlug = $keep->slug;
        $archivedSlug = $bestSlug . '-archived-' . $holder->id;

        $this->line(sprintf('  -> move slug %s to keep id=%d; duplicate id=%d becomes %s', $bestSlug, $keep->id, $holder->id, $archivedSlug));

        if ($apply) {
            DB::table('products')->where('id', $holder->id)->update([
                'slug'       => $archivedSlug,
                'updated_at' => now(),
            ]);

            DB::table('products')->where('id', $keep->id)->update([
                'slug'       => $bestSlug,
                'updated_at' => now(),
            ]);

            if ($oldKeepSlug && $oldKeepSlug !== $bestSlug) {
                DB::table('redirects')->updateOrInsert(
                    ['from_url' => '/' . ltrim($oldKeepSlug, '/')],
                    [
                        'to_url'      => '/' . ltrim($bestSlug, '/'),
                        'status_code' => 301,
                        'is_active'   => true,
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
            }
        }

        return ['fixed' => 1, 'redirects' => $oldKeepSlug && $oldKeepSlug !== $bestSlug ? 1 : 0];
    }

    private function selectBestSlug(Collection $products): ?string
    {
        return $products
            ->pluck('slug')
            ->filter()
            ->unique()
            ->sortByDesc(fn ($slug) => $this->slugScore($slug))
            ->first();
    }

    private function slugScore(string $slug): int
    {
        $score = 1000 - mb_strlen($slug);

        if (! preg_match('/-\d+$/', $slug)) {
            $score += 500;
        }

        if (! str_contains($slug, 'archived')) {
            $score += 250;
        }

        return $score;
    }

    private function transferSupplierLinks(int $dupeId, int $keepId, bool $apply): array
    {
        $stats = ['moved' => 0, 'removed' => 0];
        $links = DB::table('supplier_products')->where('product_id', $dupeId)->get();

        foreach ($links as $link) {
            $alreadyExists = DB::table('supplier_products')
                ->where('product_id', $keepId)
                ->where('supplier_id', $link->supplier_id)
                ->exists();

            if ($alreadyExists) {
                $this->line(sprintf('  -> remove duplicate supplier link supplier_id=%d from id=%d', $link->supplier_id, $dupeId));
                if ($apply) {
                    DB::table('supplier_products')->where('id', $link->id)->delete();
                }
                $stats['removed']++;
                continue;
            }

            $this->line(sprintf('  -> move supplier link supplier_id=%d from id=%d to id=%d', $link->supplier_id, $dupeId, $keepId));
            if ($apply) {
                DB::table('supplier_products')->where('id', $link->id)->update([
                    'product_id' => $keepId,
                    'updated_at' => now(),
                ]);
            }
            $stats['moved']++;
        }

        return $stats;
    }

    private function transferAttributeValues(int $dupeId, int $keepId, bool $apply): int
    {
        $keepAttrIds = DB::table('product_attribute_values')
            ->where('product_id', $keepId)
            ->pluck('attribute_id')
            ->all();

        $dupeAttrs = DB::table('product_attribute_values')
            ->where('product_id', $dupeId)
            ->whereNotIn('attribute_id', $keepAttrIds)
            ->get();

        foreach ($dupeAttrs as $attr) {
            $this->line(sprintf('  -> move attribute_id=%d from id=%d to id=%d', $attr->attribute_id, $dupeId, $keepId));
            if ($apply) {
                DB::table('product_attribute_values')->where('id', $attr->id)->update([
                    'product_id' => $keepId,
                    'updated_at' => now(),
                ]);
            }
        }

        return $dupeAttrs->count();
    }

    private function copyMissingProductFields(int $dupeId, int $keepId, bool $apply): int
    {
        $keep = DB::table('products')->where('id', $keepId)->first();
        $dupe = DB::table('products')->where('id', $dupeId)->first();
        $update = [];

        foreach (['short_description', 'content', 'video_url'] as $field) {
            if (empty($keep->{$field}) && ! empty($dupe->{$field})) {
                $update[$field] = $dupe->{$field};
            }
        }

        foreach (['images', 'specs', 'service_info', 'documents'] as $field) {
            if ($this->jsonIsEmpty($keep->{$field} ?? null) && ! $this->jsonIsEmpty($dupe->{$field} ?? null)) {
                $update[$field] = $dupe->{$field};
            }
        }

        if (empty($update)) {
            return 0;
        }

        $this->line(sprintf('  -> copy missing fields to keep id=%d: %s', $keepId, implode(', ', array_keys($update))));

        if ($apply) {
            $update['updated_at'] = now();
            DB::table('products')->where('id', $keepId)->update($update);
        }

        return count($update);
    }

    private function jsonIsEmpty(mixed $value): bool
    {
        return count($this->decodeArray($value)) === 0;
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
