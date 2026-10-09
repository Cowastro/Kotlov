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
    /** @return array{detected:int,opened:int,resolved:int} */
    public function scan(): array
    {
        $now = now();
        $seen = [];
        $opened = 0;

        DB::transaction(function () use ($now, &$seen, &$opened): void {
            IntegrationSource::query()
                ->where('is_active', true)
                ->each(function (IntegrationSource $source) use ($now, &$seen, &$opened): void {
                    $lastSuccess = IntegrationExchangeRun::query()
                        ->whereBelongsTo($source, 'source')
                        ->where('status', 'success')
                        ->latest('finished_at')
                        ->first();
                    $staleMinutes = max(5, (int) data_get($source->settings, 'stale_after_minutes', 15));

                    if (! $lastSuccess || $lastSuccess->finished_at?->lt($now->copy()->subMinutes($staleMinutes))) {
                        $this->report(
                            $seen,
                            $opened,
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
                ->chunkById(250, function ($products) use (&$seen, &$opened): void {
                    foreach ($products as $product) {
                        if (! $product->product_id) {
                            $this->report(
                                $seen,
                                $opened,
                                "product:{$product->id}:unmatched",
                                'product_unmatched',
                                $product->match_status === 'unmatched' ? 'danger' : 'warning',
                                'Товар в наличии не привязан',
                                $product->name,
                                source: $product->source,
                                integrationProduct: $product,
                            );

                            if (! $product->target_category_id && ! $product->integrationCategory?->category_id) {
                                $this->report(
                                    $seen,
                                    $opened,
                                    "product:{$product->id}:missing-category",
                                    'product_missing_category',
                                    'warning',
                                    'Не назначена категория сайта',
                                    $product->name,
                                    source: $product->source,
                                    integrationProduct: $product,
                                );
                            }
                        }

                        if ((float) $product->price <= 0) {
                            $this->report(
                                $seen,
                                $opened,
                                "product:{$product->id}:missing-price",
                                'product_missing_price',
                                'danger',
                                'Товар в наличии без цены',
                                $product->name,
                                source: $product->source,
                                integrationProduct: $product,
                            );
                        }
                    }
                });

            Order::query()
                ->whereNull('onec_exported_at')
                ->where('created_at', '<=', $now->copy()->subMinutes(10))
                ->each(function (Order $order) use (&$seen, &$opened): void {
                    $this->report(
                        $seen,
                        $opened,
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
                ->each(function (Order $order) use (&$seen, &$opened): void {
                    $this->report(
                        $seen,
                        $opened,
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

        return ['detected' => count($seen), 'opened' => $opened, 'resolved' => $resolved];
    }

    /** @param array<int, string> $seen */
    private function report(
        array &$seen,
        int &$opened,
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
        }
    }
}
