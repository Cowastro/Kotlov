<?php

namespace App\Services\Market;

use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MarketPriceIndicator
{
    public function __construct(private readonly MarketPriceSummary $summaries) {}

    /** @return array<string, mixed> */
    public function forProduct(Product $product, ?CarbonInterface $asOf = null): array
    {
        $summary = $this->summaries->forProduct($product, $asOf);
        $checkedAt = $summary['last_checked_at'] ?? $summary['latest_observed_at'];

        if ($summary['status'] !== 'ready') {
            $sourceProgress = $summary['sources_count'].'/'.MarketPriceSummary::MINIMUM_SOURCES.' источника';
            $checked = $checkedAt?->diffForHumans();

            return $summary + [
                'indicator_label' => 'Недостаточно данных',
                'indicator_description' => collect([$sourceProgress, $checked ? 'проверено '.$checked : null])
                    ->filter()
                    ->implode(' · '),
                'indicator_tooltip' => $summary['reason'],
                'indicator_color' => $summary['evidence']->isNotEmpty() ? 'warning' : 'gray',
                'indicator_icon' => 'heroicon-o-question-mark-circle',
            ];
        }

        $delta = (float) $summary['delta_percent'];
        $sign = $delta > 0 ? '+' : '';

        return $summary + [
            'indicator_label' => $summary['label'].' '.$sign.number_format($delta, 1, ',', ' ').'%',
            'indicator_description' => 'Наша '.number_format($summary['our_price'], 2, ',', ' ')
                .' BYN · медиана '.number_format($summary['median'], 2, ',', ' ')
                .' BYN · '.$summary['sources_count'].' источника',
            'indicator_tooltip' => collect([
                'Коридор '.number_format($summary['minimum'], 2, ',', ' ').'–'.number_format($summary['maximum'], 2, ',', ' ').' BYN',
                $checkedAt ? 'Проверено '.$checkedAt->diffForHumans() : null,
            ])->filter()->implode(' · '),
            'indicator_color' => match ($summary['position']) {
                'above' => 'danger',
                'below' => 'info',
                default => 'success',
            },
            'indicator_icon' => match ($summary['position']) {
                'above' => 'heroicon-o-arrow-trending-up',
                'below' => 'heroicon-o-arrow-trending-down',
                default => 'heroicon-o-scale',
            },
        ];
    }

    /** @return array<string, mixed> */
    public function forOrder(Order $order, ?CarbonInterface $asOf = null): array
    {
        $order->loadMissing('items.product.marketPriceObservations.source');

        $rows = $order->items
            ->map(function ($item) use ($asOf): array {
                $indicator = $item->product
                    ? $this->forProduct($item->product, $asOf)
                    : $this->missingProductIndicator();

                return [
                    'item' => $item,
                    'product' => $item->product,
                    'indicator' => $indicator,
                ];
            })
            ->values();

        $ready = $rows->filter(fn (array $row): bool => $row['indicator']['status'] === 'ready');
        $aboveCount = $ready->where('indicator.position', 'above')->count();
        $belowCount = $ready->where('indicator.position', 'below')->count();
        $insufficientCount = $rows->count() - $ready->count();

        [$label, $color, $icon] = match (true) {
            $aboveCount > 0 => ['Выше рынка: '.$aboveCount, 'danger', 'heroicon-o-arrow-trending-up'],
            $ready->isNotEmpty() => ['Рынок проверен', 'success', 'heroicon-o-scale'],
            default => ['Недостаточно данных', 'gray', 'heroicon-o-question-mark-circle'],
        };

        $parts = [];
        if ($rows->isNotEmpty()) {
            $parts[] = 'проверено '.$ready->count().' из '.$rows->count().' позиций';
        }
        if ($insufficientCount > 0) {
            $parts[] = 'без вывода: '.$insufficientCount;
        }
        if ($belowCount > 0) {
            $parts[] = 'ниже рынка: '.$belowCount;
        }

        return [
            'status' => $ready->isNotEmpty() ? 'ready' : 'insufficient',
            'label' => $label,
            'description' => $parts !== [] ? implode(' · ', $parts) : 'В заказе нет товарных позиций',
            'color' => $color,
            'icon' => $icon,
            'rows' => $rows,
            'ready_count' => $ready->count(),
            'above_count' => $aboveCount,
            'below_count' => $belowCount,
            'insufficient_count' => $insufficientCount,
        ];
    }

    /** @return array<string, mixed> */
    private function missingProductIndicator(): array
    {
        return [
            'status' => 'insufficient',
            'label' => 'Недостаточно данных',
            'reason' => 'Позиция заказа не связана с карточкой товара kotlov.by.',
            'sources_count' => 0,
            'offers_count' => 0,
            'our_price' => null,
            'minimum' => null,
            'median' => null,
            'maximum' => null,
            'delta_byn' => null,
            'delta_percent' => null,
            'position' => null,
            'latest_observed_at' => null,
            'last_checked_at' => null,
            'observations' => new Collection,
            'evidence' => new Collection,
            'indicator_label' => 'Недостаточно данных',
            'indicator_description' => 'Нет связи с карточкой kotlov.by',
            'indicator_tooltip' => 'Сначала сопоставьте позицию заказа с карточкой товара.',
            'indicator_color' => 'gray',
            'indicator_icon' => 'heroicon-o-question-mark-circle',
        ];
    }
}
