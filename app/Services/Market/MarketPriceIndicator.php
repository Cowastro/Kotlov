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
        $warnings = $this->warnings($summary);
        $primaryWarning = $warnings->first();

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
                'warnings' => $warnings,
                'primary_warning' => $primaryWarning,
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
            'warnings' => $warnings,
            'primary_warning' => $primaryWarning,
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

                if ($item->product) {
                    $warnings = $indicator['warnings'];
                    $supply = $item->supplyContext();
                    $minimumMargin = (float) config('shop.order_management.minimum_margin_percent', 10);

                    if ($supply['has_purchase_price']
                        && $supply['margin_percent'] !== null
                        && (float) $supply['margin_percent'] < $minimumMargin) {
                        $warnings->prepend([
                            'code' => 'margin_below_minimum',
                            'label' => 'Маржа ниже '.number_format($minimumMargin, 0, ',', ' ').'%',
                            'description' => 'Позиция даёт '.number_format((float) $supply['margin_percent'], 1, ',', ' ').'% при входной цене '
                                .number_format((float) $supply['wholesale_price'], 2, ',', ' ').' BYN. Снижать цену без пересчёта нельзя.',
                            'color' => (float) $supply['margin_percent'] < 0 ? 'danger' : 'warning',
                        ]);
                    }

                    $indicator['warnings'] = $warnings;
                    $indicator['primary_warning'] = $warnings->first();
                }

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
        $warningCount = $rows->filter(fn (array $row): bool => $row['indicator']['warnings']->isNotEmpty())->count();

        [$label, $color, $icon] = match (true) {
            $warningCount > 0 => ['Требует внимания: '.$warningCount, 'danger', 'heroicon-o-exclamation-triangle'],
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
            'warning_count' => $warningCount,
        ];
    }

    /** @param array<string, mixed> $summary
     * @return Collection<int, array{code:string,label:string,description:string,color:string}>
     */
    private function warnings(array $summary): Collection
    {
        $warnings = collect();

        if ($summary['status'] === 'ready' && $summary['position'] === 'above') {
            $warnings->push([
                'code' => 'above_market',
                'label' => 'Цена выше рынка',
                'description' => 'Наша цена выше медианы на '.number_format(abs((float) $summary['delta_percent']), 1, ',', ' ').'%.',
                'color' => 'danger',
            ]);
        }

        $suspiciousLowThreshold = (float) config('shop.market_intelligence.suspicious_low_percent', 5);
        if ($summary['status'] === 'ready'
            && $summary['position'] === 'below'
            && (float) $summary['minimum'] > 0
            && (float) $summary['our_price'] < (float) $summary['minimum'] * (1 - $suspiciousLowThreshold / 100)) {
            $belowMinimum = round(((float) $summary['minimum'] - (float) $summary['our_price']) / (float) $summary['minimum'] * 100, 1);
            $warnings->push([
                'code' => 'suspicious_low_price',
                'label' => 'Подозрительно низкая цена',
                'description' => 'Наша цена на '.$belowMinimum.'% ниже минимального свежего предложения. Проверьте комплектацию и маржу.',
                'color' => 'warning',
            ]);
        }

        if ($summary['evidence']->contains(fn (array $evidence): bool => $evidence['code'] === 'stale')) {
            $warnings->push([
                'code' => 'stale_data',
                'label' => 'Есть устаревшие данные',
                'description' => 'Один или несколько источников нужно проверить заново.',
                'color' => 'warning',
            ]);
        }

        if ((int) $summary['sources_count'] < MarketPriceSummary::MINIMUM_SOURCES) {
            $warnings->push([
                'code' => 'insufficient_sources',
                'label' => 'Мало источников',
                'description' => 'Подтверждено '.$summary['sources_count'].' из '.MarketPriceSummary::MINIMUM_SOURCES.' необходимых независимых источников.',
                'color' => 'warning',
            ]);
        }

        return $warnings;
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
            'warnings' => collect([[
                'code' => 'missing_product_link',
                'label' => 'Нет связи с карточкой',
                'description' => 'Сначала сопоставьте позицию заказа с карточкой kotlov.by.',
                'color' => 'warning',
            ]]),
            'primary_warning' => [
                'code' => 'missing_product_link',
                'label' => 'Нет связи с карточкой',
                'description' => 'Сначала сопоставьте позицию заказа с карточкой kotlov.by.',
                'color' => 'warning',
            ],
        ];
    }
}
