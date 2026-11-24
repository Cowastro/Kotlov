<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\OneCItem;
use App\Models\OneCPriceBatch;
use App\Models\OneCPriceBatchItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Cost of every 1C item from the posted purchases read into onec_purchase_lines (BYN): the price of the last one; when
 * it has none, the average of the last 3 months. With VAT (the prices for 1C are set with VAT) and without.
 */
class OneCItemCosts
{
    public const LAST = 'last';

    public const AVERAGE = 'avg3m';

    /** @param list<string>|null $itemKeys null — every item that was ever bought @return int items with a cost */
    public function refresh(?array $itemKeys = null): int
    {
        $itemKeys ??= DB::table('onec_purchase_lines')->where('currency_key', OneCPurchaseCosts::BYN)->distinct()->pluck('item_key')->all();
        $n = 0;
        foreach (array_chunk(array_values(array_unique(array_map('strtolower', $itemKeys))), 200) as $chunk) {
            $lines = DB::table('onec_purchase_lines')->whereIn('item_key', $chunk)->where('currency_key', OneCPurchaseCosts::BYN)
                ->orderBy('doc_date')->orderBy('id')->get()->groupBy('item_key');
            foreach ($lines as $key => $rows) {
                $cost = self::cost($rows->all());
                if ($cost !== null) {
                    $n += OneCItem::query()->where('ref_key', $key)->update($cost);
                }
            }
        }

        return $n;
    }

    /** @param list<object> $rows purchase lines of one item, oldest first @return array<string, mixed>|null */
    public static function cost(array $rows): ?array
    {
        $last = end($rows);
        if (! $last) {
            return null;
        }
        if ((float) $last->price_gross > 0) {
            return ['cost_net' => round((float) $last->price_net, 2), 'cost_gross' => round((float) $last->price_gross, 2),
                'cost_date' => substr((string) $last->doc_date, 0, 10), 'cost_doc' => $last->doc_number, 'cost_kind' => self::LAST];
        }
        $since = date('Y-m-d', strtotime(substr((string) $last->doc_date, 0, 10).' -3 months'));
        $recent = array_filter($rows, fn (object $r): bool => (float) $r->price_gross > 0 && substr((string) $r->doc_date, 0, 10) >= $since);
        $qty = array_sum(array_map(fn (object $r): float => (float) $r->quantity, $recent));
        if ($recent === [] || $qty <= 0) {
            return null;
        }
        $avg = fn (string $field): float => round(array_sum(array_map(fn (object $r): float => (float) $r->{$field} * (float) $r->quantity, $recent)) / $qty, 2);

        return ['cost_net' => $avg('price_net'), 'cost_gross' => $avg('price_gross'), 'cost_date' => substr((string) $last->doc_date, 0, 10),
            'cost_doc' => null, 'cost_kind' => self::AVERAGE];
    }

    /** Retail price of the item in 1C with VAT («Розничная с НДС»), or null. */
    public static function retail1c(OneCItem $item): ?float
    {
        if ($item->prices_synced_at !== null) {
            return $item->price_retail ?: null;
        }
        $price = IntegrationProduct::query()->whereHas('source', fn ($q) => $q->where('code', 'onec'))
            ->where(fn ($q) => $q->where('external_id', $item->ref_key)->orWhere('external_id', 'like', $item->ref_key.'#%'))
            ->get(['price', 'price_byn'])
            // the BYN price; the exchange price as it came when it was not converted
            ->map(fn (IntegrationProduct $p): float => (float) $p->price_byn > 0 ? (float) $p->price_byn : (float) $p->price)->max();

        return (float) $price > 0 ? round((float) $price, 2) : null;
    }

    /**
     * What a price is counted from: the 1C cost; when the goods have not come yet (no purchase in 1C) — the purchase
     * price of the linked card's suppliers (with VAT, the lowest).
     *
     * @return array{cost: float, net: ?float, label: string}|null
     */
    public static function basis(OneCItem $item): ?array
    {
        if ((float) $item->cost_gross > 0) {
            return ['cost' => (float) $item->cost_gross, 'net' => $item->cost_net ?: null, 'label' => 'себестоимость 1С ('.($item->cost_kind === self::AVERAGE
                ? 'средняя за 3 мес.' : 'закупка '.$item->cost_date?->format('d.m.Y')).')'];
        }
        $price = self::supplierBasis($item);

        return $price ? $price + ['label' => 'закупка поставщика (в 1С ещё не поступал)'] : null;
    }

    public static function supplierPrice(OneCItem $item): ?float
    {
        return self::supplierBasis($item)['cost'] ?? null;
    }

    private static function supplierBasis(OneCItem $item): ?array
    {
        $productId = $item->product_id ?: IntegrationProduct::query()->whereHas('source', fn ($q) => $q->where('code', 'onec'))
            ->where(fn ($q) => $q->where('external_id', $item->ref_key)->orWhere('external_id', 'like', $item->ref_key.'#%'))
            ->whereNotNull('product_id')->value('product_id');
        $card = $productId ? Product::query()->find($productId) : null;
        if (! $card) {
            return null;
        }
        return self::cardSupplierBasis($card);
    }

    /** Compare supplier sources on a VAT-inclusive basis, retaining a documented net cost. */
    public static function cardSupplierBasis(Product $card): ?array
    {
        $prices = collect([['cost' => (float) $card->manual_purchase_price_byn, 'net' => null]]);
        foreach ($card->operationalSupplierProducts() as $offer) {
            $amount = (float) $offer->price_byn;
            $exclusive = data_get($offer->raw, 'vat_included') === false;
            $prices->push([
                'cost' => $exclusive ? round($amount * config('onec.price_documents.vat_rate'), 2) : $amount,
                'net' => $exclusive ? $amount : null,
            ]);
        }
        foreach ($card->usablePurchasePriceSources() as $source) {
            $prices->push(['cost' => (float) $source->price_byn, 'net' => null]);
        }

        return $prices->where('cost', '>', 0)->sortBy('cost')->first();
    }

    /** The prices we sent to 1C last for the item (a batch handed to the agent), or null. */
    public static function lastSent(OneCItem $item): ?OneCPriceBatchItem
    {
        return OneCPriceBatchItem::query()->where('item_key', strtolower($item->ref_key))
            ->whereHas('batch', fn ($q) => $q->whereIn('status', [OneCPriceBatch::APPROVED, OneCPriceBatch::SENDING, OneCPriceBatch::CREATED]))
            ->latest('id')->first();
    }

    /** «+35 %» of a price over the cost with VAT, or null. */
    public static function markup(?float $price, OneCItem $item): ?string
    {
        $cost = (float) (self::basis($item)['cost'] ?? 0);

        return $price && $cost > 0 ? ($price >= $cost ? '+' : '').round(($price / $cost - 1) * 100).' %' : null;
    }

    /** Whole rubles, as set by the markup. */
    public static function priceFrom(float $cost, float $markupPercent): float
    {
        return (float) round($cost * (1 + $markupPercent / 100));
    }
}
