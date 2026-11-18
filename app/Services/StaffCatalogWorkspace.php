<?php

namespace App\Services;

use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\User;
use App\Services\Suppliers\SupplierOrderRules;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StaffCatalogWorkspace
{
    public static function allowed(?User $user): bool
    {
        return (bool) ($user?->is_active && ($user->isAdmin()
            || ($user->is_crm_user && $user->isOperationalManager())));
    }

    public static function canViewProcurement(?User $user): bool
    {
        return self::allowed($user);
    }

    public function current(Request $request): ?CustomerSelection
    {
        $query = CustomerSelection::query()->where('user_id', $request->user()->id);
        if ($request->filled('selection_id')) {
            $selection = $query->findOrFail($request->integer('selection_id'));
        } else {
            $selection = (clone $query)->find($request->session()->get('staff_selection_id'))
                ?? $this->selections($request)->latest('updated_at')->first();
        }
        if ($selection) {
            $request->session()->put('staff_selection_id', $selection->id);
        }

        return $selection;
    }

    public function selections(Request $request)
    {
        return CustomerSelection::query()->where('user_id', $request->user()->id)
            ->where('meta->staff_workspace', true);
    }

    public function offers(Product $product, User $user): Collection
    {
        if (! self::canViewProcurement($user)) {
            return collect();
        }
        $product->loadMissing(['supplierProducts.supplier.latestChannelTransition', 'integrationProducts.source.supplier', 'warehouseStocks.supplier', 'purchasePriceSources.supplier']);
        $contextProduct = clone $product;
        $contextProduct->setRelation('supplierProducts', $product->operationalSupplierProducts());
        $contextProduct->setRelation('integrationProducts', $product->operationalIntegrationProducts());
        $contextProduct->setRelation('warehouseStocks', $product->warehouseStocks->filter(fn ($stock) => $stock->supplier?->is_active));
        $offers = app(ManagerStorefrontProductContext::class)->offers($contextProduct, $user)->map(function ($offer) {
            // Return only procurement fields, without supplier contact details.
            return array_intersect_key($offer, array_flip(['supplier_key', 'stock_key', 'supplier', 'article', 'catalog_code', 'purchase_price', 'margin', 'margin_percent', 'stock', 'stock_quantity', 'in_stock', 'is_fresh', 'warehouse', 'warehouse_breakdown', 'locations', 'updated_at']));
        });
        foreach ($product->usablePurchasePriceSources() as $source) {
            $offers->push([
                'supplier_key' => 'document:'.$source->id, 'supplier' => $source->supplierDisplayName(),
                'article' => $source->documentLabel(), 'purchase_price' => (float) $source->price_byn,
                'stock_quantity' => null, 'stock' => 'Остаток уточняется', 'in_stock' => false,
                'is_fresh' => false, 'warehouse' => null, 'updated_at' => $source->confirmed_at,
            ]);
        }
        if ((float) $product->manual_purchase_price_byn > 0) {
            $offers->push([
                'supplier_key' => 'manual', 'supplier' => 'Ручная закупочная цена', 'article' => null,
                'purchase_price' => (float) $product->manual_purchase_price_byn,
                'stock_quantity' => null, 'stock' => 'Остаток уточняется', 'in_stock' => false,
                'is_fresh' => false, 'warehouse' => null, 'updated_at' => $product->manual_price_updated_at,
            ]);
        }

        return $offers->map(function ($offer) use ($product) {
            $source = $product->operationalSupplierProducts()->first(fn ($row) => $row->supplier_article === ($offer['article'] ?? null) && $row->supplier?->name === $offer['supplier']);
            $offer['order_rules'] = $source ? SupplierOrderRules::fromOffer($source) : [];
            $cost = $offer['purchase_price'];
            $retail = (float) $product->price;
            $offer['margin'] = $cost !== null && $retail > 0 ? round($retail - $cost, 2) : null;
            $offer['margin_percent'] = $cost !== null && $retail > 0 ? round(($retail - $cost) / $retail * 100, 1) : null;
            if (($offer['stock_quantity'] ?? null) === null && ! $offer['in_stock']) {
                $offer['stock'] = 'Остаток уточняется';
            }
            $offer['updated_at'] = $offer['updated_at']?->format('d.m.Y H:i');

            return $offer;
        })->sortBy(function (array $offer): int {
            // Prefer usable numeric stock over a cheaper textual availability.
            // Stable sorting preserves the existing order within each tier.
            if ($offer['is_fresh'] && (float) ($offer['stock_quantity'] ?? 0) > 0) {
                return 0;
            }

            return $offer['is_fresh'] && $offer['in_stock'] ? 1 : 2;
        })->values();
    }

    public function state(?CustomerSelection $selection, ?User $user = null): array
    {
        $items = array_values($selection?->items ?? []);
        if (! self::canViewProcurement($user)) {
            $items = array_map(fn ($item) => array_diff_key($item, ['workspace_purchase' => true]), $items);
        }
        $items = array_map(function (array $item): array {
            $rules = $item['order_rules'] ?? $item['workspace_purchase']['order_rules'] ?? [];
            $item['order_rules_label'] = SupplierOrderRules::label($rules);
            $item['order_rules_warning'] = SupplierOrderRules::warning($rules, (float) $item['quantity']);

            return $item;
        }, $items);
        $knownCosts = collect($items)->filter(fn ($item) => isset($item['workspace_purchase']['purchase_price']));
        $purchaseTotal = $knownCosts->sum(fn ($item) => $item['workspace_purchase']['purchase_price'] * $item['quantity']);
        $marginLines = $knownCosts->filter(fn ($item) => $item['price'] > 0);
        $matchedRetail = $marginLines->sum(fn ($item) => $item['price'] * $item['quantity']);
        $marginCost = $marginLines->sum(fn ($item) => $item['workspace_purchase']['purchase_price'] * $item['quantity']);

        return [
            'id' => $selection?->id, 'revision' => $selection?->revision,
            'name' => $selection?->name ?? 'Новый подбор',
            'items' => $items,
            'procurement' => self::canViewProcurement($user) ? [
                'total' => round($purchaseTotal, 2), 'margin' => round($matchedRetail - $marginCost, 2),
                'margin_percent' => $matchedRetail > 0 ? round(($matchedRetail - $marginCost) / $matchedRetail * 100, 1) : null,
                'unconfirmed' => count($items) - $knownCosts->count(),
                'margin_unconfirmed' => count($items) - $marginLines->count(),
            ] : null,
            'total' => $selection?->total() ?? 0,
            'saved_at' => $selection?->updated_at?->format('d.m.Y H:i'),
            'edit_url' => $selection ? route('selections.show', $selection) : null,
            'print_url' => $selection ? route('selections.show', [$selection, 'print' => 1]) : null,
            'desk_url' => $selection ? route('b2b.selections.show', $selection) : null,
        ];
    }
}
