<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DetectsBadContent;
use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rewrite Rusklimat products whose content has orphan measurement units.
 * Takes the product's real specs, drops the old bad content and regenerates a
 * clean SEO description via DeepSeek (no empty values, no fabricated numbers).
 *
 *   php artisan supplier:rewrite-bad-content-rusklimat --active-only --limit=20 --dry-run
 *   php artisan supplier:rewrite-bad-content-rusklimat --active-only --limit=20 --apply
 */
class RewriteBadContentRusklimatCommand extends Command
{
    use DetectsBadContent;

    protected $signature = 'supplier:rewrite-bad-content-rusklimat
        {--active-only      : Only active (non-archived) products}
        {--brand=           : Filter by brand name (partial match)}
        {--limit=20         : Max products to rewrite per run}
        {--offset=0         : Skip first N bad products}
        {--apply            : Write changes (default is preview)}
        {--dry-run          : Preview only (default)}';

    protected $description = 'Regenerate clean content for Rusklimat products with orphan-unit content (DeepSeek).';

    private const SUPPLIER_CODE = 'rusklimat';

    public function handle(): int
    {
        $apply  = (bool) $this->option('apply') && ! $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: content will be rewritten.</>'
            : '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>');

        $ai = new AiContentEnricher();
        if (! $ai->isAvailable()) {
            $this->error('No AI provider configured (DeepSeek: AI_API_KEY + AI_API_URL + AI_MODEL).');
            return self::FAILURE;
        }
        $this->info('AI provider: ' . $ai->providerName());

        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . self::SUPPLIER_CODE . '" not found.');
            return self::FAILURE;
        }

        $candidates = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('sp.supplier_id', $supplierId)
            ->when($this->option('active-only'), fn ($q) => $q->where('p.is_archived', false))
            ->when($this->option('brand'), fn ($q) => $q->where('b.name', 'like', '%' . $this->option('brand') . '%'))
            ->whereNotNull('p.content')->where('p.content', '!=', '')
            ->orderBy('p.id')
            ->get(['p.id', 'p.name', 'p.content', 'p.specs', 'b.name as brand', 'c.name as category']);

        $stats = ['bad_found' => 0, 'rewritten' => 0, 'by_ai' => 0, 'by_build' => 0, 'no_result' => 0, 'errors' => 0];
        $seenBad = 0;

        foreach ($candidates as $p) {
            $oldMatches = $this->badContentMatches($p->content);
            if ($oldMatches === []) {
                continue;
            }
            $stats['bad_found']++;
            $seenBad++;
            if ($seenBad <= $offset) {
                continue;
            }
            if ($stats['rewritten'] + $stats['no_result'] + $stats['errors'] >= $limit) {
                break;
            }

            $specs = json_decode($p->specs ?? '[]', true);
            $specs = is_array($specs) ? $specs : [];

            $this->newLine();
            $this->line(sprintf('<fg=cyan>id=%d</> %s', $p->id, mb_substr((string) $p->name, 0, 56)));
            $this->line('  brand: ' . ($p->brand ?? '—') . ' | category: ' . ($p->category ?? '—')
                . ' | specs: ' . (empty($specs) ? '—' : count($specs)));
            $this->line('  <fg=yellow>old orphan:</> ' . mb_substr(implode(' | ', $oldMatches), 0, 70));

            try {
                $seo = $ai->generateSeo((string) $p->name, (string) ($p->brand ?? ''), (string) ($p->category ?? ''), $specs);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->line('  <fg=red>error: ' . $e->getMessage() . '</>');
                continue;
            }

            // Prefer a clean AI result; otherwise fall back to a deterministic
            // clean build so the orphan-unit content is always replaced.
            $newContent = null;
            $source     = '';
            if ($seo !== null && $seo['content'] !== '' && ! $this->hasBadContent($seo['content'])) {
                $newContent = $seo['content'];
                $source     = 'AI';
            } else {
                $built = $this->buildCleanContent((string) $p->name, (string) ($p->brand ?? ''), (string) ($p->category ?? ''), $specs);
                if ($built !== '') {
                    $newContent = $built;
                    $source     = 'built';
                }
            }

            if ($newContent === null) {
                $stats['no_result']++;
                $this->line('  <fg=yellow>could not produce clean content — kept old</>');
                continue;
            }

            $this->line('  <fg=green>new (' . $source . '):</> ' . mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($newContent)) ?? ''), 0, 90));

            if ($apply) {
                DB::table('products')->where('id', $p->id)->update([
                    'content'    => $newContent,
                    'updated_at' => now(),
                ]);
                $this->line('  <fg=green>rewritten (' . $source . ')</>');
            } else {
                $this->line('  <fg=blue>[dry-run] would rewrite content (' . $source . ')</>');
            }
            $stats['rewritten']++;
            $stats[$source === 'AI' ? 'by_ai' : 'by_build']++;
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));
        $this->line(sprintf('Total bad-content found so far in scan: %d', $stats['bad_found']));

        return self::SUCCESS;
    }

    /**
     * Deterministic clean content from supplier data — guaranteed free of orphan
     * units (drops any spec/value that would reintroduce them). '' if no data.
     */
    private function buildCleanContent(string $name, string $brand, string $category, array $specs): string
    {
        $flat = [];
        foreach ($specs as $k => $v) {
            if (is_array($v)) {
                $key = trim((string) ($v['key'] ?? $k));
                $val = trim((string) ($v['value'] ?? '')) . (($v['unit'] ?? '') ? ' ' . $v['unit'] : '');
            } else {
                $key = trim((string) $k);
                $val = trim((string) $v);
            }
            $val = trim($val);
            if ($key === '' || $val === '' || $val === '—') {
                continue;
            }
            $flat[$key] = $val;
        }

        // Content is PROSE ONLY — specs live in the structured «Характеристики»
        // tab (product_attribute_values), never inside the description.
        $cat   = $category !== '' ? mb_strtolower($category) : 'оборудование';
        $intro = '<p>' . e(trim($brand . ' ' . $name)) . ' — ' . e($cat)
            . ' от производителя ' . e($brand !== '' ? $brand : 'бренда') . '.</p>';

        return $this->hasBadContent($intro) ? '' : $intro;
    }
}
