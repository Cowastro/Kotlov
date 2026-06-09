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

        $subtotal  = $this->calcSubtotal($cart);
        $user      = Auth::user();
        $threshold = (float) config('shop.free_delivery_threshold', 400);

        return view('pages.checkout', compact('cart', 'subtotal', 'user', 'threshold'));
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
            'customer_phone'   => ['required', 'string', 'max:30', 'regex:/^[\d\s\+\(\)\-]{7,}$/'],
            'customer_email'   => ['nullable', 'email', 'max:255'],
            'delivery_type'    => ['required', 'in:' . implode(',', array_keys(config('shop.delivery_methods', [])))],
            'delivery_region'  => ['required_unless:delivery_type,pickup', 'nullable', 'string', 'max:255'],
            'delivery_city'    => ['required_unless:delivery_type,pickup', 'nullable', 'string', 'max:255'],
            'delivery_address' => ['required_unless:delivery_type,pickup', 'nullable', 'string', 'max:500'],
            'payment_type'     => ['required', 'in:' . implode(',', array_keys(config('shop.payment_methods', [])))],
            'comment'          => ['nullable', 'string', 'max:1000'],
            // Реквизиты для счёта
            'company_name'     => ['required_if:payment_type,invoice', 'nullable', 'string', 'max:255'],
            'company_unp'      => ['nullable', 'string', 'max:50'],
            'company_address'  => ['nullable', 'string', 'max:500'],
            'company_email'    => ['nullable', 'email', 'max:255'],
        ], [
            'customer_name.required'         => 'Введите имя.',
            'customer_phone.required'        => 'Введите телефон.',
            'customer_phone.regex'           => 'Введите корректный номер телефона.',
            'delivery_type.required'         => 'Выберите способ доставки.',
            'payment_type.required'          => 'Выберите способ оплаты.',
            'delivery_region.required_unless' => 'Выберите область / регион доставки.',
            'delivery_city.required_unless'   => 'Укажите город доставки.',
            'delivery_address.required_unless' => 'Укажите адрес доставки.',
            'company_name.required_if'        => 'Введите название организации для оплаты по счёту.',
        ]);

        $subtotal      = $this->calcSubtotal($cart);
        $deliveryPrice = self::calcDelivery($request->delivery_type, $subtotal) ?? 0;
        $total         = $subtotal + $deliveryPrice;

        $order = DB::transaction(function () use ($request, $cart, $subtotal, $deliveryPrice, $total) {

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
                'delivery_price'   => $deliveryPrice,

                'payment_type'     => $request->payment_type,
                'payment_status'   => 'pending',

                // Реквизиты для счёта (только если выбран invoice)
                'company_name'     => $request->payment_type === 'invoice' ? $request->company_name    : null,
                'company_unp'      => $request->payment_type === 'invoice' ? $request->company_unp     : null,
                'company_address'  => $request->payment_type === 'invoice' ? $request->company_address : null,
                'company_email'    => $request->payment_type === 'invoice' ? $request->company_email   : null,

                'subtotal'         => $subtotal,
                'discount'         => 0,
                'total'            => $total,

                // comment: что пользователь написал в форме; если поле пустое — null
                'comment'          => $request->filled('comment') ? $request->comment : null,
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

        // Очищаем корзину и вспомогательные данные
        session()->forget([
            self::CART_KEY,
            'cart_note',
            'cart_coupon',
            'cart_delivery_region',
            'cart_delivery_city',
        ]);

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
    // Хелперы
    // ─────────────────────────────────────────

    /**
     * Рассчитать стоимость доставки.
     * Возвращает float (0 = бесплатно) или null (уточняется / по тарифам).
     */
    public static function calcDelivery(string $deliveryType, float $subtotal): ?float
    {
        $methods = config('shop.delivery_methods', []);
        $method  = $methods[$deliveryType] ?? null;

        if (!$method) {
            return null; // неизвестный тип — уточняется
        }

        $price    = $method['price'];    // float|null
        $freeFrom = $method['free_from']; // float|null

        // null price = рассчитывается отдельно (КИТ и т.п.)
        if ($price === null) {
            return null;
        }

        // Самовывоз всегда бесплатно
        if ($price === 0) {
            return 0.0;
        }

        // Проверяем порог бесплатной доставки
        if ($freeFrom !== null && $subtotal >= (float) $freeFrom) {
            return 0.0;
        }

        return (float) $price;
    }

    private function calcSubtotal(array $items): float
    {
        return round(array_sum(array_map(
            fn($i) => $i['price'] * $i['quantity'],
            $items
        )), 2);
    }
}
