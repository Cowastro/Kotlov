<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sanity check of automatic supplier links: the model numbers of the price
 * row must all be in the card name and in the same order. Sorted model keys
 * made «VUW 242/5-3» equal «VU 242/3-5»; this finds such links. Read-only.
 *
 *   php artisan catalog:check-supplier-links atmos
 */
class CheckSupplierLinksCommand extends Command
{
    protected $signature = 'catalog:check-supplier-links {supplier : Supplier code}';

    protected $description = 'Flag supplier links whose price-row model numbers are missing from the card or in another order';

    public function handle(): int
    {
        $supplier = Supplier::query()->where('code', $this->argument('supplier'))->first();
        if (! $supplier) {
            $this->line('metric.link_check.found=0');

            return self::FAILURE;
        }
        $links = DB::table('supplier_products as sp')->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplier->id)->where('p.is_archived', false)
            ->get(['sp.id', 'sp.supplier_name', 'sp.supplier_article', 'sp.match_confidence', 'p.id as product_id', 'p.slug', 'p.name']);

        $stats = ['links' => $links->count(), 'ok' => 0, 'missing_number' => 0, 'order' => 0, 'no_numbers' => 0];
        foreach ($links as $link) {
            $row = $this->numbers((string) $link->supplier_name);
            $card = $this->numbers((string) $link->name);
            if ($row === []) {
                $stats['no_numbers']++;

                continue;
            }
            $issue = array_diff($row, $card) !== [] ? 'missing_number' : null;
            if (! $issue) {
                // Shared numbers must keep their order: 242/5-3 is not 242/3-5.
                $shared = array_values(array_filter($card, fn (string $n): bool => in_array($n, $row, true)));
                $rowShared = array_values(array_filter($row, fn (string $n): bool => in_array($n, $card, true)));
                $issue = $this->dedupe($shared) !== $this->dedupe($rowShared) ? 'order' : null;
            }
            if (! $issue) {
                $stats['ok']++;

                continue;
            }
            $stats[$issue]++;
            $this->line(sprintf('audit.link_check.%s=%d:%s:%s:%s:%s', $issue, $link->product_id, $link->slug,
                Str::slug($link->supplier_name) ?: '-', implode('-', $row), preg_replace('/[^a-z_]/', '', (string) $link->match_confidence) ?: 'none'));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.link_check.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Model numbers in order: «24 кВт», «2 л» and other units count too; leading zeros dropped, «1.24» kept whole. */
    private function numbers(string $name): array
    {
        $name = preg_replace('/\b\d{8,}\b/', ' ', $name) ?? $name; // manufacturer articles in names
        preg_match_all('/\d+(?:[.,]\d+)?/', $name, $m);

        return array_map(fn (string $n): string => ltrim(str_replace(',', '.', $n), '0') ?: '0', $m[0]);
    }

    /** First occurrence of every number, in order («24 … 24 кВт» counts once). */
    private function dedupe(array $numbers): array
    {
        return array_values(array_unique($numbers));
    }
}
