<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\CategoryMetadataImage;
use Illuminate\Console\Command;

class AuditCategoryMetadataImages extends Command
{
    protected $signature = 'catalog:audit-meta-images {--fresh : Recheck local photo files}';

    protected $description = 'Audit metadata photos for every active category without changing catalog data';

    public function handle(CategoryMetadataImage $resolver): int
    {
        $total = $missing = 0;
        foreach (Category::active()->orderBy('id')->cursor() as $category) {
            $image = $resolver->resolve($category, fresh: (bool) $this->option('fresh'));
            $total++;
            $missing += $image === null ? 1 : 0;
            $this->line(json_encode(['slug' => $category->slug, 'image' => $image], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $this->info("categories={$total} with_photo=".($total - $missing)." missing={$missing}");

        return self::SUCCESS;
    }
}
