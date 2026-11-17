<div class="cart-list-tools">
    <label class="cart-check-all"><input type="checkbox" id="cart-select-all" @checked($cartSelection['positions'] === count($items))> Выбрать всё</label>
    <span class="cart-selection-count">Выбрано <strong data-selected-positions>{{ $cartSelection['positions'] }}</strong> из {{ count($items) }}</span>
    <label class="cart-find"><span class="visually-hidden">Найти в корзине</span><input type="search" id="cart-find" placeholder="Найти товар или артикул"></label>
</div>
<p class="cart-feedback" id="cart-save-state" role="status" aria-live="polite">{{ auth()->check() ? 'Все изменения сохраняются в аккаунте' : 'Изменения сохраняются в этой корзине' }}</p>
<p class="cart-error" id="cart-error" role="alert" hidden></p>
<p class="cart-search-empty" hidden>Совпадений нет. Измените запрос — товары остаются в корзине.</p>
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
            @if (($item['pricing_type'] ?? null) === 'b2b')<small class="cart-line__warehouse">Партнёрская цена@if (! empty($item['warehouse_name'])) · {{ $item['warehouse_name'] }}@endif</small>@endif
        </div>
        <details class="cart-line__menu"><summary aria-label="Действия с {{ $item['name'] }}">⋯</summary><div>
            <button type="button" data-edit-line-note>Комментарий к позиции</button>
            @auth<button type="button" data-move-line>В другой подбор</button>@endauth
            <form action="{{ route('cart.remove') }}" method="post">@csrf<input type="hidden" name="product_id" value="{{ $item['id'] }}"><button type="submit" class="cart-line__remove">Удалить</button></form>
        </div></details>
        <div class="cart-line__price"><span data-unit-price>{{ number_format($item['price'], 2, ',', ' ') }}</span> × <span data-line-quantity>{{ $item['quantity'] }}</span><small>BYN за штуку</small></div>
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
