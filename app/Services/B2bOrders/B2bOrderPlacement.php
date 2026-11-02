<?php

namespace App\Services\B2bOrders;

use App\Models\B2bOrder;
use App\Models\B2bOrderEvent;
use App\Models\B2bOrderItem;
use App\Models\IntegrationProduct;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\B2bCheckoutTerms;
use Illuminate\Support\Facades\DB;

class B2bOrderPlacement
{
    public function __construct(private readonly B2bCheckoutTerms $checkoutTerms) {}

    /**
     * @param array<int|string, array<string, mixed>> $cart
     * @param array<string, mixed> $data
     * @param array<string, mixed> $checkout
     */
    public function place(User $user, array $cart, array $data, array $checkout): B2bOrder
    {
        $client = $user->activeB2bClient(['responsibleUser', 'counterparty']);
        abort_unless($client, 403);

        $order = DB::transaction(function () use ($user, $client, $cart, $data, $checkout): B2bOrder {
            $total = round((float) $data['subtotal'] - (float) $data['discount'] + (float) $data['delivery_price'], 2);
            $terms = $checkout['terms_snapshot'] ?? [];

            if ($data['payment_type'] === 'invoice_deferred') {
                $credit = $this->checkoutTerms->assertDeferredPaymentAvailable($user, $total);
                $terms = array_merge($terms, [
                    'credit_exposure_before' => $credit['exposure'],
                    'credit_available_before' => $credit['available'],
                    'credit_available_after' => round((float) $credit['available'] - $total, 2),
                ]);
            }

            $manager = $client->responsibleUser;
            if (! $manager?->is_active || ! $manager->canManageB2bOrders()) {
                $manager = null;
            }

            $order = B2bOrder::create([
                'b2b_client_id' => $client->id,
                'created_by' => $user->id,
                'assigned_manager_id' => $manager?->id,
                'number' => B2bOrder::generateNumber(),
                'status' => $manager ? 'assigned' : 'new',
                'payment_status' => $data['payment_type'] === 'invoice_deferred' ? 'credit' : 'pending',
                'payment_type' => $data['payment_type'],
                'delivery_type' => $data['delivery_type'],
                'delivery_region' => $data['delivery_region'],
                'delivery_city' => $data['delivery_city'],
                'delivery_address' => $data['delivery_address'],
                'delivery_price' => $data['delivery_price'],
                'contact_name' => $data['customer_name'],
                'contact_phone' => $data['customer_phone'],
                'contact_email' => $data['customer_email'],
                'company_name' => $data['company_name'],
                'company_unp' => $data['company_unp'],
                'company_address' => $data['company_address'],
                'company_email' => $data['company_email'],
                'subtotal' => $data['subtotal'],
                'discount' => $data['discount'],
                'total' => $total,
                'commercial_terms_snapshot' => $terms,
                'client_comment' => $data['comment'],
            ]);

            foreach ($cart as $item) {
                $supplierProduct = ! empty($item['supplier_product_id'])
                    ? SupplierProduct::query()->with('supplier')->find($item['supplier_product_id'])
                    : null;
                $integrationProduct = ! empty($item['integration_product_id'])
                    ? IntegrationProduct::query()->with('source.supplier')->find($item['integration_product_id'])
                    : null;
                $supplier = $supplierProduct?->supplier ?? $integrationProduct?->source?->supplier;
                $purchasePrice = $supplierProduct?->price_byn !== null
                    ? (float) $supplierProduct->price_byn
                    : $integrationProduct?->normalizedPriceByn();

                B2bOrderItem::create([
                    'b2b_order_id' => $order->id,
                    'product_id' => $item['id'],
                    'integration_product_id' => $item['integration_product_id'] ?? null,
                    'supplier_product_id' => $item['supplier_product_id'] ?? null,
                    'warehouse_stock_id' => $item['warehouse_stock_id'] ?? null,
                    'supplier_id' => $supplier?->id,
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'] ?? null,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => round($item['price'] * $item['quantity'], 2),
                    'base_price' => $item['b2b_base_price'] ?? null,
                    'purchase_price' => $purchasePrice,
                    'supplier_name' => $supplier?->name,
                    'price_level_code' => $item['b2b_price_level_code'] ?? null,
                    'discount_percent' => $item['b2b_discount_percent'] ?? null,
                    'warehouse_name' => $item['warehouse_name'] ?? null,
                    'delivery_days' => $item['warehouse_delivery_days'] ?? null,
                ]);
            }

            B2bOrderEvent::create([
                'b2b_order_id' => $order->id,
                'actor_id' => $user->id,
                'event' => 'created',
                'to_status' => $order->status,
                'comment' => 'Заказ создан в B2B-кабинете.',
                'metadata' => ['assigned_manager_id' => $manager?->id],
            ]);

            return $order->load('items');
        });

        app(B2bOrderNotifier::class)->created($order);

        return $order;
    }
}
