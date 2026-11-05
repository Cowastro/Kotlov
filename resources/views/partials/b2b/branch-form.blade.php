<form class="b2b-cabinet-form" method="POST" enctype="multipart/form-data" action="{{ $branchForm ? route('b2b.branches.update', $branchForm) : route('b2b.branches.store') }}" style="margin-top:20px">
    @csrf
    <fieldset @disabled($adminPreview) style="border:0;padding:0;margin:0;min-width:0">
        <div class="b2b-cabinet-form__grid">
            <label><span>Город *</span><select name="city_id" required><option value="">Выберите город</option>@foreach($branchCities as $branchCity)<option value="{{ $branchCity->id }}" @selected(($draft['city_id'] ?? null) == $branchCity->id)>{{ $branchCity->name }}</option>@endforeach</select></label>
            <label><span>Название магазина *</span><input name="name" value="{{ $draft['name'] ?? '' }}" required maxlength="255"></label>
            <label><span>Адрес *</span><input name="address" value="{{ $draft['address'] ?? '' }}" required maxlength="255"></label>
            <label><span>Телефон *</span><input name="phone" value="{{ $draft['phones'][0] ?? '' }}" type="tel" required maxlength="50"></label>
            <label><span>Email</span><input name="email" value="{{ $draft['email'] ?? '' }}" type="email" maxlength="255"></label>
            <label><span>Режим работы</span><input name="working_hours" value="{{ $draft['working_hours'] ?? '' }}" maxlength="255"></label>
            <label><span>Ссылка на карту</span><input name="map_url" value="{{ $draft['map_url'] ?? '' }}" type="url" maxlength="1000"></label>
            <label><span>Заголовок страницы магазина</span><input name="storefront_title" value="{{ $draft['storefront_title'] ?? '' }}" maxlength="255"></label>
            <label><span>Краткое описание</span><textarea name="storefront_intro" rows="4" maxlength="1200">{{ $draft['storefront_intro'] ?? '' }}</textarea></label>
            <label><span>Услуги и предложения магазина</span><textarea name="storefront_promo" rows="4" maxlength="1500">{{ $draft['storefront_promo'] ?? '' }}</textarea></label>
            <label><span>Главная фотография</span><input name="hero" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG или WebP, до 5 МБ</small></label>
            <label><span>Фотографии магазина</span><input name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><small>До 6 фото по 5 МБ. Новая подборка заменит прежнюю после согласования.</small></label>
        </div>
        <button type="submit" class="b2b-cabinet-primary" style="margin-top:16px">Отправить на проверку</button>
    </fieldset>
    @if($adminPreview)<p>В служебном просмотре заполнение отключено. Партнёр сможет заполнить эту форму в своём кабинете.</p>@endif
</form>
