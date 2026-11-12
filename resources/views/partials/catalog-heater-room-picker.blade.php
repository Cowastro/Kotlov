@php
    $roomParameters = request()->except('page', 'room_area');
    // A room-size selection replaces the previous technical area band.
    if (is_array($roomParameters['heating'] ?? null)) {
        unset($roomParameters['heating']['area']);
    } else {
        unset($roomParameters['heating']);
    }
@endphp
<div class="mt-16" data-heater-room-picker>
    <form method="get" action="{{ url()->current() }}#catalog-products" class="d-flex flex-wrap align-items-center gap-2"
          aria-label="Подбор обогревателя по площади комнаты">
        @foreach (\Illuminate\Support\Arr::dot($roomParameters) as $key => $value)
            @if (is_scalar($value))
                <input type="hidden" name="{{ preg_replace('/\.([^.]+)/', '[$1]', $key) }}" value="{{ $value }}">
            @endif
        @endforeach
        <label for="heater-room-area" class="fw-medium">Площадь комнаты, м²</label>
        <input id="heater-room-area" class="form-control" style="width:120px;max-width:100%" name="room_area"
               type="text" inputmode="decimal" pattern="[0-9]+([.,][0-9]{1,2})?" maxlength="7"
               value="{{ $roomArea !== null ? str_replace('.', ',', (string) $roomArea) : '' }}" placeholder="Например, 20"
               aria-describedby="heater-room-help{{ $roomAreaInvalid ? ' heater-room-error' : '' }}" @if($roomAreaInvalid) aria-invalid="true" @endif>
        <button class="tf-btn btn-primary small" type="submit">Подобрать</button>
        @if ($roomArea !== null || $roomAreaInvalid)
            <a href="{{ request()->fullUrlWithoutQuery(['room_area', 'page']) }}#catalog-products" class="link text-caption-01">Сбросить площадь</a>
        @endif
    </form>
    @if ($roomAreaInvalid)
        <p id="heater-room-error" role="alert" class="text-primary text-caption-01 mt-8">Введите площадь от 1 до 1000 м². Подбор по площади не применён.</p>
    @endif
    <p id="heater-room-help" class="cl-text-3 text-caption-01 mt-8 mb-0">
        Предварительный подбор по площади, заявленной производителем.
    </p>
    <details class="cl-text-3 text-caption-01 mt-8">
        <summary class="link">Как работает подбор</summary>
        <p class="mt-8 mb-0">Показываем модели с заявленной производителем площадью не меньше площади комнаты. Модели без этих данных не участвуют в подборе. Для основного отопления учитывайте теплопотери и условия помещения.</p>
    </details>
</div>
