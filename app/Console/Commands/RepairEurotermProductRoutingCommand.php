<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepairEurotermProductRoutingCommand extends Command
{
    protected $signature = 'supplier:repair-euroterm-routing
        {--apply : Remove foreign supplier routes from EUROTERM product cards}';

    protected $description = 'Keep EUROTERM-* product cards owned by ООО «Евротерм» and detach erroneous SanBusinessGroup routes.';

    public function handle(SupplierProductAvailabilityService $availability): int
    {
        $euroterm = Supplier::query()->where('code', 'euroterm')->first();
        $sbg = Supplier::query()->where('code', 'sanbusinessgroup')->first();

        if (! $euroterm || ! $sbg) {
            $this->error('Не найдены поставщики euroterm и sanbusinessgroup.');

            return self::FAILURE;
        }

        $productIds = Product::query()
            ->where('sku', 'like', 'EUROTERM-%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $eurotermLinkedIds = SupplierProduct::query()
            ->where('supplier_id', $euroterm->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $metrics = [
            'products' => count($productIds),
            'wrong_product_owner' => Product::query()
                ->whereIn('id', $productIds)
                ->where(fn ($query) => $query->whereNull('supplier_id')->orWhere('supplier_id', '!=', $euroterm->id))
                ->count(),
            'euroterm_product_links' => count($eurotermLinkedIds),
            'missing_euroterm_link' => count(array_diff($productIds, $eurotermLinkedIds)),
            'sbg_supplier_links' => SupplierProduct::query()
                ->where('supplier_id', $sbg->id)
                ->whereIn('product_id', $productIds)
                ->count(),
            'sbg_integration_links' => IntegrationProduct::query()
                ->whereIn('product_id', $productIds)
                ->where('match_status', 'matched')
                ->whereHas('source', fn ($source) => $source->where('supplier_id', $sbg->id))
                ->count(),
            'products_updated' => 0,
            'supplier_links_removed' => 0,
            'integration_links_detached' => 0,
        ];

        if ($this->option('apply') && $productIds !== []) {
            DB::transaction(function () use ($productIds, $eurotermLinkedIds, $euroterm, $sbg, &$metrics): void {
                $metrics['products_updated'] = Product::query()
                    ->whereIn('id', $productIds)
                    ->update(['supplier_id' => $euroterm->id, 'updated_at' => now()]);
                $metrics['supplier_links_removed'] = SupplierProduct::query()
                    ->where('supplier_id', $sbg->id)
                    ->whereIn('product_id', $productIds)
                    ->delete();
                $metrics['integration_links_detached'] = IntegrationProduct::query()
                    ->whereIn('product_id', $productIds)
                    ->whereHas('source', fn ($source) => $source->where('supplier_id', $sbg->id))
                    ->update([
                        'product_id' => null,
                        'match_status' => 'unmatched',
                        'match_method' => 'supplier_ownership_conflict',
                        'match_confidence' => null,
                        'candidates' => null,
                        'matched_at' => null,
                        'updated_at' => now(),
                    ]);

                $missingIds = array_values(array_diff($productIds, $eurotermLinkedIds));
                if ($missingIds !== []) {
                    Product::query()->whereIn('id', $missingIds)->update([
                        'requires_price_confirmation' => true,
                        'in_stock' => false,
                        'availability_status' => Product::AVAILABILITY_CHECK,
                        'updated_at' => now(),
                    ]);
                }
            });

            $availability->refreshMany($eurotermLinkedIds);
            Cache::forget('home:data');
            Cache::forget('home:data:v5');
        }

        $this->line('metric.euroterm_routing.status='.($this->option('apply') ? 'applied' : 'dry_run'));
        foreach ($metrics as $name => $value) {
            $this->line("metric.euroterm_routing.{$name}={$value}");
        }

        return self::SUCCESS;
    }
}
