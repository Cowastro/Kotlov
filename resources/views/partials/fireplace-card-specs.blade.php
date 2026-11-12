<ul class="fireplace-card-specs list-unstyled d-flex flex-wrap gap-1 text-caption-01 cl-text-2 m-0" aria-label="Характеристики">
    @foreach ($fireplaceCardSpecs as $label => $value)
        <li class="border rounded-pill px-2">
            @if (str_starts_with($label, 'Теплоотдача') && str_contains($label, 'ΔT'))
                {{ $value }} · {{ substr($label, strpos($label, 'ΔT')) }}
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
