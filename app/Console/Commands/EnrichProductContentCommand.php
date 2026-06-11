<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichProductContentCommand extends Command
{
    protected $signature = 'product:enrich-content
        {--brand= : Brand slug (e.g. tekhnolit, pegas)}
        {--category= : Category ID}
        {--sku= : Single product SKU}
        {--force : Re-generate even if content already exists}
        {--limit= : Limit number of products}
        {--sleep=500 : Delay between API calls in milliseconds}
        {--dry-run : Show what would be processed without calling AI}';

    protected $description = 'Generate unique SEO descriptions for existing products via AI.';

    public function handle(): int
    {
        $enricher = new AiContentEnricher();

        if (! $enricher->isAvailable()) {
            $this->error('No AI provider configured. Set ANTHROPIC_API_KEY or AI_API_KEY + AI_API_URL + AI_MODEL in .env');
            return self::FAILURE;
        }

        $this->info('Provider: ' . $enricher->providerName());

        $force  = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $sleepMs = max(0, (int) ($this->option('sleep') ?? 500));
        $limit  = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = DB::table('products')
            ->where('is_archived', false);

        if ($sku = $this->option('sku')) {
            $query->where('sku', $sku);
        } elseif ($brandSlug = $this->option('brand')) {
            $brandId = DB::table('brands')->where('slug', $brandSlug)->value('id');
            if (! $brandId) {
                $this->error("Brand not found: $brandSlug");
                return self::FAILURE;
            }
            $query->where('brand_id', $brandId);
        } elseif ($categoryId = $this->option('category')) {
            $query->where('category_id', (int) $categoryId);
        } else {
            $this->error('Specify --brand=, --category= or --sku=');
            return self::FAILURE;
        }

        if (! $force) {
            $query->where(fn ($q) => $q->whereNull('content')->orWhere('content', ''));
        }

        $query->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $products = $query->get(['id', 'sku', 'name', 'brand_id', 'category_id', 'content', 'specs']);

        $this->info(sprintf(
            'Found %d products to process%s.',
            $products->count(),
            $force ? ' (--force: including those with content)' : ' (empty content only)'
        ));

        if ($dryRun) {
            foreach ($products as $p) {
                $this->line(sprintf('[dry-run] %s — %s', $p->sku, mb_substr($p->name, 0, 60)));
            }
            return self::SUCCESS;
        }

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($products as $i => $product) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, $products->count(), mb_substr($product->name, 0, 60)));

            try {
                $brandName  = DB::table('brands')->where('id', $product->brand_id)->value('name') ?? '';
                $attributes = $product->specs ? (json_decode($product->specs, true) ?: []) : [];

                $aiText = $enricher->enrich($product->name, $brandName, $product->content, $attributes);

                if ($aiText) {
                    DB::table('products')->where('id', $product->id)->update([
                        'content'    => $aiText,
                        'updated_at' => now(),
                    ]);
                    $this->line('  <fg=cyan>✓ content updated</>');
                    $stats['updated']++;
                } else {
                    $this->warn('  AI returned empty response, skipped.');
                    $stats['skipped']++;
                }

                usleep($sleepMs * 1000);
            } catch (\Throwable $e) {
                $this->warn('  failed: ' . $e->getMessage());
                $stats['errors']++;
            }
        }

        $this->table(
            ['action', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats))
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
