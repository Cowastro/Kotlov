<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only: the in-stock 1C rows that have no card yet, with what could link them —
 * a card carrying the same article at another supplier, the matcher's best candidate
 * and the 1C group — so the queue can be split into «link», «check» and «create».
 *
 *   php artisan integration:audit-onec-queue
 */
class AuditOneCQueueCommand extends Command
{
    protected $signature = 'integration:audit-onec-queue {--source=onec : Integration source code}';

    protected $description = 'Split the unlinked in-stock 1C rows into link / check / create';

    public function handle(): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        if (! $source) {
            $this->line('metric.onec_queue.source=0');

            return self::FAILURE;
        }

        $items = IntegrationProduct::query()->where('integration_source_id', $source->id)->inStock()
            ->whereNull('product_id')->whereIn('match_status', ['unmatched', 'suggested', 'ambiguous'])
            ->with('integrationCategory:id,path')->orderBy('external_code')->get();

        // Cards that already carry the 1C article at some supplier, or as their SKU.
        $articles = $items->pluck('external_sku')->filter()->map(fn ($sku) => Str::upper(trim((string) $sku)))->unique()->values();
        $byArticle = DB::table('supplier_products')->whereNotNull('product_id')
            ->whereIn(DB::raw('UPPER(TRIM(supplier_article))'), $articles)
            ->pluck('product_id', DB::raw('UPPER(TRIM(supplier_article))'))->all()
            + DB::table('products')->where('is_archived', false)->whereIn(DB::raw('UPPER(TRIM(sku))'), $articles)
                ->pluck('id', DB::raw('UPPER(TRIM(sku))'))->all();

        $stats = ['rows' => 0, 'same_article' => 0, 'strong_candidate' => 0, 'weak_candidate' => 0, 'no_candidate' => 0, 'with_price' => 0];
        $groups = [];
        foreach ($items as $item) {
            $stats['rows']++;
            $stats['with_price'] += (int) ((float) $item->normalizedPriceByn() > 0);
            $article = Str::upper(trim((string) $item->external_sku));
            $sameArticle = $article !== '' ? ($byArticle[$article] ?? null) : null;
            $top = collect($item->candidates ?? [])->sortByDesc(fn ($c) => (float) ($c['score'] ?? $c['confidence'] ?? 0))->first();
            $score = (float) ($top['score'] ?? $top['confidence'] ?? 0);
            $score = $score > 1 ? $score / 100 : $score;
            $kind = match (true) {
                $sameArticle !== null => 'same_article',
                $top !== null && $score >= 0.8 => 'strong_candidate',
                $top !== null => 'weak_candidate',
                default => 'no_candidate',
            };
            $stats[$kind]++;
            $group = Str::slug(Str::limit((string) ($item->integrationCategory?->path ?? 'none'), 60, ''), '_') ?: 'none';
            $groups[$group][$kind] = ($groups[$group][$kind] ?? 0) + 1;
            $this->line(sprintf('audit.onec_queue.row=%d:%s:%s:%s:%s:%d:%d:%s:%d:%d',
                $item->id, Str::slug((string) $item->external_code) ?: 'none', Str::slug((string) $item->external_sku) ?: 'none',
                Str::slug(Str::limit((string) $item->name, 70, '')) ?: 'none', $item->match_status,
                (int) round((float) $item->normalizedPriceByn()), (int) round((float) $item->stock_quantity), $kind,
                (int) ($sameArticle ?? ($top['product_id'] ?? 0)), (int) round($score * 100)));
        }
        foreach ($groups as $group => $counts) {
            $this->line('audit.onec_queue.group='.$group.':'.collect($counts)->map(fn ($n, $k) => $k.'~'.$n)->implode(','));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_queue.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}
