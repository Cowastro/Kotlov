<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Services\B2bCatalogOfferResolver;
use App\Services\CartAvailabilityGuard;
use App\Services\CartCouponService;
use App\Services\ChimneyBundlePromotion;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // ключ в сессии
    private const KEY = 'cart';

    public function __construct(
        private readonly CartCouponService $couponService,
        private readonly ChimneyBundlePromotion $chimneyBundlePromotion,
    ) {}

    // ─────────────────────────────────────────
    // Страница корзины
    // ─────────────────────────────────────────
    public function index(CartAvailabilityGuard $availabilityGuard)
    {
        $items = $this->getAvailableItems($availabilityGuard);
        $subtotal = $this->calcSubtotal($items);
        $coupon = $this->couponQuote($subtotal);

        return view('pages.cart', compact('items', 'subtotal', 'coupon'));
    }

    // ─────────────────────────────────────────
    // Добавить товар  POST /cart/add
    // ─────────────────────────────────────────
    public function add(Request $request, B2bCatalogOfferResolver $offerResolver)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'warehouse_stock_id' => ['sometimes', 'nullable', 'integer', 'exists:product_warehouse_stocks,id'],
            'chimney_selector_token' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $product = Product::query()
            ->withSupplierChannels()
            ->with(['category:id,slug', 'brand:id,slug,name', 'warehouseStocks'])
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

        if (! $product->hasAssignedSupplier()) {
            $message = 'Товар нельзя добавить в корзину: поставщик пока не назначен. Оставьте запрос — менеджер уточнит наличие и возможность поставки.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $canBeOrdered = $b2bOffer
            ? $product->is_active && ! $product->is_archived && $offerResolver->canOrder($b2bOffer)
            : $product->canBeAddedToCart();

        if (! $canBeOrdered) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Товар сейчас недоступен для оформления заказа.'], 422);
            }

            return back()->with('error', 'Товар сейчас недоступен для оформления заказа.');
        }

        $qty = max(1, (int) $request->input('quantity', 1));
        $sessionSelectorToken = session('chimney_selector_token');
        $requestSelectorToken = $request->input('chimney_selector_token');
        $selectedInChimneyCalculator = is_string($sessionSelectorToken)
            && is_string($requestSelectorToken)
            && hash_equals($sessionSelectorToken, $requestSelectorToken)
            && $this->chimneyBundlePromotion->productIsChimney($product);

        $warehouseStock = null;
        if ($b2bOffer) {
            $requestedWarehouseStockId = (int) $request->input('warehouse_stock_id', 0);
            if ($requestedWarehouseStockId > 0) {
                $warehouseStock = $product->warehouseStocks->firstWhere('id', $requestedWarehouseStockId);
                if (! $warehouseStock || (float) $warehouseStock->available_quantity < $qty) {
                    $message = 'На выбранном складе недостаточно товара. Выберите другой склад или уменьшите количество.';

                    return $request->expectsJson()
                        ? response()->json(['message' => $message], 422)
                        : back()->with('error', $message);
                }
            } elseif (! $offerResolver->hasFreshStock($b2bOffer)) {
                $warehouseStock = $product->warehouseStocks
                    ->filter(fn ($stock): bool => (float) $stock->available_quantity >= $qty)
                    ->sortBy('delivery_days')
                    ->first();
            }
        }

        if ($b2bOffer && ! $warehouseStock && ! $offerResolver->canOrder($b2bOffer, $qty)) {
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
            'warehouse_stock_id' => $warehouseStock?->id,
            'warehouse_name' => $warehouseStock?->warehouse_name
                ?: $this->b2bWarehouseLabel($product, $b2bOffer),
            'warehouse_delivery_days' => $warehouseStock?->delivery_days,
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
            $cart[$id]['chimney_selector_selected'] = ($cart[$id]['chimney_selector_selected'] ?? false)
                || $selectedInChimneyCalculator;
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
                'chimney_selector_selected' => $selectedInChimneyCalculator,
            ] + $pricing;
        }

        session([self::KEY => $cart]);

        if ($request->expectsJson()) {
            $subtotal = $this->calcSubtotal($cart);
            $coupon = $this->couponQuote($subtotal);

            return response()->json([
                'message' => 'added',
                'count' => $this->totalQty($cart),
                'subtotal' => $subtotal,
                'discount' => $coupon['discount'],
                'coupon_discount' => $coupon['coupon_discount'],
                'bundle_discount' => $coupon['bundle_discount'],
                'bundle_percent' => $coupon['bundle']['percent'],
                'bundle_mode' => $coupon['bundle']['mode'],
                'total' => $coupon['total'],
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

                if ($warehouseStockId = (int) ($cart[$id]['warehouse_stock_id'] ?? 0)) {
                    $warehouseStock = ProductWarehouseStock::query()->find($warehouseStockId);
                    if (! $warehouseStock
                        || (int) $warehouseStock->product_id !== $id
                        || (float) $warehouseStock->available_quantity < $quantity) {
                        $message = 'На выбранном складе больше нет нужного количества. Выберите склад заново.';

                        return $request->expectsJson()
                            ? response()->json(['message' => $message], 422)
                            : back()->with('error', $message);
                    }
                }

                $cart[$id]['price'] = $offerResolver->priceWithTax($offer);
            }

            $cart[$id]['quantity'] = (int) $request->quantity;
            session([self::KEY => $cart]);
        }

        if ($request->expectsJson()) {
            $subtotal = $this->calcSubtotal($cart);
            $coupon = $this->couponQuote($subtotal);

            return response()->json([
                'count' => $this->totalQty($cart),
                'subtotal' => $subtotal,
                'discount' => $coupon['discount'],
                'coupon_discount' => $coupon['coupon_discount'],
                'bundle_discount' => $coupon['bundle_discount'],
                'bundle_percent' => $coupon['bundle']['percent'],
                'bundle_mode' => $coupon['bundle']['mode'],
                'total' => $coupon['total'],
                'item_total' => isset($cart[$id])
                    ? round($cart[$id]['price'] * $cart[$id]['quantity'], 2)
                    : 0,
            ]);
        }

        return back();
    }

    private function b2bWarehouseLabel(Product $product, $offer): string
    {
        $label = trim((string) data_get($offer?->source?->settings, 'warehouse_label'));
        if (($product->brand?->slug === 'ecokamin') && in_array(mb_strtolower($label), ['', 'основной', 'основной склад'], true)) {
            return 'Склад Минск';
        }

        return $label !== '' ? $label : 'Основной склад';
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
            $subtotal = $this->calcSubtotal($cart);
            $coupon = $this->couponQuote($subtotal);

            return response()->json([
                'message' => 'removed',
                'count' => $this->totalQty($cart),
                'subtotal' => $subtotal,
                'discount' => $coupon['discount'],
                'coupon_discount' => $coupon['coupon_discount'],
                'bundle_discount' => $coupon['bundle_discount'],
                'bundle_percent' => $coupon['bundle']['percent'],
                'bundle_mode' => $coupon['bundle']['mode'],
                'total' => $coupon['total'],
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
        $coupon = $this->couponQuote($subtotal);
        $threshold = (float) config('shop.free_delivery_threshold', 400);
        $remaining = max(0, $threshold - $subtotal);
        $progress = $threshold > 0 ? min(100, round($subtotal / $threshold * 100)) : 100;

        return response()->json([
            'items' => array_values($items),
            'count' => $this->totalQty($items),
            'subtotal' => $subtotal,
            'discount' => $coupon['discount'],
            'coupon_discount' => $coupon['coupon_discount'],
            'bundle_discount' => $coupon['bundle_discount'],
            'bundle_percent' => $coupon['bundle']['percent'],
            'bundle_mode' => $coupon['bundle']['mode'],
            'total' => $coupon['total'],
            'threshold' => $threshold,
            'remaining' => $remaining,
            'progress' => $progress,
            'note' => session('cart_note', ''),
            'coupon' => $coupon['code'] ?? '',
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
        $code = $this->couponService->normalize($request->input('coupon'));

        if ($code === null) {
            session()->forget('cart_coupon');

            return $request->expectsJson()
                ? response()->json(['message' => 'Промокод удалён.', 'coupon' => null])
                : back()->with('success', 'Промокод удалён.');
        }

        $items = $this->getItems();
        $coupon = $this->couponService->quoteForCart($code, $items);

        if (! $coupon['valid']) {
            session()->forget('cart_coupon');
            $message = 'Промокод не найден, недействителен или не подходит к сумме заказа.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        session(['cart_coupon' => $coupon['code']]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Промокод применён. Скидка '.number_format($coupon['discount'], 2, '.', ' ').' BYN.',
                'coupon' => $coupon['code'],
                'discount' => $coupon['discount'],
                'coupon_discount' => $coupon['coupon_discount'],
                'bundle_discount' => $coupon['bundle_discount'],
                'bundle_percent' => $coupon['bundle']['percent'],
                'bundle_mode' => $coupon['bundle']['mode'],
                'total' => $coupon['total'],
            ]);
        }

        return back()->with('success', 'Промокод применён.');
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

    /** @return array{code: ?string, coupon: ?Coupon, discount: float, total: float, valid: bool} */
    private function couponQuote(float $subtotal): array
    {
        $code = session('cart_coupon');
        $items = $this->getItems();
        $eligibleOnlineSubtotal = $this->couponService->onlineOrderDiscountSubtotal($items);

        if ($this->couponService->isOnlineOrderDiscountCode($code) && $eligibleOnlineSubtotal <= 0) {
            session()->forget('cart_coupon');
            $code = null;
        }

        // Покупателю не нужно запоминать промокод: для обычной розничной
        // корзины онлайн-скидка подключается при первом расчёте автоматически.
        // Партнёрская B2B-цена уже является специальной и не суммируется с ней.
        if (! $code && $subtotal > 0 && $eligibleOnlineSubtotal > 0 && $this->isRetailCart()) {
            $code = (string) config('shop.online_order_discount.code', 'ONLINE3');
        }

        $quote = $this->couponService->quoteForCart($code, $items);

        if ($code && ! $quote['valid']) {
            session()->forget('cart_coupon');
        } elseif ($quote['valid'] && session('cart_coupon') !== $quote['code']) {
            session(['cart_coupon' => $quote['code']]);
        }

        return $quote;
    }

    private function isRetailCart(): bool
    {
        $items = $this->getItems();

        return $items !== [] && collect($items)->every(
            fn (array $item): bool => ($item['pricing_type'] ?? 'retail') !== 'b2b'
        );
    }
}
