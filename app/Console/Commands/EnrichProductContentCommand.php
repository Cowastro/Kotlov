<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        {--min-specs=0 : Skip products with fewer available specs/attributes}
        {--openai : Use OPENAI_API_KEY/OPENAI_API_URL for this run when configured}
        {--ai-model= : Override AI model for this run}
        {--debug-ai : Print provider error/raw response when AI output cannot be parsed}
        {--dry-run : Preview generated text without writing to database}';

    protected $description = 'Generate unique SEO descriptions for existing products via AI.';

    public function handle(): int
    {
        $enricher = new AiContentEnricher();
        if ((bool) $this->option('openai')) {
            if (trim((string) config('services.ai.openai_key', '')) === '') {
                $this->error('OPENAI_API_KEY is not configured; refusing to fall back to the default AI provider.');
                return self::FAILURE;
            }
            $enricher = $enricher->withOpenAi((string) $this->option('ai-model'));
        } elseif ($this->option('ai-model')) {
            $enricher = $enricher->withModel((string) $this->option('ai-model'));
        }

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
        $minSpecs = max(0, (int) ($this->option('min-specs') ?? 0));
        $only    = $this->option('only') ?? 'both';

        $attributeCounts = DB::table('product_attribute_values')
            ->select('product_id', DB::raw('COUNT(*) as attribute_rows'))
            ->groupBy('product_id');

        $query = DB::table('products as p')
            ->leftJoinSub($attributeCounts, 'pav_count', 'pav_count.product_id', '=', 'p.id')
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

        if ($minSpecs > 0) {
            $query->whereRaw('COALESCE(pav_count.attribute_rows, 0) >= ?', [$minSpecs]);
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

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($products as $i => $product) {
            $this->line(sprintf('[%d/%d] id=%d %s', $i + 1, $products->count(), $product->id, mb_substr($product->name, 0, 60)));

            try {
                $specs = $this->specsForProduct((int) $product->id, $product->specs);
                if ($minSpecs > 0 && count($specs) < $minSpecs) {
                    $this->line(sprintf('  skipped: only %d specs, min is %d', count($specs), $minSpecs));
                    $stats['skipped']++;
                    continue;
                }
                $this->line(sprintf('  specs available: %d', count($specs)));

                $seo = $enricher->generateSeo(
                    (string) $product->name,
                    (string) ($product->brand_name ?? ''),
                    (string) ($product->category_name ?? ''),
                    $specs
                );

                if (! $seo) {
                    $this->warn('  AI returned empty response, skipped.');
                    if ((bool) $this->option('debug-ai')) {
                        if ($enricher->lastError()) {
                            $this->warn('  AI debug error: ' . $enricher->lastError());
                        }
                        if ($enricher->lastRawResponse()) {
                            $this->line('  <fg=gray>AI raw:</> ' . mb_substr(preg_replace('/\s+/u', ' ', $enricher->lastRawResponse()) ?? $enricher->lastRawResponse(), 0, 1200));
                        }
                    }
                    $stats['skipped']++;
                    usleep($sleepMs * 1000);
                    continue;
                }

                if ($dryRun) {
                    if (($seo['short'] ?? '') !== '') {
                        $this->line('  <fg=green>short preview:</> ' . $seo['short']);
                    }
                    if (($seo['content'] ?? '') !== '') {
                        $preview = trim(preg_replace('/\s+/u', ' ', strip_tags($seo['content'])) ?? '');
                        $this->line('  <fg=green>content preview:</> ' . mb_substr($preview, 0, 700));
                        $this->line('  <fg=gray>html preview:</> ' . mb_substr($seo['content'], 0, 1200));
                    }
                    $this->line('  <fg=blue>[dry-run] database not changed</>');
                    $stats['updated']++;
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

    private function specsForProduct(int $productId, mixed $productSpecs): array
    {
        $flat = [];
        if (is_string($productSpecs) && trim($productSpecs) !== '') {
            $decoded = json_decode($productSpecs, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_array($value)) {
                        $name = trim((string) ($value['name'] ?? $value['key'] ?? $key));
                        $val = trim((string) ($value['value'] ?? ''));
                        $unit = trim((string) ($value['unit'] ?? ''));
                        if ($name !== '' && $val !== '') {
                            $flat[$name] = trim($val . ' ' . $unit);
                        }
                    } elseif (is_scalar($value) && trim((string) $value) !== '') {
                        $flat[(string) $key] = trim((string) $value);
                    }
                }
            }
        }

        if ($flat !== []) {
            return $flat;
        }

        $query = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('pav.product_id', $productId)
            ->whereNotNull('pav.value')
            ->where('pav.value', '<>', '')
            ->limit(40);

        if (Schema::hasColumn('product_attribute_values', 'sort_order')) {
            $query->orderBy('pav.sort_order');
        } else {
            $query->orderBy('pav.id');
        }

        return $query->pluck('pav.value', 'a.name')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->all();
    }
}
