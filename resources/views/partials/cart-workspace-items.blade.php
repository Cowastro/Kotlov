@unless($b2bClient)<details class="cart-retail-selection"><summary>Выбрать часть товаров или найти в корзине</summary>@endunless
<div class="cart-list-tools">
    <label class="cart-check-all"><input type="checkbox" id="cart-select-all" @checked($cartSelection['positions'] === count($items))> <span id="cart-select-label">Выбрать всё</span></label>
    <span class="cart-selection-count">Выбрано <strong data-selected-positions>{{ $cartSelection['positions'] }}</strong> из {{ count($items) }}</span>
    <div class="cart-find"><label for="cart-find" class="visually-hidden">Найти в корзине</label><input type="search" id="cart-find" placeholder="Найти товар или артикул" autocomplete="off" enterkeyhint="search" aria-describedby="cart-search-result"><button type="button" id="cart-search-clear" aria-label="Сбросить поиск в корзине" hidden>×</button></div>
    <span class="cart-search-result" id="cart-search-result" role="status" aria-live="polite" hidden></span>
</div>
@unless($b2bClient)</details>@endunless
<p class="cart-feedback" id="cart-save-state" role="status" aria-live="polite">{{ auth()->check() ? 'Все изменения сохраняются в аккаунте' : 'Изменения сохраняются в этой корзине' }}</p>
<p class="cart-error" id="cart-error" role="alert" hidden></p>
<p class="cart-search-empty" hidden>Совпадений нет. Измените запрос — товары остаются в корзине.</p>
@if($b2bClient)<div class="b2b-cart-table-head" aria-hidden="true"><span></span><span></span><span>Товар / артикул / склад</span><span>Цена сайта</span><span>Ваша цена</span><span>Количество</span><span>Сумма, BYN</span><span></span></div>@endif
<div class="cart-lines">
@foreach ($items as $item)
    @php
        $productUrl = '/'.$item['category_slug'].'/'.$item['slug'];
        $chosen = ! in_array($item['id'], session(\App\Services\CartSelection::KEY, []));
    @endphp
    <article class="cart-line {{ $chosen ? '' : 'is-postponed' }}" data-cart-row data-product-id="{{ $item['id'] }}" data-price="{{ $item['price'] }}">
        <label class="cart-line__check"><input type="checkbox" class="cart-line-check" @checked($chosen) aria-label="Выбрать {{ $item['name'] }}"></label>
        <a class="cart-line__image" href="{{ $productUrl }}"><img src="{{ $item['image'] ?? asset('img/products/product-placeholder.jpg') }}" width="72" height="72" loading="lazy" alt="{{ $item['name'] }}"></a>
        <div class="cart-line__info">
            <small class="cart-line__sku">{{ $item['sku'] ?? '' }}</small>
            <h2><a href="{{ $productUrl }}">{{ $item['name'] }}</a></h2>
            @if (! empty($cartHighlights[$item['id']]))<p class="cart-line__specs">{{ $cartHighlights[$item['id']] }}</p>@endif
            <p class="cart-line__availability {{ ($item['requires_availability_confirmation'] ?? false) ? 'needs-confirmation' : '' }}">{{ ($item['requires_availability_confirmation'] ?? false) ? 'Наличие уточнит менеджер' : 'Доступен к заказу' }}</p>
            @if($b2bClient && !empty($item['order_rules']))<p class="cart-line__specs">{{ \App\Services\Suppliers\SupplierOrderRules::label($item['order_rules']) }}</p>@endif
            @if (($item['pricing_type'] ?? null) === 'b2b')<small class="cart-line__warehouse">{{ $item['b2b_price_level_name'] ?? 'Партнёрская цена' }}@if(!empty($item['source_name'])) · {{ $item['source_name'] }}@endif @if (! empty($item['warehouse_name'])) · {{ $item['warehouse_name'] }}@endif</small>@endif
        </div>
        <details class="cart-line__menu"><summary aria-label="Действия с {{ $item['name'] }}">⋯</summary><div>
            @if($b2bClient)<button type="button" data-edit-line-note>Комментарий к позиции</button>
            <button type="button" data-move-line>В другой подбор</button>@endif
            <form action="{{ route('cart.remove') }}" method="post">@csrf<input type="hidden" name="product_id" value="{{ $item['id'] }}"><button type="submit" class="cart-line__remove">Удалить</button></form>
        </div></details>
        @if($b2bClient)<div class="cart-line__retail">{{ ($cartRetailPrices[$item['id']] ?? 0) > 0 ? number_format((float)$cartRetailPrices[$item['id']], 2, ',', ' ') : '—' }}<small>BYN · цена сайта</small></div>@endif
        <div class="cart-line__price"><span data-unit-price>{{ number_format($item['price'], 2, ',', ' ') }}</span> × <span data-line-quantity>{{ $item['quantity'] }}</span><small>BYN за штуку</small>@if($b2bClient)<small data-b2b-discount data-retail="{{ $cartRetailPrices[$item['id']] ?? 0 }}"></small>@endif</div>
        <div class="cart-line__quantity"><button type="button" data-qty-step="-1" aria-label="Уменьшить количество {{ $item['name'] }}">−</button><input class="cart-qty-input" type="number" inputmode="numeric" min="1" max="999" step="1" value="{{ $item['quantity'] }}" data-saved="{{ $item['quantity'] }}" aria-label="Количество {{ $item['name'] }}"><button type="button" data-qty-step="1" aria-label="Увеличить количество {{ $item['name'] }}">+</button></div>
        <div class="cart-line__total"><strong data-line-total>{{ number_format(round($item['price'] * $item['quantity'], 2), 2, ',', ' ') }} BYN</strong><small>Сумма позиции</small></div>
        <div class="cart-line__note" @if (empty($item['selection_note'])) hidden @endif><label for="line-note-{{ $item['id'] }}">Комментарий к позиции в подборе</label><textarea id="line-note-{{ $item['id'] }}" class="cart-line-note" rows="2" maxlength="500" data-saved="{{ $item['selection_note'] ?? '' }}" placeholder="Например: для первого этажа">{{ $item['selection_note'] ?? '' }}</textarea></div>
    </article>
@endforeach
</div>
<p class="cart-selection-hint">В заказ попадут только отмеченные товары. Остальные останутся в корзине.</p>
@auth
<dialog id="cart-move-dialog" class="cart-dialog" aria-labelledby="cart-move-heading"><form method="post" action="{{ route('selections.move-line') }}">
    @csrf<input type="hidden" name="product_id" id="cart-move-id">
    <div class="cart-dialog__head"><h2 id="cart-move-heading">В другой подбор</h2><button type="button" data-close-cart-dialog aria-label="Закрыть">×</button></div>
    <p id="cart-move-name"></p>
    <label for="cart-move-target">Куда перенести</label><select id="cart-move-target" name="target_selection_id"><option value="">Создать новый подбор</option>@foreach ($cartSelections as $saved)<option value="{{ $saved->id }}">{{ $saved->name }}</option>@endforeach</select>
    <div id="cart-move-new"><label for="cart-move-title">Название нового подбора</label><input id="cart-move-title" name="name" maxlength="160" required placeholder="Клиент или объект"></div>
    <p class="text-caption-01 cl-text-2">Позиция сохранится в выбранном подборе и будет убрана из текущей корзины.</p>
    <button class="tf-btn btn-primary" type="submit">Перенести позицию</button>
</form></dialog>
@endauth
