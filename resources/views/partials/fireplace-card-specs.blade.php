@php($cardSummary = \App\Support\Catalog\CardSpecificationSummary::select($fireplaceCardSpecs ?? []))
<ul class="fireplace-card-specs catalog-card-specs list-unstyled text-caption-01 cl-text-2 m-0" aria-label="Основные характеристики">
    @forelse ($cardSummary as $label => $value)
        <li title="{{ $label }}: {{ $value }}" aria-label="{{ $label }}: {{ $value }}">
            @if (str_starts_with($label, 'Теплоотдача') && str_contains($label, 'ΔT'))
                {{ $value }} · {{ substr($label, strpos($label, 'ΔT')) }}
            @elseif ($label === 'Количество секций')
                {{ $value }} секц.
            @elseif (in_array($label, ['Объём обогрева', 'Объём парной', 'Стекло', 'Дверца', 'Оценка при потолке 2,5 м', 'Высота', 'Ширина', 'Ø насоса', 'Скважина от', 'Макс. напор', 'Макс. расход', 'Макс. всасывание', 'Макс. погружение', 'Твёрдые частицы до', 'Гидроаккумулятор', 'Номин. напор', 'Номин. расход', 'Напор по таблице', 'Расход по таблице', 'Мощность двигателя', 'Потребляемая мощность', 'Свободный проход', 'Подключение DN'], true))
                {{ match ($label) { 'Гидроаккумулятор' => 'Бак', 'Твёрдые частицы до' => 'Частицы до', 'Макс. всасывание' => 'Всасывание до', 'Макс. погружение' => 'Погружение до', 'Мощность двигателя' => 'Двигатель', 'Потребляемая мощность' => 'Потребление', 'Номин. напор' => 'Ном. напор', 'Номин. расход' => 'Ном. расход', 'Напор по таблице' => 'Напор (табл.)', 'Расход по таблице' => 'Расход (табл.)', default => $label } }}: {{ $value }}
            @else
                <span class="visually-hidden">{{ $label }}: </span>{{ $value }}
            @endif
        </li>
    @empty
        <li class="catalog-card-specs__pending">Параметры пока не подтверждены</li>
    @endforelse
</ul>
