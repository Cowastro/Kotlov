<?php

namespace App\Services;

use App\Events\NewOrderCreated;
use App\Models\ChimneyCalculationRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderEconomicSnapshotRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChimneyCalculationRequestService
{
    public function __construct(
        private readonly CartCouponService $coupons,
        private readonly ChimneyBundlePromotion $promotion,
    ) {}

    public function create(array $data): ChimneyCalculationRequest
    {
        $submitted = collect($data['items'])->take(80);
        $quantities = $submitted->mapWithKeys(fn (array $item): array => [
            (int) ($item['product_id'] ?? 0) => max(1, min(99, (int) ($item['quantity'] ?? 1))),
        ])->filter(fn (int $quantity, int $id): bool => $id > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Сначала рассчитайте комплект дымохода.']);
        }

        $products = Product::query()->with(['category', 'brand'])->whereIn('id', $quantities->keys())->get()->keyBy('id');
        if ($products->count() !== $quantities->count()) {
            throw ValidationException::withMessages(['items' => 'Одна из позиций комплекта больше недоступна.']);
        }

        foreach ($products as $product) {
            if (! $product->canBeAddedToCart() || ! $this->isAllowedSelectorProduct($product)) {
                throw ValidationException::withMessages(['items' => "Позиция «{$product->name}» недоступна для расчёта."]);
            }
        }

        $cart = [];
        foreach ($quantities as $id => $quantity) {
            $product = $products->get($id);
            $cart[$id] = $this->cartItem($product, $quantity, true);
        }

        $equipment = null;
        if (! empty($data['equipment_product_id'])) {
            $equipment = Product::query()->with('category')->find((int) $data['equipment_product_id']);
            if (! $equipment || ! $equipment->canBeAddedToCart() || ! $this->promotion->productIsEligibleEquipment($equipment)) {
                throw ValidationException::withMessages(['equipment_product_id' => 'Выбранное оборудование недоступно.']);
            }
            $cart[$equipment->id] = $this->cartItem($equipment, 1, false);
        }

        $quote = $this->coupons->quoteForCart(null, $cart);
        $items = collect($cart)->map(fn (array $item): array => [
            'product_id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'total' => round($item['price'] * $item['quantity'], 2),
            'chimney_selector_selected' => $item['chimney_selector_selected'],
        ])->values()->all();

        return ChimneyCalculationRequest::create([
            'number' => $this->nextNumber(),
            'status' => 'pending',
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'configuration' => $data['configuration'] ?? null,
            'items' => $items,
            'equipment_product_id' => $equipment?->id,
            'subtotal' => collect($items)->sum('total'),
            'discount_percent' => $quote['bundle']['active'] ? $quote['bundle']['percent'] : 0,
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'promotion_mode' => $quote['bundle']['mode'],
        ]);
    }

    public function revise(ChimneyCalculationRequest $calculation, array $submittedItems): ChimneyCalculationRequest
    {
        if ($calculation->status !== 'pending') {
            throw ValidationException::withMessages(['items' => 'Изменять можно только неподтверждённый расчёт.']);
        }

        $submitted = collect($submittedItems)->take(80);
        $quantities = $submitted->mapWithKeys(fn (array $item): array => [
            (int) ($item['product_id'] ?? 0) => max(1, min(99, (int) ($item['quantity'] ?? 1))),
        ])->filter(fn (int $quantity, int $id): bool => $id > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'В комплекте должна остаться хотя бы одна позиция.']);
        }

        $products = Product::query()->with(['category', 'brand'])->whereIn('id', $quantities->keys())->get()->keyBy('id');
        if ($products->count() !== $quantities->count()) {
            throw ValidationException::withMessages(['items' => 'Одна из позиций комплекта больше недоступна.']);
        }

        $existing = collect($calculation->items ?? [])->keyBy(fn (array $item): int => (int) ($item['product_id'] ?? 0));
        $cart = [];
        foreach ($quantities as $id => $quantity) {
            $product = $products->get($id);
            if (! $product->canBeAddedToCart() || ! $this->isAllowedSelectorProduct($product)) {
                throw ValidationException::withMessages(['items' => "Позиция «{$product->name}» недоступна для расчёта."]);
            }

            $selected = (bool) ($existing->get($id)['chimney_selector_selected'] ?? true);
            if ((int) $calculation->equipment_product_id === $id) {
                $selected = false;
            }
            $cart[$id] = $this->cartItem($product, $quantity, $selected);
        }

        $quote = $this->coupons->quoteForCart(null, $cart);
        $items = collect($cart)->map(fn (array $item): array => [
            'product_id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'total' => round($item['price'] * $item['quantity'], 2),
            'chimney_selector_selected' => $item['chimney_selector_selected'],
        ])->values()->all();

        $calculation->update([
            'items' => $items,
            'subtotal' => collect($items)->sum('total'),
            'discount_percent' => $quote['bundle']['active'] ? $quote['bundle']['percent'] : 0,
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'promotion_mode' => $quote['bundle']['mode'],
        ]);

        return $calculation->fresh();
    }

    /** @return array{order: Order, created: bool} */
    public function confirm(ChimneyCalculationRequest $calculation, string $confirmedBy, ?User $user = null): array
    {
        $created = false;
        $order = DB::transaction(function () use ($calculation, $confirmedBy, $user, &$created): Order {
            $locked = ChimneyCalculationRequest::query()->lockForUpdate()->findOrFail($calculation->id);
            if ($locked->order_id) {
                return Order::with('items')->findOrFail($locked->order_id);
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Этот расчёт уже закрыт.']);
            }

            $order = Order::create([
                'number' => Order::generateNumber(),
                'status' => 'new',
                'customer_name' => $locked->customer_name,
                'customer_phone' => $locked->customer_phone,
                'customer_email' => $locked->customer_email,
                'delivery_type' => 'pickup',
                'delivery_price' => 0,
                'payment_type' => 'cash',
                'payment_status' => 'pending',
                'subtotal' => $locked->subtotal,
                'discount' => $locked->discount,
                'promotion_breakdown' => [
                    'chimney_bundle_discount' => (float) $locked->discount,
                    'chimney_bundle_percent' => (float) $locked->discount_percent,
                    'chimney_bundle_mode' => $locked->promotion_mode,
                    'chimney_calculation_request_id' => $locked->id,
                ],
                'total' => $locked->total,
                'comment' => 'Заказ сформирован из расчёта дымохода '.$locked->number.'. Доставка и способ оплаты уточняются менеджером.',
                'assigned_to' => $confirmedBy,
                'manager_id' => $user?->id,
            ]);

            foreach ($locked->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'] ?? null,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['total'],
                    'pricing_type' => 'retail',
                ]);
            }

            app(OrderEconomicSnapshotRecorder::class)->capturePlaced($order->load('items'));

            $locked->update([
                'status' => 'converted',
                'confirmed_at' => now(),
                'confirmed_by_name' => $confirmedBy,
                'confirmed_by_user_id' => $user?->id,
                'order_id' => $order->id,
            ]);
            $created = true;

            return $order->load('items');
        });

        if ($created) {
            NewOrderCreated::dispatch($order);
        }

        return ['order' => $order, 'created' => $created];
    }

    private function cartItem(Product $product, int $quantity, bool $selected): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'quantity' => $quantity,
            'category_slug' => $product->category?->slug,
            'pricing_type' => 'retail',
            'chimney_selector_selected' => $selected,
        ];
    }

    private function isAllowedSelectorProduct(Product $product): bool
    {
        if ($this->promotion->productIsChimney($product)) {
            return true;
        }

        $allowedBrands = array_filter([
            (string) config('shop.chimney_bundle_discount.preferred_brand_slug'),
            'darco',
        ]);
        if (in_array((string) $product->brand?->slug, $allowedBrands, true)) {
            return true;
        }

        return Str::contains(Str::lower($product->name), ['master flash', 'мастер флеш', 'мастер-флеш']);
    }

    private function nextNumber(): string
    {
        return 'DYM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
