<ul class="fireplace-card-specs list-unstyled d-flex flex-wrap gap-1 text-caption-01 cl-text-2 m-0" aria-label="Характеристики">
    @foreach ($fireplaceCardSpecs as $label => $value)
        <li class="border rounded-pill px-2" style="max-width:100%;overflow-wrap:anywhere" title="{{ $label }}: {{ $value }}">
            @if (str_starts_with($label, 'Теплоотдача') && str_contains($label, 'ΔT'))
                {{ $value }} · {{ substr($label, strpos($label, 'ΔT')) }}
            @elseif ($label === 'Количество секций')
                {{ $value }} секц.
            @elseif (in_array($label, ['Объём обогрева', 'Объём парной', 'Стекло', 'Дверца', 'Оценка при потолке 2,5 м', 'Высота', 'Ширина', 'Ø насоса', 'Скважина от', 'Макс. напор', 'Макс. расход', 'Макс. всасывание', 'Макс. погружение', 'Твёрдые частицы до', 'Гидроаккумулятор', 'Номин. напор', 'Номин. расход', 'Напор по таблице', 'Расход по таблице', 'Мощность двигателя', 'Потребляемая мощность', 'Свободный проход', 'Подключение DN'], true))
                {{ match ($label) { 'Гидроаккумулятор' => 'Бак', 'Твёрдые частицы до' => 'Частицы до', 'Макс. всасывание' => 'Всасывание до', 'Макс. погружение' => 'Погружение до', default => $label } }}: {{ $value }}
            @else
                <span class="visually-hidden">{{ $label }}: </span>{{ $value }}
            @endif
        </li>
    @endforeach
</ul>
