<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cards created from a supplier price that no source could fill (no photo):
 * hidden from buyers until a photo is found, still available in КП and the
 * admin. --publish shows again the marked cards that have a photo by now.
 *
 *   php artisan catalog:hide-cards-awaiting-photos --supplier=tsk_nasosy --created-from=2026-10-25            # dry run
 *   php artisan catalog:hide-cards-awaiting-photos --supplier=tsk_nasosy --created-from=2026-10-25 --apply
 *   php artisan catalog:hide-cards-awaiting-photos --publish --apply
 */
class HideCardsAwaitingPhotosCommand extends Command
{
    protected $signature = 'catalog:hide-cards-awaiting-photos
        {--supplier= : Supplier code of the cards}
        {--created-from= : Only cards created from this date (YYYY-MM-DD)}
        {--publish : Show again the marked cards that have a photo now}
        {--apply : Write the change}';

    protected $description = 'Hide supplier cards without a photo until one is found (and publish them later)';

    public function handle(): int
    {
        if ($this->option('publish')) {
            $ready = Product::query()->where('created_via', Product::CREATED_VIA_AWAITING_PHOTOS)->where('is_active', false)->get(['id', 'images'])
                ->filter(fn (Product $product): bool => $this->hasPhoto($product->images));
            if ($this->option('apply')) {
                Product::query()->whereIn('id', $ready->pluck('id'))->update(['is_active' => true, 'created_via' => null, 'updated_at' => now()]);
            }
            $this->line('metric.awaiting_photos.published='.$ready->count());

            return self::SUCCESS;
        }

        $supplierId = (int) DB::table('suppliers')->where('code', (string) $this->option('supplier'))->value('id');
        if (! $supplierId) {
            $this->line('metric.awaiting_photos.supplier_found=0');

            return self::FAILURE;
        }
        $cards = Product::query()
            ->whereIn('id', DB::table('supplier_products')->where('supplier_id', $supplierId)->whereNotNull('product_id')->select('product_id'))
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNull('created_via')
            ->when($this->option('created-from'), fn ($q) => $q->where('created_at', '>=', $this->option('created-from')))
            ->get(['id', 'images'])
            ->reject(fn (Product $product): bool => $this->hasPhoto($product->images));

        if ($this->option('apply')) {
            Product::query()->whereIn('id', $cards->pluck('id'))->update([
                'is_active' => false,
                'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS,
                'updated_at' => now(),
            ]);
        }
        $this->line('metric.awaiting_photos.hidden='.$cards->count());

        return self::SUCCESS;
    }

    private function hasPhoto(mixed $images): bool
    {
        return array_filter((array) $images, fn ($path): bool => is_string($path) && trim($path) !== '' && $path !== '[]') !== [];
    }
}
