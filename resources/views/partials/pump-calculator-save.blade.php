<details class="pc-save"><summary>{{ $candidate ? 'Добавить в сохранённый подбор' : 'Сохранить расчёт в подбор' }}</summary>
<form method="post" action="{{ route('pump-calculator.save') }}" data-pc-save>@csrf
    @foreach($input as $key=>$value)@if(is_scalar($value) && $value!=='')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
    @if($candidate)<input type="hidden" name="product_id" value="{{ $candidate->id }}"><label>Количество<input type="number" name="quantity" value="1" min="1" max="999" step="1" inputmode="numeric"></label>@endif
    <label>Куда сохранить<select name="selection_id" data-pc-selection><option value="">Новый подбор</option>@foreach($selections as $saved)<option value="{{ $saved->id }}" data-revision="{{ $saved->revision }}">{{ $saved->name }}</option>@endforeach</select></label>
    <input type="hidden" name="revision" value="" data-pc-revision>
    <label data-pc-new-name>Название<input name="name" value="Подбор циркуляционного насоса" maxlength="160" required></label>
    <button class="pc-button is-primary" type="submit">{{ $candidate ? 'Сохранить товар и расчёт' : 'Сохранить расчёт' }}</button>
</form></details>
