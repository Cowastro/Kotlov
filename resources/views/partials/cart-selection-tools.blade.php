<section class="cart-save-tools {{ $b2bClient ? 'cart-save-tools--business' : 'cart-save-tools--retail' }}" aria-label="Сохранение подбора">
    @auth
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <p class="fw-semibold"><span class="cart-saved-dot" aria-hidden="true"></span> Автосохранение</p>
            <div class="cart-save-links"><a class="link" href="{{ route('selections.index') }}">Мои заявки</a>@if($b2bClient && count($items))<a class="link" href="{{ route('cart.print') }}" target="_blank" rel="noopener">Печать / PDF</a>@endif</div>
        </div>
        <details class="cart-save-details"><summary>{{ $b2bClient ? 'Сохранить подбор для клиента или объекта' : 'Сохранить список или распечатать' }}</summary><p class="text-caption-01 cl-text-2 mb-3">Сохраните отдельный подбор с названием клиента или объекта. К нему можно вернуться после повторного входа.</p>
        @if (session('error'))<p role="alert" class="text-danger mb-2">{{ session('error') }}</p>@endif
        @error('name')<p role="alert" class="text-danger mb-2">{{ $message }}</p>@enderror
        @if (count($items) > 0)
            @if ($editingSelection ?? null)
                <form method="post" action="{{ route('selections.store') }}" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    @csrf
                    <input type="hidden" name="selection_id" value="{{ $editingSelection->id }}">
                    <input type="hidden" name="revision" value="{{ session('cart_selection_revision', $editingSelection->revision) }}">
                    <input type="hidden" name="name" value="{{ $editingSelection->name }}">
                    <p>Открыт подбор «{{ $editingSelection->name }}»</p>
                    <button type="submit" class="tf-btn btn-stroke small">Обновить этот подбор</button>
                </form>
            @endif
            <form method="post" action="{{ route('selections.store') }}" class="d-flex flex-wrap align-items-center gap-2">
                @csrf
                <label for="selection-name" class="visually-hidden">Название подбора</label>
                <input id="selection-name" name="name" required maxlength="160" placeholder="Название: клиент или объект" value="{{ old('name') }}" class="form-control flex-grow-1" style="width:auto;min-width:0">
                <button class="tf-btn btn-primary small" type="submit">Сохранить подбор</button>
                <button class="tf-btn btn-stroke small" type="button" data-share-cart>Поделиться</button>
                <a class="tf-btn btn-stroke small" href="{{ route('cart.print') }}" target="_blank" rel="noopener">Печать / PDF</a>
            </form>
        @endif
        </details>
    @else
        <p><a class="link fw-semibold" href="#sign" data-bs-toggle="modal">Войдите</a>, чтобы сохранить корзину в аккаунте.</p>
    @endauth
</section>
