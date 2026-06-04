<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // ключ в сессии
    private const KEY = 'cart';

    // ─────────────────────────────────────────
    // Страница корзины
    // ─────────────────────────────────────────
    public function index()
    {
        $items    = $this->getItems();
        $subtotal = $this->calcSubtotal($items);

        return view('pages.cart', compact('items', 'subtotal'));
    }

    // ─────────────────────────────────────────
    // Добавить товар  POST /cart/add
    // ─────────────────────────────────────────
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['sometimes', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::with('category:id,slug')->findOrFail($request->product_id);
        $qty     = max(1, (int) $request->input('quantity', 1));

        $cart = session(self::KEY, []);

        // Если корзина была пустой — сбрасываем старые данные прошлой сессии
        if (empty($cart)) {
            $this->clearCartMeta();
        }

        $id = $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = min($cart[$id]['quantity'] + $qty, 999);
        } else {
            $imgUrl = $product->image_url;

            $cart[$id] = [
                'id'             => $id,
                'name'           => $product->name,
                'slug'           => $product->slug,
                'sku'            => $product->sku,
                'price'          => (float) $product->price,
                'image'          => $imgUrl,
                'category_slug'  => $product->category->slug ?? 'catalog',
                'quantity'       => $qty,
            ];
        }

        session([self::KEY => $cart]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'added',
                'count'   => $this->totalQty($cart),
                'subtotal'=> $this->calcSubtotal($cart),
            ]);
        }

        return back()->with('success', 'Товар добавлен в корзину.');
    }

    // ─────────────────────────────────────────
    // Изменить количество  POST /cart/update
    // ─────────────────────────────────────────
    public function update(Request $request)
    {
        $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity'   => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cart = session(self::KEY, []);
        $id   = (int) $request->product_id;

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = (int) $request->quantity;
            session([self::KEY => $cart]);
        }

        if ($request->expectsJson()) {
            $items = $this->getItems();
            return response()->json([
                'count'        => $this->totalQty($cart),
                'subtotal'     => $this->calcSubtotal($cart),
                'item_total'   => isset($cart[$id])
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
                'message'  => 'removed',
                'count'    => $this->totalQty($cart),
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
    public function data()
    {
        $items     = $this->getItems();
        $subtotal  = $this->calcSubtotal($items);
        $threshold = (float) config('shop.free_delivery_threshold', 400);
        $remaining = max(0, $threshold - $subtotal);
        $progress  = $threshold > 0 ? min(100, round($subtotal / $threshold * 100)) : 100;

        return response()->json([
            'items'     => array_values($items),
            'count'     => $this->totalQty($items),
            'subtotal'  => $subtotal,
            'threshold' => $threshold,
            'remaining' => $remaining,
            'progress'  => $progress,
            'note'      => session('cart_note', ''),
            'coupon'    => session('cart_coupon', ''),
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
            'delivery_city'   => ['nullable', 'string', 'max:100'],
        ]);
        session([
            'cart_delivery_region' => $request->input('delivery_region', ''),
            'cart_delivery_city'   => $request->input('delivery_city', ''),
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
                'coupon'  => $code,
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

    private function calcSubtotal(array $items): float
    {
        return round(array_sum(array_map(
            fn($i) => $i['price'] * $i['quantity'],
            $items
        )), 2);
    }

    private function totalQty(array $items): int
    {
        return (int) array_sum(array_column($items, 'quantity'));
    }
}
