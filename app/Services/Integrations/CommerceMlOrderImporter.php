<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class CommerceMlOrderImporter
{
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
                $order = $this->findOrder($externalId, $number);

                if (! $order) {
                    $stats['unmatched']++;

                    continue;
                }

                $stats['matched']++;
                $rawStatus = $this->text($node, './*[local-name()="Статус"]')
                    ?: $this->requisite($node, ['Статус заказа', 'Статус']);
                $rawPaymentStatus = $this->requisite($node, ['Статус оплаты', 'Оплата']);
                $status = $this->mapStatus($rawStatus);
                $paymentStatus = $this->mapPaymentStatus($rawPaymentStatus);
                $oldStatus = $order->status;
                $oldPaymentStatus = $order->payment_status;
                $unknownStatus = $rawStatus !== '' && $status === null;
                $unknownPaymentStatus = $rawPaymentStatus !== '' && $paymentStatus === null;
                $statusConflict = $status !== null
                    && $status !== $oldStatus
                    && ! $this->canApplyStatus($oldStatus, $status);
                $paymentConflict = $paymentStatus !== null
                    && $paymentStatus !== $oldPaymentStatus
                    && ! $this->canApplyPaymentStatus($oldPaymentStatus, $paymentStatus);

                $changes = array_filter([
                    'onec_external_id' => $externalId ?: null,
                    'onec_status' => $rawStatus ?: null,
                    'onec_status_received_at' => now(),
                    'status' => $statusConflict ? null : $status,
                    'payment_status' => $paymentConflict ? null : $paymentStatus,
                ], static fn (mixed $value): bool => $value !== null);

                $meaningfulChange = ($status !== null && ! $statusConflict && $status !== $order->status)
                    || ($paymentStatus !== null && ! $paymentConflict && $paymentStatus !== $order->payment_status)
                    || ($rawStatus !== '' && $rawStatus !== $order->onec_status);

                Order::withoutEvents(fn () => $order->update($changes));

                if ($status !== null && ! $statusConflict && $status !== $oldStatus) {
                    OrderStatusHistory::query()->create([
                        'order_id' => $order->id,
                        'user_id' => null,
                        'status_from' => $oldStatus,
                        'status_to' => $status,
                        'comment' => 'Статус получен из 1С',
                    ]);
                }

                if ($statusConflict || $paymentConflict) {
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
                } else {
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

        $issue = IntegrationIssue::query()->firstOrNew([
            'fingerprint' => "order:{$order->id}:unknown-onec-status",
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
        bool $statusObserved,
        bool $paymentStatusObserved,
    ): void {
        IntegrationIssue::query()
            ->where('order_id', $order->id)
            ->where('type', 'order_status_unknown')
            ->where('status', 'open')
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

    private function findOrder(string $externalId, string $number): ?Order
    {
        if ($externalId === '' && $number === '') {
            return null;
        }

        if (preg_match('/^kotlov-order-(\d+)$/', $externalId, $matches)) {
            $order = Order::query()->find((int) $matches[1]);
            if ($order) {
                return $order;
            }
        }

        return Order::query()
            ->when($number !== '', fn ($query) => $query->where('number', $number))
            ->when($number === '' && $externalId !== '', fn ($query) => $query->where('onec_external_id', $externalId))
            ->first();
    }

    private function mapStatus(string $status): ?string
    {
        $status = mb_strtolower(trim($status));

        return match (true) {
            $status === '' => null,
            str_contains($status, 'отмен') => 'cancelled',
            str_contains($status, 'достав'), str_contains($status, 'выполн'), str_contains($status, 'заверш') => 'delivered',
            str_contains($status, 'отгруж'), str_contains($status, 'отправ') => 'shipped',
            str_contains($status, 'обработ'), str_contains($status, 'сбор'), str_contains($status, 'комплект') => 'processing',
            str_contains($status, 'подтверж'), str_contains($status, 'принят') => 'confirmed',
            str_contains($status, 'нов') => 'new',
            default => null,
        };
    }

    private function mapPaymentStatus(string $status): ?string
    {
        $status = mb_strtolower(trim($status));

        return match (true) {
            $status === '' => null,
            str_contains($status, 'не опла'), str_contains($status, 'ожида') => 'pending',
            str_contains($status, 'возврат') => 'refunded',
            str_contains($status, 'опла') => 'paid',
            default => null,
        };
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
