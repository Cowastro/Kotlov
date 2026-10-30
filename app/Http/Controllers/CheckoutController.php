<?php

namespace App\Http\Controllers;

use App\Events\NewOrderCreated;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\B2bCheckoutTerms;
use App\Services\B2bSupplyOptionResolver;
use App\Services\CartAvailabilityGuard;
use App\Services\CartCouponService;
use App\Services\CommercialDocuments\CheckoutInvoiceService;
use App\Services\Crm\BelarusTaxpayerRegistry;
use App\Services\Orders\OrderEconomicSnapshotRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    private const CART_KEY = 'cart';

    // ─────────────────────────────────────────
    // Страница оформления заказа  GET /checkout
    // ─────────────────────────────────────────
    public function index(
        Request $request,
        CartAvailabilityGuard $availabilityGuard,
        CartCouponService $couponService,
        B2bCheckoutTerms $b2bTerms,
    ) {
        $cart = session(self::CART_KEY, []);

        $availability = $availabilityGuard->reconcileRetailItems($cart);
        $cart = $availability['cart'];
        if ($availability['removed_ids'] !== []) {
            session([self::CART_KEY => $cart]);

            return redirect()->route('cart')->with(
                'error',
                'Недоступные или снятые с продажи товары удалены из корзины.'
            );
        }
        if ($availability['price_changed_ids'] !== []) {
            session([self::CART_KEY => $cart]);

            return redirect()->route('cart')->with(
                'info',
                'Цены в корзине обновились. Проверьте итоговую сумму перед оформлением.'
            );
        }

        if (empty($cart)) {
            return redirect()->route('cart')->with('info', 'Корзина пуста.');
        }

        $subtotal = $this->calcSubtotal($cart);
        $user = Auth::user();
        $b2bCheckout = $b2bTerms->evaluate($user, $cart);
        if (! $b2bCheckout['can_checkout']) {
            return redirect()->route('cart')->with('error', $b2bCheckout['message']);
        }
        $checkoutPaymentMethods = $b2bCheckout['payment_methods'];
        $invoiceProfile = $b2bCheckout['invoice_profile'];
        $requestedPayment = (string) $request->query('payment_type', '');
        $selectedPayment = in_array($requestedPayment, $checkoutPaymentMethods, true)
            ? $requestedPayment
            : null;
        $couponCode = session('cart_coupon');
        if ($couponService->isOnlineOrderDiscountCode($couponCode)
            && $couponService->onlineOrderDiscountSubtotal($cart) <= 0) {
            session()->forget('cart_coupon');
            $couponCode = null;
        }
        $coupon = $couponService->quoteForCart($couponCode, $cart);
        if (session('cart_coupon') && ! $coupon['valid']) {
            session()->forget('cart_coupon');
        }
        $onlineOrderDiscountCoupon = $coupon['valid']
            && $couponService->isOnlineOrderDiscountCode($coupon['code']);
        $onlineOrderDiscountExcludedByPayment = $onlineOrderDiscountCoupon
            && ! $couponService->onlineOrderDiscountAppliesToPayment($selectedPayment);
        // A returning customer finds the contacts and address of the last order already filled in.
        $lastOrder = $user ? Order::query()->where('user_id', $user->id)->latest('id')
            ->first(['customer_name', 'customer_phone', 'delivery_type', 'delivery_city', 'delivery_address']) : null;
        $deliveryCities = self::deliveryCities();

        return view('pages.checkout', compact(
            'cart',
            'subtotal',
            'coupon',
            'user',
            'lastOrder',
            'deliveryCities',
            'selectedPayment',
            'onlineOrderDiscountCoupon',
            'onlineOrderDiscountExcludedByPayment',
            'b2bCheckout',
            'checkoutPaymentMethods',
            'invoiceProfile',
        ));
    }

    // ─────────────────────────────────────────
    // Создать заказ  POST /checkout
    // ─────────────────────────────────────────
    public function store(
        Request $request,
        B2bSupplyOptionResolver $supplyOptions,
        B2bCheckoutTerms $b2bTerms,
        CartAvailabilityGuard $availabilityGuard,
        CartCouponService $couponService,
        BelarusTaxpayerRegistry $taxpayerRegistry,
        CheckoutInvoiceService $checkoutInvoices,
    ) {
        $cart = session(self::CART_KEY, []);

        if (empty($cart)) {
            return redirect()->route('cart')->with('info', 'Корзина пуста.');
        }

        $availability = $availabilityGuard->reconcileRetailItems($cart);
        $cart = $availability['cart'];
        if ($availability['removed_ids'] !== []) {
            session([self::CART_KEY => $cart]);

            return redirect()->route('cart')->with(
                'error',
                'Заказ не создан: недоступные или снятые с продажи товары удалены из корзины.'
            );
        }
        if ($availability['price_changed_ids'] !== []) {
            session([self::CART_KEY => $cart]);

            return redirect()->route('cart')->with(
                'error',
                'Заказ не создан: цены изменились и обновлены в корзине. Проверьте сумму и подтвердите заказ повторно.'
            );
        }

        $containsB2bPrices = collect($cart)->contains(
            fn (array $item): bool => ($item['pricing_type'] ?? 'retail') === 'b2b'
        );
        if ($containsB2bPrices && ! Auth::user()?->isB2B()) {
            return redirect()->route('cart')->with(
                'error',
                'Партнёрский доступ изменился. Очистите корзину и добавьте товары повторно.'
            );
        }

        if ($containsB2bPrices) {
            $products = Product::query()
                ->whereIn('id', array_keys($cart))
                ->get()
                ->keyBy('id');

            $b2bPriceChanged = false;
            foreach ($cart as $key => $item) {
                if (($item['pricing_type'] ?? 'retail') !== 'b2b') {
                    continue;
                }

                $product = $products->get((int) $item['id']);
                $option = $product ? $supplyOptions->optionForKey(
                    $product,
                    Auth::user(),
                    (string) ($item['supply_option'] ?? ''),
                ) : null;
                if (! $option || ! $supplyOptions->canOrder($option, (int) $item['quantity'])) {
                    return redirect()->route('cart')->with(
                        'error',
                        'B2B-заказ не создан: одна из позиций потеряла привязку или больше недоступна в указанном количестве.'
                    );
                }

                $currentPrice = $option['price'];
                if (abs((float) $item['price'] - $currentPrice) >= 0.01) {
                    $cart[$key]['price'] = $currentPrice;
                    $b2bPriceChanged = true;
                }

                $cart[$key]['integration_product_id'] = $option['integration_product_id'];
                $cart[$key]['supplier_product_id'] = $option['supplier_product_id'];
                $cart[$key]['warehouse_stock_id'] = $option['warehouse_stock_id'];
                $cart[$key]['source_name'] = $option['source_label'];
                $cart[$key]['warehouse_name'] = $option['warehouse_name'];
                $cart[$key]['warehouse_delivery_days'] = $option['delivery_days'];
                $cart[$key]['b2b_base_price'] = $option['base_price'];
                $cart[$key]['b2b_price_level_id'] = $option['price_level_id'];
                $cart[$key]['b2b_price_level_code'] = $option['price_level_code'];
                $cart[$key]['b2b_price_level_name'] = $option['price_level_name'];
                $cart[$key]['b2b_discount_percent'] = $option['discount_percent'];
            }

            if ($b2bPriceChanged) {
                session([self::CART_KEY => $cart]);

                return redirect()->route('cart')->with(
                    'error',
                    'B2B-заказ не создан: цены 1С изменились и обновлены в корзине. Проверьте сумму повторно.'
                );
            }
        }

        // Убираем из заказа товары, снятые с продажи (могли попасть в корзину до архивации)
        $archivedIds = Product::whereIn('id', array_keys($cart))
            ->where('is_archived', true)
            ->pluck('id')
            ->all();

        if (! empty($archivedIds)) {
            foreach ($archivedIds as $aid) {
                unset($cart[$aid]);
            }
            session([self::CART_KEY => $cart]);

            if (empty($cart)) {
                return redirect()->route('cart')->with('error', 'Товары в корзине сняты с продажи и недоступны для заказа.');
            }

            return redirect()->route('cart')->with('error', 'Некоторые товары сняты с продажи и были удалены из корзины. Проверьте заказ.');
        }

        $b2bCheckout = $b2bTerms->evaluate(Auth::user(), $cart);
        if (! $b2bCheckout['can_checkout']) {
            return redirect()->route('cart')->with('error', $b2bCheckout['message']);
        }
        $checkoutPaymentMethods = $b2bCheckout['payment_methods'];

        // Защита от двойного сабмита — один токен = один заказ
        $token = $request->input('order_token');
        if (! $token || session('order_token_used_'.$token)) {
            return redirect()->route('checkout')->with('info', 'Заказ уже был отправлен. Проверьте список заказов.');
        }
        session(['order_token_used_'.$token => true]);

        $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[\d\s\+\(\)\-]{7,}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'delivery_type' => ['required', 'in:'.implode(',', array_keys(config('shop.delivery_methods', [])))],
            // The region is no longer asked: it follows from the city (self::regionOf).
            'delivery_region' => ['nullable', 'string', 'max:255'],
            'delivery_city' => ['required_unless:delivery_type,pickup', 'nullable', 'string', 'max:255'],
            'delivery_address' => ['required_unless:delivery_type,pickup', 'nullable', 'string', 'max:500'],
            'payment_type' => ['required', 'in:'.implode(',', $checkoutPaymentMethods)],
            'comment' => ['nullable', 'string', 'max:1000'],
            // Реквизиты для счёта
            'company_name' => ['required_if:payment_type,invoice,invoice_deferred', 'nullable', 'string', 'max:255'],
            'company_unp' => ['required_if:payment_type,invoice,invoice_deferred', 'nullable', 'regex:/^\d{9}$/'],
            'company_address' => ['required_if:payment_type,invoice,invoice_deferred', 'nullable', 'string', 'max:500'],
            'company_email' => ['required_if:payment_type,invoice,invoice_deferred', 'nullable', 'email', 'max:255'],
        ], [
            'customer_name.required' => 'Введите имя.',
            'customer_phone.required' => 'Введите телефон.',
            'customer_phone.regex' => 'Введите корректный номер телефона.',
            'delivery_type.required' => 'Выберите способ доставки.',
            'payment_type.required' => 'Выберите способ оплаты.',
            'payment_type.in' => 'Выбранный способ оплаты сейчас недоступен.',
            'delivery_city.required_unless' => 'Укажите город доставки.',
            'delivery_address.required_unless' => 'Укажите адрес доставки.',
            'company_name.required_if' => 'Введите название организации для оплаты по счёту.',
            'company_unp.required_if' => 'Введите УНП организации.',
            'company_unp.regex' => 'УНП должен состоять из 9 цифр.',
            'company_address.required_if' => 'Укажите юридический адрес организации.',
            'company_email.required_if' => 'Укажите email, на который менеджер отправит счёт.',
        ]);

        $invoiceCompany = null;
        $isInvoicePayment = in_array($request->payment_type, ['invoice', 'invoice_deferred'], true);
        if ($isInvoicePayment) {
            $registryCompany = $taxpayerRegistry->lookup((string) $request->company_unp);
            $invoiceCompany = [
                'unp' => preg_replace('/\D+/', '', (string) $request->company_unp),
                'legal_name' => $registryCompany['legal_name'] ?? $request->company_name,
                'short_name' => $registryCompany['short_name'] ?? $request->company_name,
                'address' => $registryCompany['address'] ?? $request->company_address,
                'registered_at' => $registryCompany['registered_at'] ?? null,
                'email' => $request->company_email,
                'phone' => $request->customer_phone,
            ];
        }

        $subtotal = $this->calcSubtotal($cart);
        $couponCode = session('cart_coupon');
        if ($couponService->isOnlineOrderDiscountCode($couponCode)
            && $couponService->onlineOrderDiscountSubtotal($cart) <= 0) {
            session()->forget('cart_coupon');
            $couponCode = null;
        }
        $coupon = $couponService->quoteForCart($couponCode, $cart);
        if (session('cart_coupon') && ! $coupon['valid']) {
            session()->forget('cart_coupon');

            return redirect()->route('cart')->with(
                'error',
                'Промокод больше не действует. Проверьте сумму заказа и оформите его повторно.'
            );
        }
        if ($couponService->isOnlineOrderDiscountCode($coupon['code'])
            && ! $couponService->onlineOrderDiscountAppliesToPayment($request->payment_type)) {
            $coupon = $couponService->quoteForCart(null, $cart);
        }
        $deliveryPrice = self::calcDelivery($request->delivery_type, $subtotal) ?? 0;

        $order = DB::transaction(function () use ($request, $cart, $subtotal, $deliveryPrice, $coupon, $invoiceCompany, $checkoutInvoices, $couponService, $b2bCheckout, $b2bTerms, $isInvoicePayment) {

            $lockedCoupon = null;
            if ($coupon['code']) {
                $lockedCoupon = Coupon::query()
                    ->where('code', $coupon['code'])
                    ->lockForUpdate()
                    ->first();

                if (! $lockedCoupon || ! $lockedCoupon->isValid($subtotal)) {
                    return null;
                }
            }

            $finalQuote = $couponService->quoteForCart($lockedCoupon?->code, $cart);
            $discount = $finalQuote['discount'];
            $total = round($subtotal - $discount + $deliveryPrice, 2);
            $termsSnapshot = $b2bCheckout['terms_snapshot'];

            if ($request->payment_type === 'invoice_deferred') {
                $credit = $b2bTerms->assertDeferredPaymentAvailable($request->user(), $total);
                $termsSnapshot = array_merge($termsSnapshot ?? [], [
                    'credit_exposure_before' => $credit['exposure'],
                    'credit_available_before' => $credit['available'],
                    'credit_available_after' => round((float) $credit['available'] - $total, 2),
                ]);
            }

            $order = Order::create([
                'user_id' => Auth::id(),
                'number' => Order::generateNumber(),
                'status' => 'new',

                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email,

                'delivery_type' => $request->delivery_type,
                'delivery_region' => $request->delivery_type === 'pickup' ? null
                    : ($request->delivery_region ?: self::regionOf((string) $request->delivery_city)),
                'delivery_city' => $request->delivery_city,
                'delivery_address' => $request->delivery_address,
                'delivery_price' => $deliveryPrice,

                'payment_type' => $request->payment_type,
                'payment_status' => 'pending',

                // Реквизиты для счёта: предоплата или согласованная отсрочка.
                'company_name' => $isInvoicePayment ? $request->company_name : null,
                'company_unp' => $isInvoicePayment ? $request->company_unp : null,
                'company_address' => $isInvoicePayment ? $request->company_address : null,
                'company_email' => $isInvoicePayment ? $request->company_email : null,

                'subtotal' => $subtotal,
                'coupon_code' => $coupon['code'],
                'discount' => $discount,
                'promotion_breakdown' => [
                    'online_order_discount' => $finalQuote['coupon_discount'],
                    'chimney_bundle_discount' => $finalQuote['bundle_discount'],
                    'chimney_bundle_percent' => $finalQuote['bundle']['percent'],
                    'chimney_bundle_mode' => $finalQuote['bundle']['mode'],
                    'chimney_self_selected_product_ids' => $finalQuote['bundle']['self_selected_product_ids'],
                    'chimney_product_ids' => $finalQuote['bundle']['chimney_product_ids'],
                    'equipment_product_ids' => $finalQuote['bundle']['equipment_product_ids'],
                ],
                'b2b_commercial_terms_snapshot' => $termsSnapshot,
                'total' => $total,

                // comment: что пользователь написал в форме; если поле пустое — null
                'comment' => $request->filled('comment') ? $request->comment : null,
            ]);

            foreach ($cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['sku'] ?? null,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => round($item['price'] * $item['quantity'], 2),
                    'pricing_type' => $item['pricing_type'] ?? 'retail',
                    'price_tax_mode' => $item['price_tax_mode'] ?? null,
                    'integration_product_id' => $item['integration_product_id'] ?? null,
                    'supplier_product_id' => $item['supplier_product_id'] ?? null,
                    'warehouse_stock_id' => $item['warehouse_stock_id'] ?? null,
                    'supply_warehouse_name' => $item['warehouse_name'] ?? null,
                    'supply_delivery_days' => $item['warehouse_delivery_days'] ?? null,
                    'b2b_base_price' => $item['b2b_base_price'] ?? null,
                    'b2b_price_level_id' => $item['b2b_price_level_id'] ?? null,
                    'b2b_price_level_code' => $item['b2b_price_level_code'] ?? null,
                    'b2b_price_level_name' => $item['b2b_price_level_name'] ?? null,
                    'b2b_discount_percent' => $item['b2b_discount_percent'] ?? null,
                    'b2b_priced_at' => ($item['pricing_type'] ?? 'retail') === 'b2b' ? now() : null,
                ]);
            }

            app(OrderEconomicSnapshotRecorder::class)->capturePlaced($order->load('items'));

            if ($invoiceCompany) {
                $checkoutInvoices->createDraft($order, $invoiceCompany);
            }

            if ($lockedCoupon) {
                $lockedCoupon->increment('uses_count');
            }

            return $order;
        });

        if (! $order) {
            session()->forget('cart_coupon');

            return redirect()->route('cart')->with(
                'error',
                'Промокод перестал действовать во время оформления. Итог заказа не изменён.'
            );
        }

        NewOrderCreated::dispatch($order->load('items'));

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
            ->with('order_number', $order->number)
            ->with('analytics_event', 'order_success');
    }

    // ─────────────────────────────────────────
    // Страница успешного заказа  GET /checkout/success/{number}
    // ─────────────────────────────────────────
    public function success(string $number)
    {
        $order = Order::where('number', $number)
            ->with(['items', 'user'])
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
    /** Region of a Belarusian city for the order card; null for a place the list does not know. */
    public static function regionOf(string $city): ?string
    {
        $key = mb_strtolower(trim(preg_replace('/^(г\.|город|аг\.|д\.|п\.)\s*/u', '', trim($city)) ?? $city));
        foreach (self::REGION_CITIES as $region => $cities) {
            if (in_array($key, array_map('mb_strtolower', $cities), true)) {
                return $region;
            }
        }

        return null;
    }

    /** Cities offered as hints in the checkout: Minsk, regional centres and the own transport routes. */
    public static function deliveryCities(): array
    {
        return collect(self::REGION_CITIES)->flatten()->unique()->values()->all();
    }

    private const REGION_CITIES = [
        'Минск' => ['Минск'],
        'Минская область' => ['Борисов', 'Солигорск', 'Молодечно', 'Жодино', 'Слуцк', 'Дзержинск', 'Смолевичи', 'Заславль', 'Фаниполь', 'Столбцы',
            'Несвиж', 'Вилейка', 'Логойск', 'Марьина Горка', 'Червень', 'Березино', 'Крупки', 'Узда', 'Копыль', 'Клецк', 'Любань', 'Старые Дороги',
            'Мядель', 'Воложин', 'Бегомль', 'Руденск', 'Мачулищи', 'Колодищи', 'Лесной', 'Ждановичи'],
        'Брестская область' => ['Брест', 'Барановичи', 'Пинск', 'Кобрин', 'Береза', 'Ивацевичи', 'Лунинец', 'Пружаны', 'Дрогичин', 'Ляховичи', 'Ганцевичи', 'Столин', 'Жабинка', 'Иваново', 'Каменец', 'Малорита'],
        'Гродненская область' => ['Гродно', 'Лида', 'Слоним', 'Волковыск', 'Новогрудок', 'Сморгонь', 'Ошмяны', 'Островец', 'Вороново', 'Зельва', 'Мосты', 'Щучин', 'Ивье', 'Дятлово', 'Кореличи', 'Свислочь', 'Берестовица', 'Скидель'],
        'Витебская область' => ['Витебск', 'Орша', 'Новополоцк', 'Полоцк', 'Глубокое', 'Лепель', 'Докшицы', 'Ушачи', 'Чашники', 'Новолукомль', 'Сенно', 'Поставы', 'Браслав', 'Толочин', 'Городок', 'Миоры', 'Дубровно'],
        'Могилёвская область' => ['Могилёв', 'Могилев', 'Бобруйск', 'Горки', 'Осиповичи', 'Кричев', 'Быхов', 'Шклов', 'Климовичи', 'Костюковичи', 'Белыничи', 'Кировск', 'Чаусы'],
        'Гомельская область' => ['Гомель', 'Мозырь', 'Жлобин', 'Светлогорск', 'Речица', 'Калинковичи', 'Рогачёв', 'Рогачев', 'Добруш', 'Житковичи', 'Хойники', 'Петриков', 'Ельск', 'Наровля', 'Буда-Кошелёво'],
    ];

    public static function calcDelivery(string $deliveryType, float $subtotal): ?float
    {
        $methods = config('shop.delivery_methods', []);
        $method = $methods[$deliveryType] ?? null;

        if (! $method) {
            return null; // неизвестный тип — уточняется
        }

        $price = $method['price'] ?? null;    // float|null
        $freeFrom = $method['free_from'] ?? null; // float|null

        // Ступенчатый тариф: берём первую подходящую ступень, начиная с
        // наибольшей суммы заказа. Например: менее 500 = 30, 500–999 = 20,
        // от 1000 = бесплатно.
        if (! empty($method['price_tiers']) && is_array($method['price_tiers'])) {
            $tiers = collect($method['price_tiers'])
                ->sortByDesc(fn (array $tier) => (float) ($tier['from'] ?? 0));

            foreach ($tiers as $tier) {
                if ($subtotal >= (float) ($tier['from'] ?? 0)) {
                    return isset($tier['price']) ? (float) $tier['price'] : null;
                }
            }
        }

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
            fn ($i) => $i['price'] * $i['quantity'],
            $items
        )), 2);
    }
}
