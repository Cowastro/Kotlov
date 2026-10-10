<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class IntegrationIssueDetector
{
    public function __construct(
        private readonly IntegrationIdentityCollisionFinder $collisionFinder,
        private readonly IntegrationFlowHealth $flowHealth,
        private readonly OrderIntegrationMonitoring $orderMonitoring,
    ) {}

    /** @return array{detected:int,opened:int,resolved:int,opened_issue_ids:array<int, int>} */
    public function scan(): array
    {
        $now = now();
        $seen = [];
        $opened = 0;
        $openedIssueIds = [];

        DB::transaction(function () use ($now, &$seen, &$opened, &$openedIssueIds): void {
            IntegrationSource::query()
                ->where('is_active', true)
                ->each(function (IntegrationSource $source) use ($now, &$seen, &$opened, &$openedIssueIds): void {
                    foreach ($this->flowHealth->snapshot($source, $now)['flows'] as $key => $flow) {
                        if ((! $flow['expected'] && $flow['status'] !== 'failed')
                            || ! in_array($flow['status'], ['failed', 'stale', 'unknown'], true)) {
                            continue;
                        }
                        $latestSuccess = $flow['latest_success'];
                        $latestRun = $flow['latest_run'];
                        $issue = $this->flowIssue($key, $source->partnerName());
                        $this->report(
                            $seen,
                            $opened,
                            $openedIssueIds,
                            "source:{$source->id}:{$key}:stale",
                            $issue['type'],
                            'danger',
                            $issue['title'],
                            $latestSuccess?->finished_at
                                ? 'Последний успешный цикл: '.$latestSuccess->finished_at->timezone('Europe/Minsk')->format('d.m.Y H:i:s')
                                : ($latestRun?->status === 'failed'
                                    ? 'Последний цикл завершился ошибкой: '.($latestRun->error_message ?: 'текст ошибки не указан').'.'
                                    : 'Успешных циклов этого направления ещё не было.'),
                            source: $source,
                            context: [
                                'flow' => $key,
                                'direction' => $flow['direction'],
                                'operation' => $flow['operation'],
                                'stale_after_minutes' => $flow['stale_after_minutes'],
                                'last_run_status' => $latestRun?->status,
                                'last_success_at' => $latestSuccess?->finished_at?->toIso8601String(),
                            ],
                        );
                    }

                    $this->detectAllPositiveStock($source, $seen, $opened, $openedIssueIds);

                    if ($source->exportsOrders()) {
                        $this->detectOrderDeliveryProblems($source, $now, $seen, $opened, $openedIssueIds);
                    }
                });

            IntegrationProduct::query()
                ->with(['source', 'integrationCategory'])
                ->inStock()
                ->where('match_status', '!=', 'ignored')
                ->chunkById(250, function ($products) use (&$seen, &$opened, &$openedIssueIds): void {
                    foreach ($products as $product) {
                        $missingPrice = (float) $product->price <= 0;
                        $unmatched = ! $product->product_id;
                        $missingCategory = $unmatched
                            && ! $product->target_category_id
                            && ! $product->integrationCategory?->category_id;

                        if ($missingPrice || $unmatched) {
                            $this->report(
                                $seen,
                                $opened,
                                $openedIssueIds,
                                "product:{$product->id}:attention",
                                'product_attention',
                                $missingPrice || $product->match_status === 'unmatched' ? 'danger' : 'warning',
                                $missingPrice ? 'Товар в наличии без цены' : 'Товар в наличии не привязан',
                                $product->name,
                                source: $product->source,
                                integrationProduct: $product,
                                context: [
                                    'missing_price' => $missingPrice,
                                    'unmatched' => $unmatched,
                                    'missing_category' => $missingCategory,
                                ],
                            );
                        }
                    }
                });

            foreach (IntegrationSource::query()->where('is_active', true)->get() as $source) {
                foreach ($this->collisionFinder->find($source->id) as $collision) {
                    $products = $collision['products'];
                    $firstProduct = IntegrationProduct::query()->find($products[0]['id']);
                    $identities = collect($collision['identities'])
                        ->map(fn (array $identity): string => ($identity['kind'] === 'barcode' ? 'штрихкод' : 'артикул').' «'.$identity['value'].'»')
                        ->implode(' и ');
                    $externalIds = collect($products)->pluck('external_id')->take(5)->implode(', ');
                    $fingerprintBasis = collect($products)
                        ->pluck('external_id')
                        ->sort()
                        ->implode('|');

                    $this->report(
                        $seen,
                        $opened,
                        $openedIssueIds,
                        'product-identity:'.$source->id.':'.sha1($fingerprintBasis),
                        'product_identity_collision',
                        'warning',
                        'Возможный дубль товара из 1С',
                        ucfirst($identities).' передан у '.count($products).' позиций с разными ID: '.$externalIds.'.',
                        source: $source,
                        integrationProduct: $firstProduct,
                        context: [
                            'identities' => $collision['identities'],
                            'product_ids' => array_column($products, 'id'),
                            'external_ids' => array_column($products, 'external_id'),
                        ],
                    );
                }
            }

        });

        $resolved = IntegrationIssue::query()
            ->where('status', 'open')
            ->whereIn('type', IntegrationIssue::PERIODIC_TYPES)
            ->when($seen !== [], fn ($query) => $query->whereNotIn('fingerprint', $seen))
            ->when($seen === [], fn ($query) => $query)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'detected' => count($seen),
            'opened' => $opened,
            'resolved' => $resolved,
            'opened_issue_ids' => $openedIssueIds,
        ];
    }

    /** @param array<int, string> $seen */
    private function detectOrderDeliveryProblems(
        IntegrationSource $source,
        CarbonInterface $now,
        array &$seen,
        int &$opened,
        array &$openedIssueIds,
    ): void {
        $this->orderMonitoring
            ->delayedDispatchQuery($source, $now)
            ->with(['integrationDeliveries' => fn ($query) => $query
                ->where('integration_source_id', $source->id)])
            ->each(function (Order $order) use ($source, &$seen, &$opened, &$openedIssueIds): void {
                $delivery = $order->integrationDeliveries->first();
                $message = $delivery?->last_attempted_at
                    ? $order->number.' сформирован для передачи '.$delivery->last_attempted_at
                        ->timezone('Europe/Minsk')->format('d.m.Y H:i').', но подтверждение success не получено.'
                    : $order->number.' создан '.$order->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i')
                        .' и ещё не запрошен источником.';

                $this->report(
                    $seen,
                    $opened,
                    $openedIssueIds,
                    $this->orderIssueFingerprint($source, $order, 'not-exported'),
                    'order_not_exported',
                    'danger',
                    'Заказ не передан: '.$source->partnerName(),
                    $message,
                    source: $source,
                    order: $order,
                    context: [
                        'route_status' => $delivery?->status ?? 'not_requested',
                        'last_attempted_at' => $delivery?->last_attempted_at?->toIso8601String(),
                        'delay_minutes' => $source->orderDispatchDelayMinutes(),
                    ],
                );
            });

        $this->orderMonitoring
            ->awaitingResponseQuery($source, $now)
            ->with('order')
            ->each(function (OrderIntegrationDelivery $delivery) use ($source, &$seen, &$opened, &$openedIssueIds): void {
                if (! $delivery->order) {
                    return;
                }

                $this->reportMissingOrderResponse(
                    $source,
                    $delivery->order,
                    $delivery->exported_at,
                    $seen,
                    $opened,
                    $openedIssueIds,
                    $delivery,
                );
            });

        $this->orderMonitoring
            ->legacyAwaitingResponseQuery($source, $now)
            ?->each(function (Order $order) use ($source, &$seen, &$opened, &$openedIssueIds): void {
                $this->reportMissingOrderResponse(
                    $source,
                    $order,
                    $order->onec_exported_at,
                    $seen,
                    $opened,
                    $openedIssueIds,
                );
            });
    }

    /** @param array<int, string> $seen */
    private function reportMissingOrderResponse(
        IntegrationSource $source,
        Order $order,
        ?CarbonInterface $exportedAt,
        array &$seen,
        int &$opened,
        array &$openedIssueIds,
        ?OrderIntegrationDelivery $delivery = null,
    ): void {
        $this->report(
            $seen,
            $opened,
            $openedIssueIds,
            $this->orderIssueFingerprint($source, $order, 'no-response'),
            'order_no_1c_response',
            'warning',
            'Нет статуса заказа: '.$source->partnerName(),
            $order->number.' передан '.($exportedAt?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? '—')
                .', но источник ещё не вернул статус.',
            source: $source,
            order: $order,
            context: [
                'route_status' => $delivery?->status ?? 'legacy_sent',
                'exported_at' => $exportedAt?->toIso8601String(),
                'response_timeout_minutes' => $source->orderResponseTimeoutMinutes(),
            ],
        );
    }

    private function orderIssueFingerprint(IntegrationSource $source, Order $order, string $suffix): string
    {
        return $source->code === 'onec'
            ? "order:{$order->id}:{$suffix}"
            : "source:{$source->id}:order:{$order->id}:{$suffix}";
    }

    /** @return array{type:string,title:string} */
    private function flowIssue(string $flow, string $sourceName): array
    {
        return match ($flow) {
            'orders' => [
                'type' => 'integration_orders_stale',
                'title' => '1С не забирает новые заказы: '.$sourceName,
            ],
            'order_statuses' => [
                'type' => 'integration_statuses_stale',
                'title' => '1С не возвращает статусы заказов: '.$sourceName,
            ],
            default => [
                'type' => 'integration_catalog_stale',
                'title' => 'Не поступают каталог, цены и остатки: '.$sourceName,
            ],
        };
    }

    /** @param array<int, string> $seen */
    private function detectAllPositiveStock(
        IntegrationSource $source,
        array &$seen,
        int &$opened,
        array &$openedIssueIds,
    ): void {
        $minimum = $source->allStockPositiveWarningMinimum();
        if ($minimum === 0) {
            return;
        }

        $stock = $source->products()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN stock_quantity > 0 THEN 1 ELSE 0 END) as positive')
            ->selectRaw('MIN(stock_quantity) as minimum_quantity')
            ->selectRaw('MAX(stock_quantity) as maximum_quantity')
            ->first();
        $total = (int) ($stock?->total ?? 0);
        $positive = (int) ($stock?->positive ?? 0);

        if ($total < $minimum || $positive !== $total) {
            return;
        }

        $minimumQuantity = (float) $stock->minimum_quantity;
        $maximumQuantity = (float) $stock->maximum_quantity;
        $this->report(
            $seen,
            $opened,
            $openedIssueIds,
            "source:{$source->id}:all-stock-positive",
            'catalog_all_stock_positive',
            'warning',
            'Все товары источника переданы с положительным остатком',
            $source->partnerName().": {$total} из {$total} позиций доступны; диапазон "
                .$this->formatQuantity($minimumQuantity).'–'.$this->formatQuantity($maximumQuantity).' шт.',
            source: $source,
            context: [
                'total' => $total,
                'positive_stock' => $positive,
                'minimum_quantity' => $minimumQuantity,
                'maximum_quantity' => $maximumQuantity,
                'warehouse_label' => data_get($source->settings, 'warehouse_label', 'Основной'),
            ],
        );
    }

    private function formatQuantity(float $quantity): string
    {
        return abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, ',', ' ')
            : rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',');
    }

    /** @param array<int, string> $seen */
    private function report(
        array &$seen,
        int &$opened,
        array &$openedIssueIds,
        string $fingerprint,
        string $type,
        string $severity,
        string $title,
        ?string $message = null,
        ?IntegrationSource $source = null,
        ?IntegrationProduct $integrationProduct = null,
        ?Order $order = null,
        array $context = [],
    ): void {
        $seen[] = $fingerprint;
        $issue = IntegrationIssue::query()->firstOrNew(['fingerprint' => $fingerprint]);
        $wasOpen = $issue->exists && $issue->status === 'open';
        $cachedAdvice = data_get($issue->context, 'ai_advice');

        if (is_array($cachedAdvice)) {
            $context['ai_advice'] = $cachedAdvice;
        }

        $issue->fill([
            'integration_source_id' => $source?->id,
            'integration_product_id' => $integrationProduct?->id,
            'order_id' => $order?->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'first_detected_at' => $issue->first_detected_at ?? now(),
            'last_detected_at' => now(),
        ]);

        if ($issue->status !== 'ignored') {
            $issue->status = 'open';
            $issue->resolved_at = null;
        }

        $issue->save();
        if (! $wasOpen && $issue->status === 'open') {
            $opened++;
            $openedIssueIds[] = $issue->id;
        }
    }
}
