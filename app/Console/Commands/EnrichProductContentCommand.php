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
        {--all : Process all non-archived products (no brand/category filter)}
        {--only= : Which fields to fill: content | short | both (default: both)}
        {--force : Re-generate even if content already exists}
        {--limit= : Limit number of products}
        {--offset=0 : Skip first N products (for batching)}
        {--sleep=300 : Delay between API calls in milliseconds}
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

        $force   = (bool) $this->option('force');
        $dryRun  = (bool) $this->option('dry-run');
        $sleepMs = max(0, (int) ($this->option('sleep') ?? 300));
        $limit   = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $offset  = max(0, (int) ($this->option('offset') ?? 0));
        $only    = $this->option('only') ?? 'both';

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('p.is_archived', false);

        if ($sku = $this->option('sku')) {
            $query->where('p.sku', $sku);
        } elseif ($brandSlug = $this->option('brand')) {
            $brandId = DB::table('brands')->where('slug', $brandSlug)->value('id');
            if (! $brandId) {
                $this->error("Brand not found: $brandSlug");
                return self::FAILURE;
            }
            $query->where('p.brand_id', $brandId);
        } elseif ($categoryId = $this->option('category')) {
            $query->where('p.category_id', (int) $categoryId);
        } elseif ($this->option('all')) {
            // no extra filter
        } else {
            $this->error('Specify --brand=, --category=, --sku= or --all');
            return self::FAILURE;
        }

        if (! $force) {
            $query->where(function ($q) use ($only) {
                if ($only === 'content') {
                    $q->whereNull('p.content')->orWhere('p.content', '');
                } elseif ($only === 'short') {
                    $q->whereNull('p.short_description')->orWhere('p.short_description', '');
                } else {
                    $q->where(fn ($w) => $w->whereNull('p.content')->orWhere('p.content', ''))
                      ->orWhere(fn ($w) => $w->whereNull('p.short_description')->orWhere('p.short_description', ''));
                }
            });
        }

        $query->orderBy('p.id');

        $total = (clone $query)->count();

        if ($offset) {
            $query->offset($offset);
        }
        if ($limit) {
            $query->limit($limit);
        }

        $products = $query->get([
            'p.id', 'p.sku', 'p.name', 'p.content', 'p.short_description', 'p.specs',
            'b.name as brand_name', 'c.name as category_name',
        ]);

        $this->info(sprintf(
            'Candidates: %d | processing: %d (offset=%d)%s',
            $total,
            $products->count(),
            $offset,
            $force ? ' [--force]' : ''
        ));

        if ($dryRun) {
            foreach ($products as $p) {
                $this->line(sprintf('[dry-run] %s — %s', $p->sku ?? $p->id, mb_substr($p->name, 0, 60)));
            }
            return self::SUCCESS;
        }

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($products as $i => $product) {
            $this->line(sprintf('[%d/%d] id=%d %s', $i + 1, $products->count(), $product->id, mb_substr($product->name, 0, 60)));

            try {
                $specs = $product->specs ? (json_decode($product->specs, true) ?: []) : [];

                $seo = $enricher->generateSeo(
                    (string) $product->name,
                    (string) ($product->brand_name ?? ''),
                    (string) ($product->category_name ?? ''),
                    $specs
                );

                if (! $seo) {
                    $this->warn('  AI returned empty response, skipped.');
                    $stats['skipped']++;
                    usleep($sleepMs * 1000);
                    continue;
                }

                $updates = [];

                if ($only !== 'short' && $seo['content'] !== '' && ($force || trim((string) $product->content) === '')) {
                    $updates['content'] = $seo['content'];
                }
                if ($only !== 'content' && $seo['short'] !== '' && ($force || trim((string) $product->short_description) === '')) {
                    $updates['short_description'] = $seo['short'];
                }

                if (empty($updates)) {
                    $this->line('  — already filled, skipped');
                    $stats['skipped']++;
                } else {
                    $updates['updated_at'] = now();
                    DB::table('products')->where('id', $product->id)->update($updates);
                    $fields = implode('+', array_diff(array_keys($updates), ['updated_at']));
                    $this->line("  <fg=cyan>✓ {$fields} saved</>");
                    $stats['updated']++;
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

        $remaining = $total - $offset - $products->count();
        if ($remaining > 0) {
            $nextOffset = $offset + $products->count();
            $this->line(sprintf("\n<fg=yellow>%d more remain. Continue with --offset=%d</>", $remaining, $nextOffset));
        }

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
