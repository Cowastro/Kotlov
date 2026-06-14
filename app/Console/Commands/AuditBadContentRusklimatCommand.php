<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\DetectsBadContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only audit: find Rusklimat products whose content contains measurement
 * units without a number (orphan units) — a sign of content generated from
 * empty spec values. Nothing is written.
 *
 *   php artisan supplier:audit-bad-content-rusklimat --active-only
 *   php artisan supplier:audit-bad-content-rusklimat --active-only --limit=100
 */
class AuditBadContentRusklimatCommand extends Command
{
    use DetectsBadContent;

    protected $signature = 'supplier:audit-bad-content-rusklimat
        {--active-only      : Only active (non-archived) products}
        {--brand=           : Filter by brand name (partial match)}
        {--limit=200        : Max rows to list}';

    protected $description = 'Read-only: list Rusklimat products with orphan measurement units in content.';

    private const SUPPLIER_CODE = 'rusklimat';

    public function handle(): int
    {
        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . self::SUPPLIER_CODE . '" not found.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $rows = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->where('sp.supplier_id', $supplierId)
            ->when($this->option('active-only'), fn ($q) => $q->where('p.is_archived', false))
            ->when($this->option('brand'), fn ($q) => $q->where('b.name', 'like', '%' . $this->option('brand') . '%'))
            ->whereNotNull('p.content')->where('p.content', '!=', '')
            ->orderBy('p.id')
            ->get(['p.id', 'p.sku', 'p.name', 'p.content', 'p.specs', 'b.name as brand']);

        $bad = [];
        $scanned = 0;
        foreach ($rows as $r) {
            $scanned++;
            $matches = $this->badContentMatches($r->content);
            if ($matches !== []) {
                $bad[] = [$r, $matches];
            }
        }

        $this->newLine();
        $this->info(sprintf('Scanned %d products with content — %d have orphan units (%.1f%%).',
            $scanned, count($bad), $scanned ? count($bad) / $scanned * 100 : 0));

        if ($bad === []) {
            $this->info('No bad content detected.');
            return self::SUCCESS;
        }

        $tableRows = [];
        foreach (array_slice($bad, 0, $limit) as [$r, $matches]) {
            $specs = json_decode($r->specs ?? '[]', true);
            $tableRows[] = [
                $r->id,
                $r->sku,
                mb_substr((string) ($r->brand ?? '—'), 0, 12),
                mb_substr((string) $r->name, 0, 32),
                is_array($specs) && $specs ? count($specs) : 0,
                mb_substr(implode(' | ', $matches), 0, 50),
            ];
        }

        $this->table(['id', 'sku', 'brand', 'name', 'specs', 'orphan units (example)'], $tableRows);
        $this->newLine();
        $this->line('<fg=yellow>Чинить:</> сначала specs — supplier:enrich-specs-rusklimat; затем supplier:rewrite-bad-content-rusklimat.');

        return self::SUCCESS;
    }
}
