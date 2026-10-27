<?php

namespace App\Services;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class B2bProductPublicationManager
{
    /**
     * @param  iterable<int, Product>  $products
     * @return array{selected:int,published_products:int,published_offers:int,skipped:array<string,int>}
     */
    public function publish(iterable $products): array
    {
        $products = collect($products);
        $result = [
            'selected' => $products->count(),
            'published_products' => 0,
            'published_offers' => 0,
            'skipped' => [
                'inactive' => 0,
                'missing_supplier' => 0,
                'missing_retail_price' => 0,
                'missing_b2b_price' => 0,
                'source_or_category_not_allowed' => 0,
            ],
        ];
        $categoryParents = Category::query()
            ->pluck('parent_id', 'id')
            ->map(fn (mixed $parentId): int => (int) $parentId)
            ->all();

        DB::transaction(function () use ($products, $categoryParents, &$result): void {
            foreach ($products as $product) {
                $reason = $this->publicationBlockReason($product, $categoryParents);
                if ($reason !== null) {
                    $result['skipped'][$reason]++;

                    continue;
                }

                $offers = $this->eligibleOffers($product, $categoryParents);
                $updated = IntegrationProduct::query()
                    ->whereKey($offers->modelKeys())
                    ->update(['b2b_enabled' => true, 'updated_at' => now()]);

                if ($updated > 0) {
                    $result['published_products']++;
                    $result['published_offers'] += $updated;
                }
            }
        });

        return $result;
    }

    /** @param array<int, int> $categoryParents */
    private function publicationBlockReason(Product $product, array $categoryParents): ?string
    {
        if (! $product->is_active || $product->is_archived) {
            return 'inactive';
        }

        if ((float) $product->price <= 0) {
            return 'missing_retail_price';
        }

        if (! $product->hasAssignedSupplier()) {
            return 'missing_supplier';
        }

        $saleOffers = $this->saleOffers($product);
        if ($saleOffers->isEmpty()) {
            return 'missing_b2b_price';
        }

        if ($saleOffers->filter(
            fn (IntegrationProduct $offer): bool => $offer->source?->isB2bEnabled() === true
                && $this->sourceAllowsCategory($offer, $categoryParents)
        )->isEmpty()) {
            return 'source_or_category_not_allowed';
        }

        return null;
    }

    /** @param array<int, int> $categoryParents */
    private function eligibleOffers(Product $product, array $categoryParents)
    {
        return $this->saleOffers($product)->filter(
            fn (IntegrationProduct $offer): bool => $offer->source?->isB2bEnabled() === true
                && $this->sourceAllowsCategory($offer, $categoryParents)
        );
    }

    private function saleOffers(Product $product)
    {
        return $product->integrationProducts()
            ->with(['source.supplier', 'product:id,category_id'])
            ->where('match_status', 'matched')
            ->get()
            ->filter(fn (IntegrationProduct $offer): bool => $offer->source?->is_active === true
                && $offer->source?->supplier?->is_active === true
                && $offer->source->hasPublishableB2bPrice()
                && $offer->normalizedPriceByn() !== null)
            ->values();
    }

    /** @param array<int, int> $categoryParents */
    private function sourceAllowsCategory(IntegrationProduct $offer, array $categoryParents): bool
    {
        $allowed = array_fill_keys($offer->source?->b2bCategoryIds() ?? [], true);
        $categoryId = (int) ($offer->product?->category_id ?? 0);
        $visited = [];

        while ($categoryId > 0 && ! isset($visited[$categoryId])) {
            if (isset($allowed[$categoryId])) {
                return true;
            }

            $visited[$categoryId] = true;
            $categoryId = (int) ($categoryParents[$categoryId] ?? 0);
        }

        return false;
    }
}
