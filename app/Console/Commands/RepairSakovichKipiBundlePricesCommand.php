<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepairSakovichKipiBundlePricesCommand extends Command
{
    protected $signature = 'catalog:repair-sakovich-kipi-bundle-prices
        {--apply : Write corrected bundle prices and detach boiler-only supplier rows}';

    protected $description = 'Restore Sakovich Pellet Universal KIPI bundle prices from boiler, burner and BIO hopper components.';

    /**
     * Component prices come from the normalized Santehsklad price dated
     * 2026-09-17. Product IDs and SKUs identify the five legacy bundle cards.
     *
     * @var array<int,array{product_id:int,sku:string,retail:float,purchase:float,boiler_article:string}>
     */
    private const BUNDLES = [
        16 => ['product_id' => 12577, 'sku' => 'KOTLOV-000299', 'retail' => 10380.00, 'purchase' => 8200.50, 'boiler_article' => 'PELLET-UNIVERSAL-16'],
        20 => ['product_id' => 12591, 'sku' => 'KOTLOV-000313', 'retail' => 11300.00, 'purchase' => 8932.00, 'boiler_article' => 'PELLET-UNIVERSAL-20'],
        26 => ['product_id' => 12594, 'sku' => 'KOTLOV-000316', 'retail' => 12650.00, 'purchase' => 9991.00, 'boiler_article' => 'PELLET-UNIVERSAL-26'],
        36 => ['product_id' => 12593, 'sku' => 'KOTLOV-000315', 'retail' => 14320.00, 'purchase' => 11302.40, 'boiler_article' => 'PELLET-UNIVERSAL-36'],
        50 => ['product_id' => 12592, 'sku' => 'KOTLOV-000314', 'retail' => 17960.00, 'purchase' => 14170.30, 'boiler_article' => 'PELLET-UNIVERSAL-50'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stats = ['checked' => 0, 'price_updates' => 0, 'purchase_updates' => 0, 'boiler_links_detached' => 0, 'errors' => 0];

        foreach (self::BUNDLES as $power => $bundle) {
            $stats['checked']++;
            $product = Product::query()->find($bundle['product_id']);

            if (! $this->isExpectedBundle($product, $bundle['sku'], $power)) {
                $stats['errors']++;
                $this->line("audit.sakovich_bundle.error={$bundle['product_id']}:identity");

                continue;
            }

            $priceChanged = abs((float) $product->price - $bundle['retail']) >= 0.005;
            $purchaseChanged = abs((float) $product->manual_purchase_price_byn - $bundle['purchase']) >= 0.005;
            $linkedOfferIds = DB::table('supplier_products as sp')
                ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->where('s.code', 'santehsklad')
                ->where('sp.product_id', $product->id)
                ->where('sp.supplier_article_normalized', $bundle['boiler_article'])
                ->pluck('sp.id');

            $stats['price_updates'] += $priceChanged ? 1 : 0;
            $stats['purchase_updates'] += $purchaseChanged ? 1 : 0;
            $stats['boiler_links_detached'] += $linkedOfferIds->count();
            $this->line(sprintf(
                'audit.sakovich_bundle.item=%d:%d:%s:%s:%d',
                $product->id,
                $power,
                number_format((float) $product->price, 2, '.', ''),
                number_format($bundle['retail'], 2, '.', ''),
                $linkedOfferIds->count(),
            ));

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($product, $bundle, $linkedOfferIds): void {
                $product->forceFill([
                    'price' => $bundle['retail'],
                    'price_old' => null,
                    'is_sale' => false,
                    'manual_purchase_price_byn' => $bundle['purchase'],
                    'manual_price_updated_at' => now(),
                ])->save();

                if ($linkedOfferIds->isNotEmpty()) {
                    DB::table('supplier_products')->whereIn('id', $linkedOfferIds)->update([
                        'product_id' => null,
                        'product_sku' => null,
                        'match_status' => 'unmatched',
                        'match_confidence' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.sakovich_bundle.{$key}={$value}");
        }

        if ($apply && $stats['errors'] === 0) {
            Cache::flush();
        }

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function isExpectedBundle(?Product $product, string $sku, int $power): bool
    {
        if (! $product || $product->sku !== $sku) {
            return false;
        }

        $name = mb_strtolower((string) $product->name);

        return str_contains($name, 'pellet universal '.$power)
            && (str_contains($name, 'kipi') || str_contains($name, 'rotary'))
            && str_contains($name, 'бункер');
    }
}
