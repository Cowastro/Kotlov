<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IntegrationProductCreator
{
    public function __construct(
        private readonly IntegrationManualMatchRecorder $recorder,
        private readonly SupplierProductAvailabilityService $availability,
    ) {}

    /**
     * @param array{name:string, category_id:int|string, retail_price:float|int|string,
     *     brand_id?:int|string|null, h1?:string|null, short_description?:string|null,
     *     content?:string|null, images?:array<int,string>, specs?:array<int,array<string,string>>,
     *     meta_title?:string|null, meta_description?:string|null} $data
     */
    public function createAndLink(IntegrationProduct $item, array $data): ?Product
    {
        return DB::transaction(function () use ($item, $data): ?Product {
            $item = IntegrationProduct::query()
                ->with('source')
                ->lockForUpdate()
                ->find($item->id);

            if (! $item || $item->product_id || $item->match_status === 'ignored') {
                return null;
            }

            $name = trim((string) $data['name']);
            $contentFields = Arr::only($data, [
                'brand_id',
                'h1',
                'short_description',
                'content',
                'images',
                'specs',
                'meta_title',
                'meta_description',
            ]);

            $product = Product::query()->create(array_merge([
                'category_id' => (int) $data['category_id'],
                'supplier_id' => $item->source?->supplier_id,
                'name' => $name,
                'slug' => 'new-product-'.Str::uuid(),
                'sku' => null,
                'price' => round((float) $data['retail_price'], 2),
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => false,
                'availability_status' => Product::AVAILABILITY_CHECK,
                'stock_qty' => null,
            ], $contentFields));

            $product->update([
                'sku' => $this->uniqueSku($product->id),
                'slug' => $this->uniqueSlug($name, $product->id),
            ]);

            $item->update([
                'product_id' => $product->id,
                'target_category_id' => (int) $data['category_id'],
                'match_status' => 'matched',
                'match_method' => 'manual_created',
                'match_confidence' => 1,
                'candidates' => null,
                'matched_at' => now(),
            ]);

            $this->recorder->record($item);
            $this->availability->refresh($product->id);

            return $product->fresh();
        });
    }

    private function uniqueSku(int $productId): string
    {
        $number = $productId;

        do {
            $sku = sprintf('KOTLOV-%06d', $number++);
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name, int $productId): string
    {
        $base = Str::slug($name) ?: 'product-'.$productId;
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->whereKeyNot($productId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
