<section class="border rounded-4 p-3 mb-4" aria-label="Сохранение подбора">
    @auth
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <p class="fw-semibold">Корзина сохраняется в вашем аккаунте автоматически</p>
            <a class="link" href="{{ route('selections.index') }}">Мои подборы →</a>
        </div>
        <p class="text-caption-01 cl-text-2 mb-3">После повторного входа товары восстановятся. Сохраните отдельный подбор для клиента или объекта, чтобы вернуться к нему позже.</p>
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
                <a class="tf-btn btn-stroke small" href="{{ route('cart.print') }}" target="_blank" rel="noopener">Печать / PDF</a>
            </form>
        @endif
    @else
        <p><a class="link fw-semibold" href="#sign" data-bs-toggle="modal">Войдите в аккаунт</a>, чтобы корзина сохранялась после завершения сессии и можно было сохранить подбор для клиента.</p>
    @endauth
</section>
