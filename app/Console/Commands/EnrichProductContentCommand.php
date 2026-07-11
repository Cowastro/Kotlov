<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use App\Services\ProductSourceEnricher;
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
        {--rewrite-thin=0 : Re-generate existing content when stripped text is shorter than this many characters}
        {--source-context : Fetch supplier source_url and pass parsed source description/specs to AI}
        {--require-source-context : Process only products with a linked supplier source_url}
        {--min-source-context-chars=0 : Skip products when parsed source description is shorter than this many characters}
        {--skip-root-source-context : Skip source URLs that point to a bare domain/home page}
        {--allow-mojibake : Allow products with visibly broken text encoding in name/brand/category}
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
        $rewriteThin = max(0, (int) ($this->option('rewrite-thin') ?? 0));
        $minSourceContextChars = max(0, (int) ($this->option('min-source-context-chars') ?? 0));
        $only    = $this->option('only') ?? 'both';
        $useSourceContext = (bool) $this->option('source-context');
        $requireSourceContext = (bool) $this->option('require-source-context');
        $skipRootSourceContext = (bool) $this->option('skip-root-source-context');
        $allowMojibake = (bool) $this->option('allow-mojibake');
        $sourceEnricher = $useSourceContext ? new ProductSourceEnricher() : null;

        $supplierTechnicalAttributeNames = \App\Models\Product::supplierTechnicalAttributeNames();

        $attributeCounts = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->whereNotNull('pav.value')
            ->where('pav.value', '<>', '')
            ->when($supplierTechnicalAttributeNames !== [], fn ($query) => $query->whereNotIn('a.name', $supplierTechnicalAttributeNames))
            ->select('pav.product_id', DB::raw('COUNT(*) as attribute_rows'))
            ->groupBy('pav.product_id');

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
            $query->where(function ($q) use ($only, $rewriteThin) {
                if ($only === 'content') {
                    $q->whereNull('p.content')->orWhere('p.content', '');
                    if ($rewriteThin > 0) {
                        $q->orWhereRaw('LENGTH(COALESCE(p.content, "")) <= ?', [$rewriteThin]);
                    }
                } elseif ($only === 'short') {
                    $q->whereNull('p.short_description')->orWhere('p.short_description', '');
                } else {
                    $q->where(fn ($w) => $w->whereNull('p.content')->orWhere('p.content', ''))
                      ->orWhere(fn ($w) => $w->whereNull('p.short_description')->orWhere('p.short_description', ''));
                    if ($rewriteThin > 0) {
                        $q->orWhereRaw('LENGTH(COALESCE(p.content, "")) <= ?', [$rewriteThin]);
                    }
                }
            });
        }

        if ($minSpecs > 0) {
            $query->whereRaw('COALESCE(pav_count.attribute_rows, 0) >= ?', [$minSpecs]);
        }

        if ($requireSourceContext) {
            $query->whereExists(function ($exists) {
                $exists->selectRaw('1')
                    ->from('supplier_products as sp_source')
                    ->whereColumn('sp_source.product_id', 'p.id')
                    ->whereNotNull('sp_source.source_url')
                    ->where('sp_source.source_url', '<>', '');
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

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($products as $i => $product) {
            $this->line(sprintf('[%d/%d] id=%d %s', $i + 1, $products->count(), $product->id, mb_substr($product->name, 0, 60)));

            try {
                if (! $allowMojibake && $this->looksLikeMojibake(
                    implode(' ', [
                        (string) $product->name,
                        (string) ($product->brand_name ?? ''),
                        (string) ($product->category_name ?? ''),
                    ])
                )) {
                    $this->line('  skipped: product text looks like broken encoding');
                    $stats['skipped']++;
                    usleep($sleepMs * 1000);
                    continue;
                }

                $specs = $this->specsForProduct((int) $product->id, $product->specs);
                $sourceContext = [];

                if ($useSourceContext && $sourceEnricher !== null) {
                    $source = $this->sourceContextForProduct((int) $product->id, $sourceEnricher);
                    $sourceContext = $source['context'];
                    $specs = array_merge($source['specs'], $specs);

                    if (($source['url'] ?? '') !== '') {
                        $this->line(sprintf(
                            '  source context: %s%s',
                            $source['url'],
                            ($source['description_chars'] ?? 0) > 0
                                ? sprintf(' (%d chars, %d specs)', $source['description_chars'], count($source['specs']))
                                : ''
                        ));
                    }
                    if (($source['error'] ?? '') !== '') {
                        $this->line('  <fg=yellow>source context skipped:</> ' . $source['error']);
                    }

                    if ($skipRootSourceContext && $this->isRootSourceUrl((string) ($source['url'] ?? ''))) {
                        $this->line('  skipped: source URL points to a bare domain/home page');
                        $stats['skipped']++;
                        usleep($sleepMs * 1000);
                        continue;
                    }

                    if ($minSourceContextChars > 0 && (int) ($source['description_chars'] ?? 0) < $minSourceContextChars) {
                        $this->line(sprintf(
                            '  skipped: source context is too short (%d chars, min is %d)',
                            (int) ($source['description_chars'] ?? 0),
                            $minSourceContextChars
                        ));
                        $stats['skipped']++;
                        usleep($sleepMs * 1000);
                        continue;
                    }

                    if (! $allowMojibake && $this->looksLikeMojibake(implode(' ', $sourceContext))) {
                        $this->line('  skipped: source context looks like broken encoding');
                        $stats['skipped']++;
                        usleep($sleepMs * 1000);
                        continue;
                    }
                }

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
                    $specs,
                    $sourceContext
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

                $contentIsEmpty = trim((string) $product->content) === '';
                $contentIsThin = $rewriteThin > 0 && $this->plainTextLength((string) $product->content) <= $rewriteThin;

                if ($only !== 'short' && $seo['content'] !== '' && ($force || $contentIsEmpty || $contentIsThin)) {
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
            ->whereNotIn('a.name', \App\Models\Product::supplierTechnicalAttributeNames())
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

    private function plainTextLength(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strlen(trim($text));
    }

    private function isRootSourceUrl(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = trim($path, '/');

        return $path === '';
    }

    private function looksLikeMojibake(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/(?:Đ|Ń|Â|Ð|Ñ|Рџ|Рђ|Р‘|РІ|Р°|Рµ|РЅ|Рѕ|Рї|Рр|СЃ|С‚)/u', $value)) {
            return true;
        }

        return substr_count($value, '?') >= 3;
    }

    /**
     * Use the already-linked supplier source URL as extra factual context for AI.
     * This is intentionally read-only: content generation still decides what to write.
     *
     * @return array{url: string, context: array<string, string>, specs: array<string, string>, description_chars: int, error: string}
     */
    private function sourceContextForProduct(int $productId, ProductSourceEnricher $sourceEnricher): array
    {
        $empty = [
            'url' => '',
            'context' => [],
            'specs' => [],
            'description_chars' => 0,
            'error' => '',
        ];

        if (! Schema::hasTable('supplier_products')) {
            return array_merge($empty, ['error' => 'supplier_products table is missing']);
        }

        $url = (string) DB::table('supplier_products')
            ->where('product_id', $productId)
            ->whereNotNull('source_url')
            ->where('source_url', '<>', '')
            ->orderByDesc('updated_at')
            ->value('source_url');

        if ($url === '') {
            return $empty;
        }

        try {
            $parsed = $sourceEnricher->preview($url);
        } catch (\Throwable $e) {
            return array_merge($empty, ['url' => $url, 'error' => $e->getMessage()]);
        }

        $description = trim(strip_tags((string) ($parsed['description'] ?? '')));
        $short = trim(strip_tags((string) ($parsed['short_description'] ?? '')));
        $title = trim(strip_tags((string) ($parsed['title'] ?? '')));
        $sourceSpecs = [];

        foreach ((array) ($parsed['specs'] ?? []) as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $key = trim((string) ($spec['key'] ?? $spec['name'] ?? ''));
            $value = trim((string) ($spec['value'] ?? ''));
            $unit = trim((string) ($spec['unit'] ?? ''));

            if ($key !== '' && $value !== '') {
                $sourceSpecs[$key] = trim($value . ($unit !== '' ? ' ' . $unit : ''));
            }
        }

        return [
            'url' => $url,
            'context' => array_filter([
                'source_url' => $url,
                'source_title' => $title,
                'source_short_description' => $short,
                'source_description' => mb_substr($description, 0, 2200),
            ], fn ($value): bool => trim((string) $value) !== ''),
            'specs' => $sourceSpecs,
            'description_chars' => mb_strlen($description),
            'error' => '',
        ];
    }
}
