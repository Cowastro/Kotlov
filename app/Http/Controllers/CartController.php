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

        $id = $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = min($cart[$id]['quantity'] + $qty, 999);
        } else {
            $images = $product->images ?? [];
            $cart[$id] = [
                'id'             => $id,
                'name'           => $product->name,
                'slug'           => $product->slug,
                'sku'            => $product->sku,
                'price'          => (float) $product->price,
                'image'          => $images[0] ?? null,
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
        $items = $this->getItems();
        return response()->json([
            'items'    => array_values($items),
            'count'    => $this->totalQty($items),
            'subtotal' => $this->calcSubtotal($items),
        ]);
    }

    // ─────────────────────────────────────────
    // Хелперы
    // ─────────────────────────────────────────
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
