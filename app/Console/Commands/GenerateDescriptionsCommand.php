<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generate SEO short_description + content for supplier products that have none,
 * using the existing AiContentEnricher (DeepSeek via AI_API_*). Strictly from
 * name/brand/category/specs — no invented numbers, no price/delivery/warranty
 * claims, no Markdown. Never overwrites filled fields unless --force.
 *
 * Usage:
 *   php artisan products:generate-descriptions --supplier=rusklimat --limit=5 --dry-run
 *   php artisan products:generate-descriptions --supplier=rusklimat --limit=20
 *   php artisan products:generate-descriptions --supplier=rusklimat --brand=Ballu --limit=10
 */
class GenerateDescriptionsCommand extends Command
{
    protected $signature = 'products:generate-descriptions
        {--supplier=rusklimat : Supplier code whose products to enrich}
        {--limit=20           : Max products per run}
        {--offset=0           : Skip first N products (batching)}
        {--brand=             : Filter by brand name (partial match)}
        {--only=both          : both | content | short — which field to fill}
        {--include-archived   : Also process archived products (default: active only)}
        {--force              : Overwrite existing content / short_description}
        {--dry-run            : Preview generated text, write nothing}';

    protected $description = 'Generate SEO descriptions (short + content) from supplier data via DeepSeek/AI.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $only   = (string) $this->option('only');
        $limit  = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $ai = new AiContentEnricher();
        if (! $ai->isAvailable()) {
            $this->error('No AI provider configured. Set AI_API_KEY + AI_API_URL + AI_MODEL (DeepSeek).');
            return self::FAILURE;
        }
        $this->line($dryRun
            ? '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>'
            : '<fg=red;options=bold>APPLY: descriptions will be written.</>');
        $this->info('AI provider: ' . $ai->providerName());

        $supplierCode = (string) $this->option('supplier');
        $supplierId   = DB::table('suppliers')->where('code', $supplierCode)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . $supplierCode . '" not found.');
            return self::FAILURE;
        }

        $includeArchived = (bool) $this->option('include-archived');
        $brandFilter     = $this->option('brand');

        $query = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('sp.supplier_id', $supplierId)
            ->when(! $includeArchived, fn ($q) => $q->where('p.is_archived', false))
            ->when($brandFilter, fn ($q) => $q->where('b.name', 'like', '%' . $brandFilter . '%'))
            ->when(! $force, fn ($q) => $q->where(function ($w) use ($only) {
                if ($only === 'content') {
                    $w->whereNull('p.content')->orWhere('p.content', '');
                } elseif ($only === 'short') {
                    $w->whereNull('p.short_description')->orWhere('p.short_description', '');
                } else {
                    $w->whereNull('p.content')->orWhere('p.content', '')
                      ->orWhereNull('p.short_description')->orWhere('p.short_description', '');
                }
            }));

        $total    = (clone $query)->distinct('p.id')->count('p.id');
        $products = $query->orderBy('p.id')->offset($offset)->limit($limit)
            ->get(['p.id', 'p.name', 'p.content', 'p.short_description', 'p.specs',
                   'b.name as brand', 'c.name as category']);

        $this->newLine();
        $this->info(sprintf('Candidates: %d (processing %d, offset %d)', $total, $products->count(), $offset));
        if ($products->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $stats = ['processed' => 0, 'content' => 0, 'short' => 0, 'no_result' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($products as $i => $p) {
            $stats['processed']++;
            $specs = json_decode($p->specs ?? '[]', true);
            $specs = is_array($specs) ? $specs : [];

            $this->newLine();
            $this->line(sprintf('<fg=cyan>[%d/%d] id=%d</> %s', $i + 1, $products->count(), $p->id, mb_substr((string) $p->name, 0, 60)));
            $this->line('  brand: ' . ($p->brand ?? '—') . ' | category: ' . ($p->category ?? '—')
                . ' | specs: ' . (empty($specs) ? '—' : count($specs) . ' шт'));

            try {
                $seo = $ai->generateSeo((string) $p->name, (string) ($p->brand ?? ''), (string) ($p->category ?? ''), $specs);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->line('  <fg=red>error: ' . $e->getMessage() . '</>');
                continue;
            }

            if ($seo === null) {
                $stats['no_result']++;
                $this->line('  <fg=yellow>no result from AI</>');
                continue;
            }

            $updates = [];
            $wantContent = $only !== 'short';
            $wantShort   = $only !== 'content';

            if ($wantContent && $seo['content'] !== '' && ($force || trim((string) $p->content) === '')) {
                $updates['content'] = $seo['content'];
            }
            if ($wantShort && $seo['short'] !== '' && ($force || trim((string) $p->short_description) === '')) {
                $updates['short_description'] = $seo['short'];
            }

            if ($seo['short'] !== '') {
                $this->line('  <fg=green>short:</> ' . mb_substr($seo['short'], 0, 110));
            }
            if ($seo['content'] !== '') {
                $this->line('  <fg=green>content:</> ' . mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($seo['content'])) ?? ''), 0, 110));
            }

            if (empty($updates)) {
                $stats['skipped']++;
                $this->line('  — nothing to update (already filled)');
                continue;
            }

            if ($dryRun) {
                $this->line('  <fg=blue>[dry-run] would update: ' . implode(', ', array_keys($updates)) . '</>');
            } else {
                $updates['updated_at'] = now();
                DB::table('products')->where('id', $p->id)->update($updates);
                $this->line('  <fg=green>saved: ' . implode(', ', array_keys(array_diff_key($updates, ['updated_at' => 1]))) . '</>');
            }
            if (isset($updates['content'])) {
                $stats['content']++;
            }
            if (isset($updates['short_description'])) {
                $stats['short']++;
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));

        if ($total > $offset + $limit) {
            $this->line(sprintf("\n<fg=yellow>%d more remain. Continue with --offset=%d</>", $total - ($offset + $limit), $offset + $limit));
        }

        return self::SUCCESS;
    }
}
