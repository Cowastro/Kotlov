@if ($heaterEquipmentGroups->isNotEmpty())
    <section class="flat-spacing" id="compatible-heater-equipment" aria-labelledby="compatible-heater-equipment-title" style="scroll-margin-top: 100px">
        <div class="container">
            <div class="sect-heading type-2 text-center">
                <h2 class="s-title" id="compatible-heater-equipment-title">Совместимые комплектующие</h2>
                <p>Для этой модели обогревателя. Совместимость подтверждена производителем.</p>
            </div>
            @foreach ($heaterEquipmentGroups as $group)
                <div class="mb-24" data-heater-equipment-group="{{ $group['kind'] }}">
                    <h3 class="text-body-1 fw-semibold mb-8">{{ match ($group['kind']) { 'control' => 'Блоки управления', 'feet' => 'Ножки и шасси', 'wall' => 'Настенные крепления', default => 'Для установки' } }}</h3>
                    <p class="cl-text-2 mb-16">{{ match ($group['requirement']) {
                        'required' => 'Для работы отопительного модуля нужен один блок управления. Выберите подходящий вариант.',
                        'floor' => 'Приобретаются отдельно, если устанавливаете обогреватель на пол.',
                        'wall' => 'Приобретается отдельно для настенной установки.'
                    } }}</p>
                    <div class="tf-grid-layout tf-col-2 md-col-3 xl-col-4">
                        @foreach ($group['products'] as $part)
                            <div>
                                @include('partials.product-card', ['product' => $part['product']])
                                <a href="{{ $part['source'] }}" target="_blank" rel="noopener" class="link text-caption-01 d-inline-block mt-8">Совместимость у производителя ↗</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
