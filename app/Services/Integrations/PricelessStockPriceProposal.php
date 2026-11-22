<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Market\MarketPriceSummary;
use Illuminate\Support\Collection;

/**
 * Prices for 1C goods in stock that have none: the site retail is checked against the purchase price of the
 * site suppliers and the market median, the wholesale is retail −20 % (as in 1C, «Оптовая с НДС» 89 to
 * «Розничная с НДС» 111) but never under purchase +5 %. Without a retail and a purchase price nothing is
 * proposed: the row asks for the cost.
 */
class PricelessStockPriceProposal
{
    public const WHOLESALE_SHARE = 0.80;

    public const MIN_RETAIL_MARGIN = 0.10;

    public const MIN_WHOLESALE_MARGIN = 0.05;

    public const NEW_RETAIL_MARKUP = 1.30;

    public const MAX_SANE_MARKUP = 2.5;

    public const VAT = 1.2;

    public function __construct(private readonly MarketPriceSummary $market) {}

    /**
     * @return Collection<int, array{row: IntegrationProduct, card: ?Product, retail_site: ?float, purchase: ?float, market: ?float,
     *     retail: ?float, wholesale: ?float, verdict: string, note: string}>
     */
    public function propose(IntegrationSource $source): Collection
    {
        $rows = IntegrationProduct::query()->where('integration_source_id', $source->id)
            ->where('stock_quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
            ->orderBy('name')->get()
            ->filter(fn (IntegrationProduct $row): bool => (float) $row->normalizedPriceByn() <= 0)
            ->values();
        $cards = Product::query()->whereIn('id', $rows->pluck('product_id')->filter())
            ->with(['supplierProducts.supplier.latestChannelTransition', 'purchasePriceSources.supplier'])->get()->keyBy('id');

        return $rows->map(fn (IntegrationProduct $row): array => $this->proposeRow($row, $row->product_id ? $cards->get($row->product_id) : null));
    }

    /** @return array{row: IntegrationProduct, card: ?Product, retail_site: ?float, purchase: ?float, market: ?float, retail: ?float, wholesale: ?float, verdict: string, note: string} */
    public function proposeRow(IntegrationProduct $row, ?Product $card): array
    {
        $retailSite = $card && (float) $card->price > 0 ? round((float) $card->price, 2) : null;
        $purchase = $card ? $this->purchase($card) : null;
        $market = $card ? $this->marketMedian($card) : null;
        $notes = [];

        if ($retailSite === null && $purchase === null) {
            return $this->result($row, $card, null, null, $market, null, null, 'need_cost',
                $card ? 'Нет розницы и закупки — запросить себестоимость' : 'Нет карточки на сайте — запросить себестоимость');
        }

        $verdict = 'ok';
        $retail = $retailSite;
        if ($retail === null) {
            $retail = round($purchase * self::NEW_RETAIL_MARKUP, 2);
            $verdict = 'computed';
            $notes[] = 'Розница рассчитана: закупка +30 %';
        } elseif ($purchase !== null && $retail < $purchase * (1 + self::MIN_RETAIL_MARGIN)) {
            $retail = round($purchase * self::NEW_RETAIL_MARKUP, 2);
            $verdict = 'raised';
            $notes[] = 'Розница на сайте даёт наценку меньше 10 % — поднята до закупки +30 %';
        } elseif ($purchase !== null && $retail > $purchase * self::MAX_SANE_MARKUP) {
            $verdict = 'check';
            $notes[] = 'Наценка больше 150 % — проверить закупку или розницу';
        }

        if ($market !== null && $retail > $market * 1.3) {
            $verdict = $verdict === 'ok' ? 'check' : $verdict;
            $notes[] = 'Дороже рынка более чем на 30 % (медиана '.number_format($market, 2, ',', ' ').')';
        } elseif ($market !== null && $retail < $market * 0.7) {
            $verdict = $verdict === 'ok' ? 'check' : $verdict;
            $notes[] = 'Дешевле рынка более чем на 30 % (медиана '.number_format($market, 2, ',', ' ').')';
        }

        $wholesale = round($retail * self::WHOLESALE_SHARE, 2);
        if ($purchase !== null && $wholesale < $purchase * (1 + self::MIN_WHOLESALE_MARGIN)) {
            $wholesale = round(min($retail, $purchase * (1 + self::MIN_WHOLESALE_MARGIN)), 2);
            $notes[] = 'Опт поднят до закупки +5 %';
        }
        if ($purchase === null) {
            $notes[] = 'Закупки на сайте нет — наценку проверить по себестоимости 1С';
        }

        return $this->result($row, $card, $retailSite, $purchase, $market, $retail, $wholesale, $verdict, implode('; ', $notes));
    }

    /**
     * What the card costs: its last purchase in 1C (with VAT) when 1C has one — that is what was really paid;
     * otherwise the lowest purchase price the site knows: a hand-set one, supplier price lists, official sources.
     */
    private function purchase(Product $card): ?float
    {
        if ((float) $card->onec_cost_vat_byn > 0) {
            return round((float) $card->onec_cost_vat_byn, 2);
        }
        $prices = collect([(float) $card->manual_purchase_price_byn])
            ->concat($card->operationalSupplierProducts()->pluck('price_byn'))
            ->concat($card->usablePurchasePriceSources()->pluck('price_byn'))
            ->map(fn ($price): float => (float) $price)
            ->filter(fn (float $price): bool => $price > 0);

        return $prices->isEmpty() ? null : round($prices->min(), 2);
    }

    private function marketMedian(Product $card): ?float
    {
        try {
            $summary = $this->market->forProduct($card);
        } catch (\Throwable) {
            return null;
        }

        return ($summary['status'] ?? null) === 'ready' && (float) ($summary['median'] ?? 0) > 0 ? round((float) $summary['median'], 2) : null;
    }

    private function result(IntegrationProduct $row, ?Product $card, ?float $retailSite, ?float $purchase, ?float $market,
        ?float $retail, ?float $wholesale, string $verdict, string $note): array
    {
        return ['row' => $row, 'card' => $card, 'retail_site' => $retailSite, 'purchase' => $purchase, 'market' => $market,
            'retail' => $retail, 'wholesale' => $wholesale, 'verdict' => $verdict, 'note' => $note];
    }
}
