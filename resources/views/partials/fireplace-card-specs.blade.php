<ul class="fireplace-card-specs list-unstyled d-flex flex-wrap gap-1 text-caption-01 cl-text-2 m-0" aria-label="Характеристики">
    @foreach ($fireplaceCardSpecs as $label => $value)
        <li class="border rounded-pill px-2">
            @if ($label === 'Теплоотдача при ΔT 70 °C')
                {{ $value }} · ΔT 70 °C
            @elseif ($label === 'Количество секций')
                {{ $value }} секц.
            @elseif (in_array($label, ['Объём обогрева', 'Объём парной', 'Стекло', 'Дверца', 'Оценка при потолке 2,5 м', 'Высота', 'Ширина'], true))
                {{ $label }}: {{ $value }}
            @else
                <span class="visually-hidden">{{ $label }}: </span>{{ $value }}
            @endif
        </li>
    @endforeach
</ul>
