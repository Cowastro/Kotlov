<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Ends time-limited promotions: after sale_ends_at the regular price comes
 * back from price_old and the sale flags are cleared. Scheduled hourly.
 */
class ExpireSaleCampaignsCommand extends Command
{
    protected $signature = 'catalog:expire-sale-campaigns {--apply : Restore the regular prices}';

    protected $description = 'Restore regular prices of products whose promotion has ended';

    public function handle(): int
    {
        $expired = Product::query()->whereNotNull('sale_campaign')->whereNotNull('sale_ends_at')->where('sale_ends_at', '<=', now());
        $count = (clone $expired)->count();
        $restored = 0;

        if ($this->option('apply')) {
            foreach ((clone $expired)->get() as $product) {
                $product->forceFill([
                    'price' => (float) $product->price_old > 0 ? $product->price_old : $product->price,
                    'price_old' => null,
                    'is_sale' => false,
                    'sale_campaign' => null,
                    'sale_ends_at' => null,
                ])->save();
                $restored++;
            }
            if ($restored > 0) {
                Cache::forget('home:data:v5');
            }
        }

        $this->line("metric.sale_campaigns.expired={$count}");
        $this->line("metric.sale_campaigns.restored={$restored}");

        return self::SUCCESS;
    }
}
