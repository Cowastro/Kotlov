<?php

namespace App\Services\Marketplaces;

use App\Models\DealByOrderInbox;
use App\Models\Order;
use Throwable;

class DealByOrderStatusSync
{
    public function __construct(private readonly DealByClient $client) {}

    public function syncSafely(Order $order, ?string $comment = null): bool
    {
        $inbox = $order->dealByInbox()->first();
        $remoteStatus = $this->remoteStatus($order->status);

        if (! $inbox || ! $remoteStatus) {
            return true;
        }

        if ($inbox->remote_status === $remoteStatus) {
            $this->recordSuccess($inbox, $remoteStatus);

            return true;
        }

        try {
            $this->client->setOrderStatus([$inbox->remote_order_id], $remoteStatus, $comment);
            $inbox->forceFill([
                'remote_status' => $remoteStatus,
                'remote_status_name' => $this->remoteStatusLabel($remoteStatus),
            ]);
            $this->recordSuccess($inbox, $remoteStatus);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $inbox->forceFill([
                'push_attempts' => $inbox->push_attempts + 1,
                'last_push_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            return false;
        }
    }

    private function recordSuccess(DealByOrderInbox $inbox, string $remoteStatus): void
    {
        $inbox->forceFill([
            'last_pushed_status' => $remoteStatus,
            'last_pushed_at' => now(),
            'last_push_error' => null,
            'push_attempts' => $inbox->push_attempts + 1,
        ])->save();
    }

    private function remoteStatus(string $status): ?string
    {
        return match ($status) {
            'confirmed', 'processing', 'shipped' => 'received',
            'delivered', 'completed' => 'delivered',
            'cancelled' => 'canceled',
            default => null,
        };
    }

    private function remoteStatusLabel(string $status): string
    {
        return match ($status) {
            'received' => 'Принят',
            'delivered' => 'Доставлен',
            'canceled' => 'Отменён',
            default => $status,
        };
    }
}
