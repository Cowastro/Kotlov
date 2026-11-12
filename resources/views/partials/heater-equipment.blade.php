<aside class="border rounded-3 p-3 mb-16" aria-label="Комплектация обогревателя" data-heater-equipment>
    <h2 class="text-body-1 fw-semibold mb-12">Комплектация и установка</h2>
    @if ($heaterEquipment['included'] !== [] || $heaterEquipment['separate'] !== [])
        @if ($heaterEquipment['included'] !== [])
            <p class="fw-semibold mb-8">В комплекте</p>
            <ul class="list-unstyled mb-12 text-caption-01">
                @foreach ($heaterEquipment['included'] as $item)
                    <li class="d-flex gap-2 mb-4"><span class="text-success" aria-hidden="true">✓</span><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        @endif
        @if ($heaterEquipment['separate'] !== [])
            <p class="fw-semibold mb-8">Приобретается отдельно</p>
            <ul class="list-unstyled mb-12 text-caption-01">
                @foreach ($heaterEquipment['separate'] as $item)
                    <li class="mb-8">
                        <span class="badge text-bg-light border rounded-pill me-1">{{ match ($item['requirement']) {
                            'required' => 'Для работы', 'floor' => 'Для установки на пол', 'wall' => 'Для монтажа на стену'
                        } }}</span>
                        {{ $item['label'] }}
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($heaterEquipmentGroups->isNotEmpty())
            <a href="#compatible-heater-equipment" class="link text-caption-01 d-inline-block mb-12">Выбрать совместимые комплектующие ↓</a>
        @endif
        <details class="text-caption-01 cl-text-2">
            <summary>Подтверждено производителем</summary>
            <p class="mt-8 mb-8">Указана подтверждённая часть комплектации. Исполнение и состав поставки сверяйте с паспортом модели перед заказом.</p>
            @foreach ($heaterEquipment['sources'] as $source)
                <a href="{{ $source }}" target="_blank" rel="noopener" class="link d-block mb-4">{{ count($heaterEquipment['sources']) > 1 ? 'Источник '.$loop->iteration : 'Паспорт или карточка производителя' }} ↗</a>
            @endforeach
        </details>
    @else
        <p class="text-caption-01 cl-text-2 mb-0">Точная комплектация этой модели пока не подтверждена. Уточните её у менеджера перед заказом. Совместимые аксессуары без подтверждения производителя не предлагаем.</p>
    @endif
</aside>
