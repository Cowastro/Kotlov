<?php

namespace App\Services\Integrations;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class IntegrationIssueDetector
{
    public function __construct(
        private readonly IntegrationMonitoringWindow $monitoringWindow,
        private readonly IntegrationIdentityCollisionFinder $collisionFinder,
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
                    $lastSuccess = IntegrationExchangeRun::query()
                        ->whereBelongsTo($source, 'source')
                        ->where('status', 'success')
                        ->latest('finished_at')
                        ->first();
                    $staleMinutes = $source->staleAfterMinutes();

                    if (! $lastSuccess || $lastSuccess->finished_at?->lt($now->copy()->subMinutes($staleMinutes))) {
                        $this->report(
                            $seen,
                            $opened,
                            $openedIssueIds,
                            "source:{$source->id}:stale",
                            'integration_stale',
                            'danger',
                            'Нет свежего обмена с '.$source->partnerName(),
                            $lastSuccess?->finished_at
                                ? 'Последний успешный обмен: '.$lastSuccess->finished_at->timezone('Europe/Minsk')->format('d.m.Y H:i:s')
                                : 'Успешных сеансов обмена ещё не было.',
                            source: $source,
                            context: ['stale_after_minutes' => $staleMinutes],
                        );
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

            $monitorOrdersFrom = $this->monitoringWindow->ordersStartAt();

            Order::query()
                ->whereNull('onec_exported_at')
                ->when($monitorOrdersFrom, fn ($query) => $query->where('created_at', '>=', $monitorOrdersFrom))
                ->where('created_at', '<=', $now->copy()->subMinutes(10))
                ->each(function (Order $order) use (&$seen, &$opened, &$openedIssueIds): void {
                    $this->report(
                        $seen,
                        $opened,
                        $openedIssueIds,
                        "order:{$order->id}:not-exported",
                        'order_not_exported',
                        'danger',
                        'Заказ не передан в 1С',
                        $order->number.' создан '.$order->created_at->timezone('Europe/Minsk')->format('d.m.Y H:i'),
                        order: $order,
                    );
                });

            Order::query()
                ->whereNotNull('onec_exported_at')
                ->whereNull('onec_status_received_at')
                ->where('onec_exported_at', '<=', $now->copy()->subMinutes(15))
                ->each(function (Order $order) use (&$seen, &$opened, &$openedIssueIds): void {
                    $this->report(
                        $seen,
                        $opened,
                        $openedIssueIds,
                        "order:{$order->id}:no-response",
                        'order_no_1c_response',
                        'warning',
                        'Нет подтверждения заказа от 1С',
                        $order->number.' передан '.$order->onec_exported_at->timezone('Europe/Minsk')->format('d.m.Y H:i'),
                        order: $order,
                    );
                });
        });

        $resolved = IntegrationIssue::query()
            ->where('status', 'open')
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
