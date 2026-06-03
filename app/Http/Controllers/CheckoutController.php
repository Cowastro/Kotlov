<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    private const CART_KEY = 'cart';

    // ─────────────────────────────────────────
    // Страница оформления заказа  GET /checkout
    // ─────────────────────────────────────────
    public function index()
    {
        $cart = session(self::CART_KEY, []);

        if (empty($cart)) {
            return redirect()->route('cart')->with('info', 'Корзина пуста.');
        }

        $subtotal = $this->calcSubtotal($cart);
        $user     = Auth::user();

        return view('pages.checkout', compact('cart', 'subtotal', 'user'));
    }

    // ─────────────────────────────────────────
    // Создать заказ  POST /checkout
    // ─────────────────────────────────────────
    public function store(Request $request)
    {
        $cart = session(self::CART_KEY, []);

        if (empty($cart)) {
            return redirect()->route('cart')->with('info', 'Корзина пуста.');
        }

        $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['required', 'string', 'max:30'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'delivery_type'    => ['required', 'in:pickup,courier,transport'],
            'delivery_region'  => ['nullable', 'string', 'max:255'],
            'delivery_city'    => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'payment_type'     => ['required', 'in:cash,card,invoice'],
            'comment'          => ['nullable', 'string', 'max:1000'],
        ], [
            'customer_name.required'  => 'Введите имя.',
            'customer_phone.required' => 'Введите телефон.',
            'delivery_type.required'  => 'Выберите способ доставки.',
            'payment_type.required'   => 'Выберите способ оплаты.',
        ]);

        $subtotal = $this->calcSubtotal($cart);

        $order = DB::transaction(function () use ($request, $cart, $subtotal) {

            $order = Order::create([
                'user_id'          => Auth::id(),
                'number'           => Order::generateNumber(),
                'status'           => 'new',

                'customer_name'    => $request->customer_name,
                'customer_phone'   => $request->customer_phone,
                'customer_email'   => $request->customer_email,

                'delivery_type'    => $request->delivery_type,
                'delivery_region'  => $request->delivery_region,
                'delivery_city'    => $request->delivery_city,
                'delivery_address' => $request->delivery_address,
                'delivery_price'   => 0,

                'payment_type'     => $request->payment_type,
                'payment_status'   => 'pending',

                'subtotal'         => $subtotal,
                'discount'         => 0,
                'total'            => $subtotal,

                'comment'          => $request->comment,
            ]);

            foreach ($cart as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item['id'],
                    'product_name' => $item['name'],
                    'product_sku'  => $item['sku'] ?? null,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'total'        => round($item['price'] * $item['quantity'], 2),
                ]);
            }

            return $order;
        });

        // Очищаем корзину
        session()->forget(self::CART_KEY);

        return redirect()
            ->route('checkout.success', $order->number)
            ->with('order_number', $order->number);
    }

    // ─────────────────────────────────────────
    // Страница успешного заказа  GET /checkout/success/{number}
    // ─────────────────────────────────────────
    public function success(string $number)
    {
        $order = Order::where('number', $number)
            ->with('items')
            ->firstOrFail();

        // Только владелец или незалогиненный (по номеру = тайный URL)
        if ($order->user_id && Auth::id() && $order->user_id !== Auth::id()) {
            abort(403);
        }

        return view('pages.checkout-success', compact('order'));
    }

    // ─────────────────────────────────────────
    // Хелпер
    // ─────────────────────────────────────────
    private function calcSubtotal(array $items): float
    {
        return round(array_sum(array_map(
            fn($i) => $i['price'] * $i['quantity'],
            $items
        )), 2);
    }
}
