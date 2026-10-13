<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class DiagnoseProductAvailabilityCommand extends Command
{
    protected $signature = 'catalog:diagnose-availability {product : Product ID or SKU}';

    protected $description = 'Read-only diagnosis of the supplier evidence behind a product availability status.';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('product'));

        $product = Product::query()
            ->withSupplierChannels()
            ->where(function ($query) use ($identifier): void {
                $query->where('sku', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhereKey((int) $identifier);
                }
            })
            ->first();

        if (! $product) {
            $this->error('product_found=0');

            return self::FAILURE;
        }

        $this->line('product_found=1');
        $this->line('product_id='.$product->id);
        $this->line('is_active='.(int) $product->is_active);
        $this->line('is_archived='.(int) $product->is_archived);
        $this->line('requires_price_confirmation='.(int) $product->requires_price_confirmation);
        $this->line('configured_status='.(string) $product->availability_status);
        $this->line('effective_status='.$product->effectiveAvailabilityStatus());
        $this->line('can_be_purchased='.(int) $product->canBePurchased());
        $this->line('legacy_channels='.$product->supplierProducts->count());
        $this->line('integration_channels='.$product->integrationProducts->count());

        foreach ($product->integrationProducts->values() as $index => $item) {
            $prefix = 'integration.'.($index + 1).'.';
            $validityMinutes = $item->source?->stockValidityMinutes();

            $this->line($prefix.'source_active='.(int) ($item->source?->is_active === true));
            $this->line($prefix.'source_code='.(string) ($item->source?->code ?? ''));
            $this->line($prefix.'source_driver='.(string) ($item->source?->driver ?? ''));
            $this->line($prefix.'match_status='.(string) $item->match_status);
            $this->line($prefix.'stock_quantity='.(string) $item->stock_quantity);
            $this->line($prefix.'stock_confirmed_at='.(string) ($item->stock_confirmed_at?->toIso8601String() ?? ''));
            $this->line($prefix.'validity_minutes='.(string) ($validityMinutes ?? ''));
            $this->line($prefix.'fresh_positive='.(int) $item->hasFreshPositiveStock());
        }

        return self::SUCCESS;
    }
}
