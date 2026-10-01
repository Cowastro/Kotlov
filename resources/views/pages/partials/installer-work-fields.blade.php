@php $workItem = $item; @endphp

<fieldset class="tf-field mb-14">
    <label class="tf-lable fw-medium">Название работы *</label>
    <input name="title" maxlength="255" value="{{ old('title', $workItem?->title) }}" required placeholder="Например: тепловой насос 16 кВт в Смолевичах">
</fieldset>
<fieldset class="tf-field mb-14">
    <label class="tf-lable fw-medium">Описание</label>
    <textarea name="description" maxlength="3000" placeholder="Что было смонтировано и какой результат получил заказчик">{{ old('description', $workItem?->description) }}</textarea>
</fieldset>
<div class="tf-grid-layout sm-col-2 mb-14">
    <fieldset class="tf-field">
        <label class="tf-lable fw-medium">Тип работы</label>
        <select name="work_type">
            <option value="">— Выберите —</option>
            @foreach($workTypes as $value => $label)
            <option value="{{ $value }}" @selected(old('work_type', $workItem?->work_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </fieldset>
    <fieldset class="tf-field">
        <label class="tf-lable fw-medium">Дата завершения</label>
        <input type="date" name="completed_at" max="{{ date('Y-m-d') }}" value="{{ old('completed_at', $workItem?->completed_at?->format('Y-m-d')) }}">
    </fieldset>
</div>
<div class="tf-grid-layout sm-col-2 mb-14">
    <fieldset class="tf-field"><label class="tf-lable fw-medium">Город / населённый пункт</label><input name="city" value="{{ old('city', $workItem?->city) }}"></fieldset>
    <fieldset class="tf-field">
        <label class="tf-lable fw-medium">Регион</label>
        <select name="region"><option value="">— Не указан —</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(old('region', $workItem?->region) === $region)>{{ $region }}</option>@endforeach</select>
    </fieldset>
</div>
<div class="tf-grid-layout sm-col-2 mb-14">
    <fieldset class="tf-field"><label class="tf-lable fw-medium">Оборудование</label><input name="equipment_type" value="{{ old('equipment_type', $workItem?->equipment_type) }}" placeholder="Тепловой насос 16 кВт, R290"></fieldset>
    <fieldset class="tf-field"><label class="tf-lable fw-medium">Бренд</label><input name="brand" value="{{ old('brand', $workItem?->brand) }}" placeholder="KOTLOV GE"></fieldset>
</div>
<fieldset class="tf-field mb-14">
    <label class="tf-lable fw-medium">Фотографии</label>
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
    <span class="text-caption-01 cl-text-3 mt-4">До 10 файлов, каждый до 8 МБ. При редактировании новые фото добавятся к существующим.</span>
</fieldset>
<label class="installer-cabinet__check">
    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $workItem?->is_published ?? true))>
    <span>Показывать работу в публичном профиле</span>
</label>
