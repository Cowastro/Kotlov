<?php

namespace App\Services\Orders;

use App\Events\NewOrderCreated;
use App\Models\ContactRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaOrderShipment;
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

    public function __construct(private readonly EvropochtaCheckout $evropochta) {}

    public function assessEvropochta(array $items, array $measurements = []): array
    {
        $cart = collect($items)->filter(fn (array $item): bool => (int) ($item['product_id'] ?? 0) > 0)
            ->map(fn (array $item): array => ['id' => (int) $item['product_id'], 'quantity' => max(1, (int) ($item['quantity'] ?? 1))])
            ->values()
            ->all();

        $assessment = $this->evropochta->assess($cart);
        if ($assessment['parcel'] !== null || $measurements === [] || $cart === []
            || ! config('services.evropochta.checkout_enabled') || ! app(Evropochta::class)->configured()) {
            return $assessment;
        }
        $parcel = ['store_id_start' => config('services.evropochta.checkout_origin')];
        foreach (['weight', 'length', 'width', 'height'] as $field) {
            $value = $measurements[$field] ?? null;
            if (! is_numeric($value) || (float) $value <= 0 || (float) $value > ($field === 'weight' ? 30 : 250)) {
                return ['parcel' => null, 'message' => 'Укажите фактический вес (до 30 кг) и все размеры посылки в упаковке.'];
            }
            $parcel[$field] = (float) $value;
        }
        if (Evropochta::isOversize($parcel)) {
            return ['parcel' => null, 'message' => 'Упаковка превышает допустимые размеры Европочты. Выберите другой способ доставки.'];
        }

        return ['parcel' => $parcel + ['items' => array_map(fn (array $item): array => [
            'product_id' => $item['id'], 'quantity' => $item['quantity'],
        ], $cart)], 'message' => null];
    }

    /**
     * @param  array{customer_name:string, customer_phone:string, customer_email?:?string, items:array<int, array{product_id:int|string, quantity:int|string, price:float|string}>,
     *     delivery_type:string, delivery_city?:?string, delivery_address?:?string, delivery_price?:float|string|null, payment_type:string,
     *     company_name?:?string, company_unp?:?string, status?:?string, comment?:?string,
     *     evropochta_branch?:?int, evropochta_token?:?string}  $data
     */
    public function create(array $data, User $manager, ?ContactRequest $contactRequest = null): Order
    {
        if ($contactRequest) {
            abort_unless($manager->is_active && $manager->isManager(), 403);
            if ($existing = ContactRequest::query()->findOrFail($contactRequest->id)->order) {
                return $existing->load('items');
            }
        }
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

        $usesEvropochta = $data['delivery_type'] === EvropochtaCheckout::METHOD;
        if ($usesEvropochta && ! in_array($data['payment_type'], [EvropochtaCheckout::COD_PAYMENT, EvropochtaCheckout::PAYMENT], true)) {
            throw new RuntimeException('Выберите оплату при получении или предоплату Европочты.');
        }

        $quote = null;
        if ($usesEvropochta) {
            if ((int) ($data['evropochta_branch'] ?? 0) < 1) {
                throw new RuntimeException('Выберите отделение получения Европочты.');
            }
            ['parcel' => $parcel, 'message' => $message] = $this->assessEvropochta($items->all(), (array) ($data['parcel'] ?? []));
            if ($parcel === null) {
                throw new RuntimeException($message ?: 'Укажите упаковку и рассчитайте тариф Европочты.');
            }
            $quote = $this->evropochta->confirmedQuote(
                $parcel,
                (int) $data['evropochta_branch'],
                is_string($data['evropochta_token'] ?? null) ? $data['evropochta_token'] : '',
                session()->getId(),
            );
            if ($quote === null || blank($quote['city'] ?? null) || blank($quote['address'] ?? null)) {
                throw new RuntimeException('Выберите отделение и дождитесь расчёта тарифа Европочты.');
            }
        }

        $subtotal = round($lines->sum('total'), 2);
        $deliveryPrice = match ($data['delivery_type']) {
            'pickup' => 0.0,
            EvropochtaCheckout::METHOD => (float) ($quote['customer_price'] ?? 0),
            default => round(max(0, (float) ($data['delivery_price'] ?? 0)), 2),
        };
        $status = in_array($data['status'] ?? '', self::START_STATUSES, true) ? $data['status'] : 'processing';
        $withCompany = in_array($data['payment_type'], ['invoice', 'bank_transfer'], true);

        $created = false;
        $order = DB::transaction(function () use ($data, $manager, $lines, $subtotal, $deliveryPrice, $status, $withCompany, $quote, $usesEvropochta, $contactRequest, &$created): Order {
            $lead = $contactRequest ? ContactRequest::query()->lockForUpdate()->findOrFail($contactRequest->id) : null;
            if ($lead?->order_id) {
                return $lead->order()->firstOrFail();
            }
            if ($lead && ($lead->archived_at || $lead->status === 'cancelled')) {
                throw new RuntimeException('Сначала верните обращение в работу.');
            }
            $order = Order::query()->create([
                'number' => Order::generateNumber(),
                'status' => $status,
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => trim($data['customer_phone']),
                'customer_email' => trim((string) ($data['customer_email'] ?? '')) ?: null,
                'company_name' => $withCompany ? (trim((string) ($data['company_name'] ?? '')) ?: null) : null,
                'company_unp' => $withCompany ? (trim((string) ($data['company_unp'] ?? '')) ?: null) : null,
                'delivery_type' => $data['delivery_type'],
                'delivery_city' => $quote['city'] ?? ($data['delivery_type'] === 'pickup' || $usesEvropochta ? null : (trim((string) ($data['delivery_city'] ?? '')) ?: null)),
                'delivery_address' => $quote['address'] ?? ($data['delivery_type'] === 'pickup' || $usesEvropochta ? null : (trim((string) ($data['delivery_address'] ?? '')) ?: null)),
                'delivery_region' => $quote['region'] ?? null,
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

            if ($quote !== null) {
                $order->update(['evropochta_quote' => app(EvropochtaOrderShipment::class)->bindQuoteToOrder($quote, $order)]);
            }

            app(OrderEconomicSnapshotRecorder::class)->capturePlaced($order->load('items'));

            if ($lead) {
                $lead->forceFill(['order_id' => $order->id, 'status' => 'done', 'next_follow_up_at' => null,
                    'responsible_user_id' => $lead->responsible_user_id ?: $manager->id])->save();
                $order->managerComments()->create(['user_id' => $manager->id, 'author_name' => $manager->name,
                    'body' => 'Создан из обращения №'.$lead->id.'.']);
            }
            $created = true;

            return $order;
        });

        if ($created) {
            NewOrderCreated::dispatch($order->load('items'));
        }

        return $order;
    }
}
