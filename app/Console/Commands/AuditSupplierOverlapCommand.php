<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Console\Command;

/**
 * Which other suppliers carry the cards of one supplier, and at what cost:
 * a reseller shows up as the same cards bought cheaper elsewhere.
 * Read-only; prints one line per card/other supplier and totals per pair.
 *
 *   php artisan catalog:supplier-overlap teploshop
 */
class AuditSupplierOverlapCommand extends Command
{
    protected $signature = 'catalog:supplier-overlap {supplier : Supplier code}';

    protected $description = 'Compare one supplier\'s cards with the other suppliers linked to the same cards';

    public function handle(): int
    {
        $supplier = Supplier::query()->where('code', $this->argument('supplier'))->first();
        if (! $supplier) {
            $this->line('metric.overlap.found=0');

            return self::FAILURE;
        }
        $own = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->get()->keyBy('product_id');
        $products = Product::query()->whereIn('id', $own->keys())->with(['brand:id,slug', 'supplierProducts.supplier.latestChannelTransition'])->get();

        $stats = ['cards' => $products->count(), 'shared_cards' => 0, 'exclusive_cards' => 0];
        $pairs = [];
        foreach ($products as $product) {
            $mine = $own[$product->id];
            $operational = $product->operationalSupplierProducts()->pluck('id')->flip();
            $others = $product->supplierProducts->filter(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
            $stats[$others->isEmpty() ? 'exclusive_cards' : 'shared_cards']++;
            $myCost = (float) $mine->price_byn;
            $myRetail = (float) ($mine->raw['retail'] ?? 0);
            foreach ($others as $other) {
                $code = (string) ($other->supplier?->code ?: 'none');
                $cost = (float) $other->price_byn;
                $key = ($product->brand?->slug ?: 'none').':'.$code;
                $pairs[$key] ??= ['cards' => 0, 'operational' => 0, 'both_cost' => 0, 'other_cheaper' => 0, 'mine_cheaper' => 0, 'diff_sum' => 0.0];
                $pairs[$key]['cards']++;
                $pairs[$key]['operational'] += (int) $operational->has($other->id);
                if ($myCost > 0 && $cost > 0) {
                    $pairs[$key]['both_cost']++;
                    $pairs[$key][$cost < $myCost ? 'other_cheaper' : 'mine_cheaper']++;
                    $pairs[$key]['diff_sum'] += ($myCost - $cost) / $cost * 100;
                }
                $this->line(sprintf('audit.overlap.card=%d:%s:%s:%d:%s:%s:%s:%s:%s', $product->id, $product->slug, $code, (int) $operational->has($other->id),
                    $this->money($cost), $this->money($myCost), $this->money($myRetail), $this->money((float) $product->price), preg_replace('/[^a-z_]/', '', (string) $other->stock_status) ?: 'none'));
            }
        }
        foreach ($pairs as $key => $pair) {
            // Average markup of this supplier over the other one, in percent (positive: this supplier is dearer).
            $markup = $pair['both_cost'] ? (int) round($pair['diff_sum'] / $pair['both_cost']) : 0;
            $this->line(sprintf('audit.overlap.pair=%s:%d:%d:%d:%d:%d:%d', $key, $pair['cards'], $pair['operational'], $pair['both_cost'], $pair['other_cheaper'], $pair['mine_cheaper'], $markup));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.overlap.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    private function money(float $value): string
    {
        return $value > 0 ? number_format($value, 2, '.', '') : '0';
    }
}
