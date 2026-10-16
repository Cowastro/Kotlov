<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\B2bCatalogOfferResolver;
use App\Services\CartAvailabilityGuard;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // ключ в сессии
    private const KEY = 'cart';

    // ─────────────────────────────────────────
    // Страница корзины
    // ─────────────────────────────────────────
    public function index(CartAvailabilityGuard $availabilityGuard)
    {
        $items = $this->getAvailableItems($availabilityGuard);
        $subtotal = $this->calcSubtotal($items);

        return view('pages.cart', compact('items', 'subtotal'));
    }

    // ─────────────────────────────────────────
    // Добавить товар  POST /cart/add
    // ─────────────────────────────────────────
    public function add(Request $request, B2bCatalogOfferResolver $offerResolver)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::query()
            ->withSupplierChannels()
            ->with('category:id,slug')
            ->findOrFail($request->product_id);
        $b2bOffer = $offerResolver->forProduct($product, $request->user());

        if ($request->user()?->isB2B() && ! $b2bOffer) {
            $message = 'Товар недоступен для B2B-заказа: требуется подтверждённая привязка, цена и свежий остаток 1С.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        // Отключённый или снятый с продажи товар нельзя заказать даже через
        // сохранённую ссылку, старую корзину либо активное предложение B2B.
        if (! $product->is_active || $product->is_archived) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Товар недоступен для заказа.'], 422);
            }

            return back()->with('error', 'Товар недоступен для заказа.');
        }

        $canBeOrdered = $b2bOffer
            ? $product->is_active && ! $product->is_archived && $offerResolver->canOrder($b2bOffer)
            : $product->canBeOrdered();

        if (! $canBeOrdered) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Товар сейчас недоступен для оформления заказа.'], 422);
            }

            return back()->with('error', 'Товар сейчас недоступен для оформления заказа.');
        }

        $qty = max(1, (int) $request->input('quantity', 1));

        if ($b2bOffer && ! $offerResolver->canOrder($b2bOffer, $qty)) {
            $message = 'Запрошенное количество превышает доступный остаток поставщика.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $cart = session(self::KEY, []);

        // Если корзина была пустой — сбрасываем старые данные прошлой сессии
        if (empty($cart)) {
            $this->clearCartMeta();
        }

        $id = $product->id;
        $price = $b2bOffer ? $offerResolver->priceWithTax($b2bOffer) : (float) $product->price;
        $pricing = $b2bOffer ? [
            'pricing_type' => 'b2b',
            'price_tax_mode' => 'inclusive',
            'integration_product_id' => $b2bOffer->id,
            'source_name' => $b2bOffer->source?->name,
        ] : [
            'pricing_type' => 'retail',
            'price_tax_mode' => null,
            'integration_product_id' => null,
            'source_name' => null,
            'requires_availability_confirmation' => $product->requiresAvailabilityConfirmation(),
        ];

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = min($cart[$id]['quantity'] + $qty, 999);
            $cart[$id]['price'] = $price;
            $cart[$id] = array_merge($cart[$id], $pricing);
        } else {
            $imgUrl = $product->image_url;

            $cart[$id] = [
                'id' => $id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => $price,
                'image' => $imgUrl,
                'category_slug' => $product->category->slug ?? 'catalog',
                'quantity' => $qty,
            ] + $pricing;
        }

        session([self::KEY => $cart]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'added',
                'count' => $this->totalQty($cart),
                'subtotal' => $this->calcSubtotal($cart),
            ]);
        }

        return back()->with('success', 'Товар добавлен в корзину.');
    }

    // ─────────────────────────────────────────
    // Изменить количество  POST /cart/update
    // ─────────────────────────────────────────
    public function update(
        Request $request,
        B2bCatalogOfferResolver $offerResolver,
        CartAvailabilityGuard $availabilityGuard,
    ) {
        $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cart = session(self::KEY, []);
        $id = (int) $request->product_id;

        $availability = $availabilityGuard->reconcileRetailItems($cart);
        $cart = $availability['cart'];
        session([self::KEY => $cart]);

        if (in_array($id, $availability['removed_ids'], true)) {
            $message = 'Товар снят с продажи или больше недоступен для заказа и удалён из корзины.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        if (isset($cart[$id])) {
            if (($cart[$id]['pricing_type'] ?? 'retail') === 'b2b') {
                $product = Product::query()->find($id);
                $offer = $product ? $offerResolver->forProduct($product, $request->user()) : null;
                $quantity = (int) $request->quantity;

                if (! $offer
                    || (int) ($cart[$id]['integration_product_id'] ?? 0) !== (int) $offer->id
                    || ! $offerResolver->canOrder($offer, $quantity)) {
                    $message = 'B2B-позиция изменилась или её уже нельзя заказать в указанном количестве. Обновите корзину.';

                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 422)
                        : back()->with('error', $message);
                }

                $cart[$id]['price'] = $offerResolver->priceWithTax($offer);
            }

            $cart[$id]['quantity'] = (int) $request->quantity;
            session([self::KEY => $cart]);
        }

        if ($request->expectsJson()) {
            $items = $this->getItems();

            return response()->json([
                'count' => $this->totalQty($cart),
                'subtotal' => $this->calcSubtotal($cart),
                'item_total' => isset($cart[$id])
                    ? round($cart[$id]['price'] * $cart[$id]['quantity'], 2)
                    : 0,
            ]);
        }

        return back();
    }

    // ─────────────────────────────────────────
    // Удалить товар  POST /cart/remove
    // ─────────────────────────────────────────
    public function remove(Request $request)
    {
        $request->validate(['product_id' => ['required', 'integer']]);

        $cart = session(self::KEY, []);
        unset($cart[(int) $request->product_id]);
        session([self::KEY => $cart]);

        // Если корзина опустела — очищаем все связанные данные
        if (empty($cart)) {
            $this->clearCartMeta();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'removed',
                'count' => $this->totalQty($cart),
                'subtotal' => $this->calcSubtotal($cart),
            ]);
        }

        return back()->with('success', 'Товар удалён из корзины.');
    }

    // ─────────────────────────────────────────
    // Очистить корзину  POST /cart/clear
    // ─────────────────────────────────────────
    public function clear(Request $request)
    {
        session()->forget(self::KEY);
        $this->clearCartMeta();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'cleared', 'count' => 0]);
        }

        return back()->with('success', 'Корзина очищена.');
    }

    // ─────────────────────────────────────────
    // API: данные для mini-cart  GET /cart/data
    // ─────────────────────────────────────────
    public function data(CartAvailabilityGuard $availabilityGuard)
    {
        $items = $this->getAvailableItems($availabilityGuard);
        $subtotal = $this->calcSubtotal($items);
        $threshold = (float) config('shop.free_delivery_threshold', 400);
        $remaining = max(0, $threshold - $subtotal);
        $progress = $threshold > 0 ? min(100, round($subtotal / $threshold * 100)) : 100;

        return response()->json([
            'items' => array_values($items),
            'count' => $this->totalQty($items),
            'subtotal' => $subtotal,
            'threshold' => $threshold,
            'remaining' => $remaining,
            'progress' => $progress,
            'note' => session('cart_note', ''),
            'coupon' => session('cart_coupon', ''),
        ]);
    }

    // ─────────────────────────────────────────
    // Заметка к заказу  POST /cart/note
    // ─────────────────────────────────────────
    public function saveNote(Request $request)
    {
        $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        session(['cart_note' => $request->input('note', '')]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'saved']);
        }

        return back();
    }

    // ─────────────────────────────────────────
    // Способ доставки  POST /cart/delivery
    // ─────────────────────────────────────────
    public function saveDelivery(Request $request)
    {
        $request->validate([
            'delivery_region' => ['nullable', 'string', 'max:100'],
            'delivery_city' => ['nullable', 'string', 'max:100'],
        ]);
        session([
            'cart_delivery_region' => $request->input('delivery_region', ''),
            'cart_delivery_city' => $request->input('delivery_city', ''),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'saved']);
        }

        return back();
    }

    // ─────────────────────────────────────────
    // Промокод  POST /cart/coupon
    // ─────────────────────────────────────────
    public function saveCoupon(Request $request)
    {
        $request->validate(['coupon' => ['nullable', 'string', 'max:50']]);
        $code = strtoupper(trim($request->input('coupon', '')));
        session(['cart_coupon' => $code]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'saved',
                'coupon' => $code,
            ]);
        }

        return back();
    }

    // ─────────────────────────────────────────
    // Хелперы
    // ─────────────────────────────────────────

    /** Очистить вспомогательные данные корзины (заметка, купон, доставка) */
    private function clearCartMeta(): void
    {
        session()->forget([
            'cart_note',
            'cart_coupon',
            'cart_delivery_region',
            'cart_delivery_city',
        ]);
    }

    private function getItems(): array
    {
        return session(self::KEY, []);
    }

    private function getAvailableItems(CartAvailabilityGuard $availabilityGuard): array
    {
        $availability = $availabilityGuard->reconcileRetailItems($this->getItems());

        if ($availability['removed_ids'] !== [] || $availability['price_changed_ids'] !== []) {
            session([self::KEY => $availability['cart']]);
        }

        return $availability['cart'];
    }

    private function calcSubtotal(array $items): float
    {
        return round(array_sum(array_map(
            fn ($i) => $i['price'] * $i['quantity'],
            $items
        )), 2);
    }

    private function totalQty(array $items): int
    {
        return (int) array_sum(array_column($items, 'quantity'));
    }
}
