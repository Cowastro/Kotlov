<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveMissingThermostudioAristonCommand extends Command
{
    protected $signature = 'supplier:archive-missing-thermostudio-ariston
        {--apply : Archive matched products}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit rows for testing}';

    protected $description = 'Archive old Ariston gas boiler products that are absent from the current Thermostudio catalog.';

    private const BRAND_SLUG = 'ariston';
    private const CATEGORY_SLUG = 'gazovye';

    private const ARCHIVE_SLUGS = [
        'ariston-clas-evo-system-24-ff',
        'ariston-clas-evo-system-28-ff',
        'ariston-clas-evo-system-28-cf',
        'ariston-clas-evo-24-ff',
        'gazovyiy-kotel-ariston-clas-xc-15-ff-ng-s-dyimohodom',
        'gazovyiy-kotel-ariston-clas-xc-systemgazovyiy-kotel-ariston-clas-xc-system-28-ff-ng',
        'gazovyiy-kotel-ariston-clas-xc-system-24-ff-ng',
        'kondensatsionnyiy-gazovyiy-kotel-ariston-genus-premium-evo-hp-85kw',
        'gazovyiy-kotel-ariston-cares-x-18-ff',
        'gazovyiy-kotel-ariston-clas-x-system-28-ff-ng-reflex-stora-af-150',
        'gazovyiy-kotel-ariston-clas-x-system-28-ff-ng-reflex-stora-af-200',
        'gazovyiy-kotel-ariston-clas-x-system-28-cf-ng-reflex-stora-af-150',
        'gazovyiy-kotel-ariston-clas-x-system-28-cf-ng-reflex-stora-af-200',
        'gazovyiy-kotel-ariston-genus-one-system-24-ng-reflex-stora-af-150',
        'gazovyiy-kotel-ariston-genus-one-system-24-ng-reflex-stora-af-200',
        'gazovyiy-kotel-ariston-genus-one-system-35-ng-reflex-stora-af-150',
        'gazovyiy-kotel-ariston-genus-one-system-35-ng-reflex-stora-af-200',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: matched products will be archived.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        $brandId = DB::table('brands')->where('slug', self::BRAND_SLUG)->value('id');
        $categoryId = DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $brandId) {
            $this->error('Brand not found by slug: ' . self::BRAND_SLUG);
            return self::FAILURE;
        }

        if (! $categoryId) {
            $this->error('Category not found by slug: ' . self::CATEGORY_SLUG);
            return self::FAILURE;
        }

        $slugs = $limit ? array_slice(self::ARCHIVE_SLUGS, 0, $limit) : self::ARCHIVE_SLUGS;

        $products = DB::table('products')
            ->where('brand_id', $brandId)
            ->where('category_id', $categoryId)
            ->whereIn('slug', $slugs)
            ->get(['id', 'name', 'slug', 'price', 'is_active', 'is_archived', 'in_stock'])
            ->sortBy(fn ($product) => array_search($product->slug, $slugs, true))
            ->values();

        $foundSlugs = $products->pluck('slug')->all();
        $missingSlugs = array_values(array_diff($slugs, $foundSlugs));

        $rows = $products->map(fn ($product) => [
            $product->is_archived ? 'already_archived' : ($apply ? 'archive' : 'would_archive'),
            $product->id,
            $product->is_active ? 'yes' : 'no',
            $product->in_stock ? 'yes' : 'no',
            number_format((float) $product->price, 2),
            mb_substr($product->name, 0, 52),
            $product->slug,
        ])->all();

        $this->table(
            ['action', 'id', 'active', 'stock', 'price', 'name', 'slug'],
            $rows
        );

        if ($missingSlugs) {
            $this->warn('Not found in current database:');
            foreach ($missingSlugs as $slug) {
                $this->line(' - ' . $slug);
            }
        }

        if (! $apply) {
            $this->line('Run with --apply to archive these products.');
            return self::SUCCESS;
        }

        $payload = [
            'is_archived' => true,
            'in_stock' => false,
            'updated_at' => now(),
        ];

        $ids = $products
            ->where('is_archived', false)
            ->pluck('id')
            ->all();

        $updated = $ids
            ? DB::table('products')->whereIn('id', $ids)->update($payload)
            : 0;

        $this->info(sprintf(
            'Archived %d products. Missing slugs: %d. Already archived: %d.',
            $updated,
            count($missingSlugs),
            $products->where('is_archived', true)->count()
        ));

        return self::SUCCESS;
    }
}
