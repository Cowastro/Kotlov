<?php

namespace App\Services\Orders;

use App\Events\NewOrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Manual order taken by a manager (phone, messenger, shop visit): the same
 * order, items, economic snapshot and «new order» notifications as the
 * website checkout, from a short form of client, items, delivery and payment.
 */
class QuickOrderService
{
    /** Statuses a manager may put a manual order into right away. */
    public const START_STATUSES = ['new', 'checking', 'confirmed', 'processing'];

    /**
     * @param  array{customer_name:string, customer_phone:string, customer_email?:?string, items:array<int, array{product_id:int|string, quantity:int|string, price:float|string}>,
     *     delivery_type:string, delivery_city?:?string, delivery_address?:?string, delivery_price?:float|string|null, payment_type:string,
     *     company_name?:?string, company_unp?:?string, status?:?string, comment?:?string}  $data
     */
    public function create(array $data, User $manager): Order
    {
        $items = collect($data['items'] ?? [])
            ->filter(fn (array $item): bool => (int) ($item['product_id'] ?? 0) > 0)
            ->values();
        if ($items->isEmpty()) {
            throw new RuntimeException('Добавьте хотя бы один товар.');
        }
        if (trim((string) ($data['customer_phone'] ?? '')) === '' || trim((string) ($data['customer_name'] ?? '')) === '') {
            throw new RuntimeException('Укажите имя и телефон клиента.');
        }
        if (! isset(Order::DELIVERY_TYPES[$data['delivery_type'] ?? '']) || ! isset(Order::PAYMENT_TYPES[$data['payment_type'] ?? ''])) {
            throw new RuntimeException('Выберите доставку и оплату.');
        }

        $products = Product::query()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        $lines = $items->map(function (array $item) use ($products): array {
            $product = $products->get((int) $item['product_id']) ?? throw new RuntimeException('Товар не найден в базе.');
            $quantity = max(1, (int) $item['quantity']);
            $price = round(max(0, (float) $item['price']), 2);

            return ['product' => $product, 'quantity' => $quantity, 'price' => $price, 'total' => round($price * $quantity, 2)];
        });

        $subtotal = round($lines->sum('total'), 2);
        $deliveryPrice = $data['delivery_type'] === 'pickup' ? 0.0 : round(max(0, (float) ($data['delivery_price'] ?? 0)), 2);
        $status = in_array($data['status'] ?? '', self::START_STATUSES, true) ? $data['status'] : 'processing';
        $withCompany = in_array($data['payment_type'], ['invoice', 'bank_transfer'], true);

        $order = DB::transaction(function () use ($data, $manager, $lines, $subtotal, $deliveryPrice, $status, $withCompany): Order {
            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'status' => $status,
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => trim($data['customer_phone']),
                'customer_email' => trim((string) ($data['customer_email'] ?? '')) ?: null,
                'company_name' => $withCompany ? (trim((string) ($data['company_name'] ?? '')) ?: null) : null,
                'company_unp' => $withCompany ? (trim((string) ($data['company_unp'] ?? '')) ?: null) : null,
                'delivery_type' => $data['delivery_type'],
                'delivery_city' => $data['delivery_type'] === 'pickup' ? null : (trim((string) ($data['delivery_city'] ?? '')) ?: null),
                'delivery_address' => $data['delivery_type'] === 'pickup' ? null : (trim((string) ($data['delivery_address'] ?? '')) ?: null),
                'delivery_price' => $deliveryPrice,
                'payment_type' => $data['payment_type'],
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => round($subtotal + $deliveryPrice, 2),
                'comment' => trim((string) ($data['comment'] ?? '')) ?: null,
                'admin_comment' => 'Ручной заказ: оформил '.$manager->name.'.',
                'manager_id' => $manager->id,
                'assigned_to' => '@'.($manager->telegram_username ?? $manager->name),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'total' => $line['total'],
                    'pricing_type' => 'retail',
                ]);
            }

            app(OrderEconomicSnapshotRecorder::class)->capturePlaced($order->load('items'));

            return $order;
        });

        NewOrderCreated::dispatch($order->load('items'));

        return $order;
    }
}
