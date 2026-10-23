<?php

namespace App\Services\Marketplaces;

use App\Events\NewOrderCreated;
use App\Models\DealByOrderInbox;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Throwable;

class DealByOrderProjector
{
    public function __construct(
        private readonly DealByOrderStatusSync $statusSync,
    ) {}

    public function projectSafely(DealByOrderInbox $inbox): bool
    {
        try {
            $order = $this->project($inbox);

            // A successfully imported order is already accepted by our system,
            // so Deal.by must no longer keep it in the "New" queue.
            $this->statusSync->syncSafely($order);

            // Repeated polling projects the same inbox row again. Notify only
            // when the operational order was actually created for the first time.
            if ($order->wasRecentlyCreated) {
                NewOrderCreated::dispatch($order->load('items'));
            }

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $inbox->refresh()->forceFill([
                'processing_status' => DealByOrderInbox::STATUS_FAILED,
                'processing_attempts' => $inbox->processing_attempts + 1,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            return false;
        }
    }

    public function project(DealByOrderInbox $inbox): Order
    {
        return DB::transaction(function () use ($inbox): Order {
            $locked = DealByOrderInbox::query()->lockForUpdate()->findOrFail($inbox->id);

            if ($locked->order_id) {
                $locked->forceFill([
                    'processing_status' => DealByOrderInbox::STATUS_PROCESSED,
                    'processed_at' => $locked->processed_at ?: now(),
                    'last_error' => null,
                ])->save();

                return $locked->order()->firstOrFail();
            }

            $payload = $locked->payload;
            $products = collect(data_get($payload, 'products', []))
                ->filter(fn (mixed $product): bool => is_array($product))
                ->values();
            $subtotal = $products->sum(fn (array $product): float => $this->lineTotal($product));
            $deliveryPrice = $this->number(data_get($payload, 'delivery_cost'));
            $total = $this->number(data_get($payload, 'full_price', data_get($payload, 'price')));

            $order = Order::query()->create([
                'number' => 'DBY-'.$locked->remote_order_id,
                'status' => $this->initialStatus($locked),
                'customer_name' => $locked->customerName(),
                'customer_phone' => $locked->customerPhone() ?: 'Не указан',
                'customer_email' => $this->text(data_get($payload, 'email')),
                'delivery_type' => $this->deliveryType($payload),
                'delivery_region' => $this->text(data_get($payload, 'delivery_region')),
                'delivery_city' => $this->text(data_get($payload, 'delivery_city')),
                'delivery_address' => $this->deliveryAddress($payload),
                'delivery_price' => $deliveryPrice,
                'payment_type' => $this->paymentType($payload),
                'payment_status' => $this->paymentStatus($payload),
                'discount' => $this->number(data_get($payload, 'discount')),
                'subtotal' => $subtotal,
                'total' => $total > 0 ? $total : $subtotal + $deliveryPrice,
                'comment' => $this->text(data_get($payload, 'client_notes')),
                'admin_comment' => 'Импортирован автоматически из Deal.by.',
            ]);

            foreach ($products as $productPayload) {
                $sku = $this->text($productPayload['sku'] ?? null);
                $product = $sku ? Product::query()->where('sku', $sku)->first() : null;
                $quantity = max(1, (int) ($productPayload['quantity'] ?? 1));
                $price = $this->number($productPayload['price'] ?? null);

                $order->items()->create([
                    'product_id' => $product?->id,
                    'product_name' => $this->text($productPayload['name'] ?? null) ?: ($sku ?: 'Товар Deal.by'),
                    'product_sku' => $sku,
                    'price' => $price,
                    'quantity' => $quantity,
                    'total' => $this->lineTotal($productPayload) ?: $price * $quantity,
                ]);
            }

            if ($locked->remote_created_at) {
                $order->forceFill(['created_at' => $locked->remote_created_at])->saveQuietly();
            }

            $locked->forceFill([
                'order_id' => $order->id,
                'processing_status' => DealByOrderInbox::STATUS_PROCESSED,
                'processing_attempts' => $locked->processing_attempts + 1,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();

            return $order;
        });
    }

    /** @return array{processed:int,failed:int} */
    public function projectPending(int $limit = 500): array
    {
        $metrics = ['processed' => 0, 'failed' => 0];

        DealByOrderInbox::query()
            ->whereNull('order_id')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (DealByOrderInbox $inbox) use (&$metrics): void {
                $metrics[$this->projectSafely($inbox) ? 'processed' : 'failed']++;
            });

        return $metrics;
    }

    /** @param array<string, mixed> $payload */
    private function deliveryType(array $payload): string
    {
        $label = mb_strtolower((string) data_get($payload, 'delivery_option.name', ''));

        return match (true) {
            str_contains($label, 'самовывоз') => 'pickup',
            str_contains($label, 'транспорт'), str_contains($label, 'тк ') => 'transport',
            default => 'courier',
        };
    }

    /** @param array<string, mixed> $payload */
    private function paymentType(array $payload): string
    {
        $label = mb_strtolower((string) data_get($payload, 'payment_option.name', ''));

        return match (true) {
            str_contains($label, 'безнал'), str_contains($label, 'счёт'), str_contains($label, 'счет') => 'invoice',
            str_contains($label, 'карт') => 'card',
            default => 'cash',
        };
    }

    /** @param array<string, mixed> $payload */
    private function paymentStatus(array $payload): string
    {
        $status = mb_strtolower((string) data_get($payload, 'payment_status', ''));

        return str_contains($status, 'paid') || str_contains($status, 'оплачен') ? 'paid' : 'pending';
    }

    private function initialStatus(DealByOrderInbox $inbox): string
    {
        $status = mb_strtolower(collect([$inbox->remote_status, $inbox->remote_status_name])->filter()->implode(' '));

        return match (true) {
            str_contains($status, 'cancel'), str_contains($status, 'отмен') => 'cancelled',
            str_contains($status, 'deliver'), str_contains($status, 'достав') => 'delivered',
            str_contains($status, 'ship'), str_contains($status, 'отправ') => 'shipped',
            str_contains($status, 'process'), str_contains($status, 'обработ') => 'processing',
            str_contains($status, 'confirm'), str_contains($status, 'подтверж') => 'confirmed',
            default => 'new',
        };
    }

    /** @param array<string, mixed> $payload */
    private function deliveryAddress(array $payload): ?string
    {
        $address = data_get($payload, 'delivery_address');

        if (is_string($address)) {
            return $this->text($address);
        }

        if (is_array($address)) {
            return collect($address)->filter(fn (mixed $value): bool => is_scalar($value) && filled($value))->implode(', ');
        }

        return null;
    }

    /** @param array<string, mixed> $product */
    private function lineTotal(array $product): float
    {
        $total = $this->number($product['total_price'] ?? null);

        return $total > 0
            ? $total
            : $this->number($product['price'] ?? null) * max(1, (int) ($product['quantity'] ?? 1));
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
