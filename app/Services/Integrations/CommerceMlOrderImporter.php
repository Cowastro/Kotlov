<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class CommerceMlOrderImporter
{
    public function __construct(
        private readonly OrderIntegrationRouter $orderRouter,
        private readonly IntegrationOrderStatusMapper $statusMapper,
    ) {}

    /** @return array{documents:int,matched:int,updated:int,unchanged:int,unmatched:int,conflicts:int,unknown_statuses:int,attention:int} */
    public function import(string $xml, ?IntegrationSource $source = null): array
    {
        $document = $this->parse($xml);
        $stats = [
            'documents' => 0,
            'matched' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'unmatched' => 0,
            'conflicts' => 0,
            'unknown_statuses' => 0,
            'attention' => 0,
        ];

        DB::transaction(function () use ($document, $source, &$stats): void {
            foreach ($document->xpath('//*[local-name()="Документ"]') ?: [] as $node) {
                $stats['documents']++;
                $externalId = $this->text($node, './*[local-name()="Ид"]');
                $number = $this->text($node, './*[local-name()="Номер"]');
                $siteExternalId = $this->requisite($node, ['ID заказа KOTLOV', 'Внешний ID KOTLOV']);
                $order = $this->findOrder($externalId, $number, $source, $siteExternalId);

                if (! $order) {
                    $stats['unmatched']++;

                    continue;
                }

                $stats['matched']++;
                $delivery = $source ? OrderIntegrationDelivery::query()->firstOrNew([
                    'order_id' => $order->id,
                    'integration_source_id' => $source->id,
                ]) : null;
                $oldRemoteStatus = $delivery?->remote_status;
                $remoteId = $this->remoteId($node, $externalId);
                $remoteNumber = $this->remoteNumber($node, $number, $order);
                $rawStatus = $this->text($node, './*[local-name()="Статус"]')
                    ?: $this->requisite($node, ['Статус заказа', 'Статус']);
                $rawPaymentStatus = $this->requisite($node, ['Статус оплаты', 'Оплата']);
                $status = $this->statusMapper->orderStatus($rawStatus, $source);
                $paymentStatus = $this->statusMapper->paymentStatus($rawPaymentStatus, $source);
                $oldStatus = $order->status;
                $oldPaymentStatus = $order->payment_status;
                $updatesCentralOrder = ! $source || $source->code === 'onec';
                $unknownStatus = $rawStatus !== '' && $status === null;
                $unknownPaymentStatus = $rawPaymentStatus !== '' && $paymentStatus === null;
                $statusConflict = $updatesCentralOrder && $status !== null
                    && $status !== $oldStatus
                    && ! $this->canApplyStatus($oldStatus, $status);
                $paymentConflict = $updatesCentralOrder && $paymentStatus !== null
                    && $paymentStatus !== $oldPaymentStatus
                    && ! $this->canApplyPaymentStatus($oldPaymentStatus, $paymentStatus);

                $changes = $updatesCentralOrder ? array_filter([
                    'onec_external_id' => $externalId ?: null,
                    'onec_order_guid' => $remoteId ?: null,
                    'onec_order_number' => $remoteNumber ?: null,
                    'onec_status' => $rawStatus ?: null,
                    'onec_status_received_at' => now(),
                    'status' => $statusConflict ? null : $status,
                    'payment_status' => $paymentConflict ? null : $paymentStatus,
                ], static fn (mixed $value): bool => $value !== null) : [];

                $meaningfulChange = $updatesCentralOrder
                    ? (($status !== null && ! $statusConflict && $status !== $order->status)
                        || ($paymentStatus !== null && ! $paymentConflict && $paymentStatus !== $order->payment_status)
                        || ($rawStatus !== '' && $rawStatus !== $order->onec_status)
                        || ($remoteId !== '' && $remoteId !== $order->onec_order_guid)
                        || ($remoteNumber !== '' && $remoteNumber !== $order->onec_order_number))
                    : ($rawStatus !== '' && $rawStatus !== $oldRemoteStatus);

                if ($changes !== []) {
                    Order::withoutEvents(fn () => $order->update($changes));
                }

                if ($delivery) {
                    $delivery->fill([
                        'status' => OrderIntegrationDelivery::STATUS_ACKNOWLEDGED,
                        'external_id' => $delivery->external_id ?: 'kotlov-order-'.$order->id,
                        'remote_id' => $remoteId ?: $delivery->remote_id,
                        'remote_number' => $remoteNumber ?: $delivery->remote_number,
                        'remote_status' => $rawStatus ?: $delivery->remote_status,
                        'status_received_at' => now(),
                        'last_error' => null,
                    ])->save();
                }

                if ($updatesCentralOrder && $status !== null && ! $statusConflict && $status !== $oldStatus) {
                    OrderStatusHistory::query()->create([
                        'order_id' => $order->id,
                        'user_id' => null,
                        'status_from' => $oldStatus,
                        'status_to' => $status,
                        'comment' => 'Статус получен из 1С',
                    ]);
                }

                if ($updatesCentralOrder && ($statusConflict || $paymentConflict)) {
                    $this->recordConflict(
                        $order,
                        $source,
                        $oldStatus,
                        $statusConflict ? $status : null,
                        $rawStatus,
                        $oldPaymentStatus,
                        $paymentConflict ? $paymentStatus : null,
                        $rawPaymentStatus,
                    );
                    $stats['conflicts']++;
                } elseif ($updatesCentralOrder) {
                    $this->resolveConflict(
                        $order,
                        statusObserved: $status !== null,
                        paymentStatusObserved: $paymentStatus !== null,
                    );
                }

                if ($unknownStatus || $unknownPaymentStatus) {
                    $this->recordUnknownStatus(
                        $order,
                        $source,
                        $unknownStatus ? $rawStatus : null,
                        $unknownPaymentStatus ? $rawPaymentStatus : null,
                    );
                    $stats['unknown_statuses']++;
                } else {
                    $this->resolveUnknownStatus(
                        $order,
                        $source,
                        statusObserved: $status !== null,
                        paymentStatusObserved: $paymentStatus !== null,
                    );
                }

                if ($statusConflict || $paymentConflict || $unknownStatus || $unknownPaymentStatus) {
                    $stats['attention']++;
                } else {
                    $stats[$meaningfulChange ? 'updated' : 'unchanged']++;
                }
            }
        });

        return $stats;
    }

    private function canApplyStatus(string $current, string $incoming): bool
    {
        if ($current === $incoming) {
            return true;
        }

        if ($current === 'delivered' || $current === 'cancelled') {
            return false;
        }

        if ($incoming === 'cancelled') {
            return true;
        }

        $progress = [
            'new' => 0,
            'confirmed' => 1,
            'processing' => 2,
            'shipped' => 3,
            'delivered' => 4,
        ];

        return isset($progress[$current], $progress[$incoming])
            && $progress[$incoming] > $progress[$current];
    }

    private function canApplyPaymentStatus(string $current, string $incoming): bool
    {
        if ($current === $incoming) {
            return true;
        }

        return match ($current) {
            'paid' => $incoming === 'refunded',
            'refunded' => false,
            default => true,
        };
    }

    private function recordConflict(
        Order $order,
        ?IntegrationSource $source,
        string $currentStatus,
        ?string $incomingStatus,
        string $rawStatus,
        string $currentPaymentStatus,
        ?string $incomingPaymentStatus,
        string $rawPaymentStatus,
    ): void {
        $parts = [];
        if ($incomingStatus !== null) {
            $parts[] = 'статус заказа «'.(Order::STATUSES[$currentStatus] ?? $currentStatus)
                .'» → «'.(Order::STATUSES[$incomingStatus] ?? $incomingStatus).'»';
        }
        if ($incomingPaymentStatus !== null) {
            $parts[] = 'статус оплаты «'.$currentPaymentStatus.'» → «'.$incomingPaymentStatus.'»';
        }

        $issue = IntegrationIssue::query()->firstOrNew([
            'fingerprint' => "order:{$order->id}:status-conflict",
        ]);
        $wasIgnored = $issue->exists && $issue->status === 'ignored';
        $issue->fill([
            'integration_source_id' => $source?->id,
            'order_id' => $order->id,
            'type' => 'order_status_conflict',
            'severity' => 'danger',
            'title' => '1С пытается откатить состояние заказа',
            'message' => 'Автоматическое изменение заблокировано: '.implode('; ', $parts).'.',
            'context' => [
                'current_status' => $currentStatus,
                'incoming_status' => $incomingStatus,
                'raw_status' => $rawStatus,
                'current_payment_status' => $currentPaymentStatus,
                'incoming_payment_status' => $incomingPaymentStatus,
                'raw_payment_status' => $rawPaymentStatus,
            ],
            'first_detected_at' => $issue->first_detected_at ?? now(),
            'last_detected_at' => now(),
        ]);
        if (! $wasIgnored) {
            $issue->status = 'open';
            $issue->resolved_at = null;
        }
        $issue->save();
    }

    private function resolveConflict(
        Order $order,
        bool $statusObserved,
        bool $paymentStatusObserved,
    ): void {
        IntegrationIssue::query()
            ->where('order_id', $order->id)
            ->where('type', 'order_status_conflict')
            ->where('status', 'open')
            ->get()
            ->each(function (IntegrationIssue $issue) use ($statusObserved, $paymentStatusObserved): void {
                $needsStatus = data_get($issue->context, 'incoming_status') !== null;
                $needsPaymentStatus = data_get($issue->context, 'incoming_payment_status') !== null;

                if (($needsStatus && ! $statusObserved) || ($needsPaymentStatus && ! $paymentStatusObserved)) {
                    return;
                }

                $issue->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);
            });
    }

    private function recordUnknownStatus(
        Order $order,
        ?IntegrationSource $source,
        ?string $unknownStatus,
        ?string $unknownPaymentStatus,
    ): void {
        $parts = [];
        if ($unknownStatus !== null) {
            $parts[] = 'статус заказа «'.$unknownStatus.'»';
        }
        if ($unknownPaymentStatus !== null) {
            $parts[] = 'статус оплаты «'.$unknownPaymentStatus.'»';
        }

        $sourceFingerprint = $source && $source->code !== 'onec' ? ":source:{$source->id}" : '';
        $issue = IntegrationIssue::query()->firstOrNew([
            'fingerprint' => "order:{$order->id}{$sourceFingerprint}:unknown-onec-status",
        ]);
        $wasIgnored = $issue->exists && $issue->status === 'ignored';
        $issue->fill([
            'integration_source_id' => $source?->id,
            'order_id' => $order->id,
            'type' => 'order_status_unknown',
            'severity' => 'warning',
            'title' => 'Не распознан статус из 1С',
            'message' => 'Состояние заказа не изменено: '.implode('; ', $parts).'.',
            'context' => [
                'unknown_status' => $unknownStatus,
                'unknown_payment_status' => $unknownPaymentStatus,
            ],
            'first_detected_at' => $issue->first_detected_at ?? now(),
            'last_detected_at' => now(),
        ]);
        if (! $wasIgnored) {
            $issue->status = 'open';
            $issue->resolved_at = null;
        }
        $issue->save();
    }

    private function resolveUnknownStatus(
        Order $order,
        ?IntegrationSource $source,
        bool $statusObserved,
        bool $paymentStatusObserved,
    ): void {
        IntegrationIssue::query()
            ->where('order_id', $order->id)
            ->where('type', 'order_status_unknown')
            ->where('status', 'open')
            ->when($source, fn ($query) => $query->where('integration_source_id', $source->id))
            ->get()
            ->each(function (IntegrationIssue $issue) use ($statusObserved, $paymentStatusObserved): void {
                $needsStatus = data_get($issue->context, 'unknown_status') !== null;
                $needsPaymentStatus = data_get($issue->context, 'unknown_payment_status') !== null;

                if (($needsStatus && ! $statusObserved) || ($needsPaymentStatus && ! $paymentStatusObserved)) {
                    return;
                }

                $issue->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                ]);
            });
    }

    private function parse(string $xml): SimpleXMLElement
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
        $previous = libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($document === false) {
                throw new \InvalidArgumentException('Invalid CommerceML order XML.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function findOrder(
        string $externalId,
        string $number,
        ?IntegrationSource $source,
        string $siteExternalId = '',
    ): ?Order {
        if ($externalId === '' && $number === '' && $siteExternalId === '') {
            return null;
        }

        if ($source && ($externalId !== '' || $siteExternalId !== '')) {
            $deliveryOrder = Order::query()
                ->whereHas('integrationDeliveries', fn ($query) => $query
                    ->where('integration_source_id', $source->id)
                    ->where(function ($query) use ($externalId, $siteExternalId): void {
                        $query->when($externalId !== '', fn ($query) => $query
                            ->where('external_id', $externalId)
                            ->orWhere('remote_id', $externalId));
                        $query->when($siteExternalId !== '', fn ($query) => $query
                            ->orWhere('external_id', $siteExternalId));
                    }))
                ->first();
            if ($deliveryOrder) {
                return $deliveryOrder;
            }
        }

        $order = null;
        $localId = $siteExternalId ?: $externalId;
        if (preg_match('/^kotlov-order-(\d+)$/', $localId, $matches)) {
            $order = Order::query()->find((int) $matches[1]);
        }

        $order ??= Order::query()
            ->when($number !== '', fn ($query) => $query->where('number', $number))
            ->when($number === '' && $externalId !== '', fn ($query) => $query
                ->where('onec_external_id', $externalId)
                ->orWhere('onec_order_guid', $externalId))
            ->first();

        if ($order && $source && ! $this->orderRouter->orderBelongsToSource($order, $source)) {
            return null;
        }

        return $order;
    }

    private function remoteId(SimpleXMLElement $node, string $externalId): string
    {
        $remoteId = $this->text($node, './*[local-name()="Ид1С"]')
            ?: $this->text($node, './*[local-name()="GUID"]')
            ?: $this->text($node, './*[local-name()="УИД"]')
            ?: $this->requisite($node, ['GUID заказа 1С', 'Идентификатор заказа 1С', 'Ид заказа 1С']);

        if ($remoteId === '' && $externalId !== '' && ! preg_match('/^kotlov-order-\d+$/', $externalId)) {
            $remoteId = $externalId;
        }

        return $remoteId;
    }

    private function remoteNumber(SimpleXMLElement $node, string $number, Order $order): string
    {
        $remoteNumber = $this->requisite($node, ['Номер заказа 1С', 'Номер документа 1С']);

        if ($remoteNumber === '' && $number !== '' && $number !== $order->number) {
            $remoteNumber = $number;
        }

        return $remoteNumber;
    }

    /** @param array<int, string> $names */
    private function requisite(SimpleXMLElement $node, array $names): string
    {
        foreach ($node->xpath('.//*[local-name()="ЗначениеРеквизита"]') ?: [] as $requisite) {
            $name = $this->text($requisite, './*[local-name()="Наименование"]');
            if (in_array($name, $names, true)) {
                return $this->text($requisite, './*[local-name()="Значение"]');
            }
        }

        return '';
    }

    private function text(SimpleXMLElement $node, string $xpath): string
    {
        $result = $node->xpath($xpath);

        return trim((string) ($result[0] ?? ''));
    }
}
