@if ($pelletComparisonProducts->isNotEmpty())
    @php
        $comparisonNotes = [
            'pelletnaya-gorelka-kotlov-xo-evo-18-kvt-ea140' => 'Для небольших отопительных систем',
            'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3' => 'Для частного дома и объектов средней мощности',
            'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt' => 'Для коммерческих и производственных объектов',
        ];
    @endphp
    <section class="pellet-comparison flat-spacing pt-0" aria-labelledby="pellet-comparison-title">
        <div class="container">
            <div class="heading-section mb-24">
                <p class="text-caption-01 cl-text-3 mb-8">Быстрый ориентир</p>
                <h2 id="pellet-comparison-title">Сравнение популярных пеллетных горелок</h2>
                <p class="text-body-1 cl-text-2 mt-8">Точный выбор зависит от теплопотерь, котла и дымохода — таблица помогает определить подходящий класс мощности.</p>
            </div>
            <div class="pellet-comparison__wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Модель</th>
                            <th>Мощность</th>
                            <th>Ориентир применения</th>
                            <th>Цена</th>
                            <th>Наличие</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pelletComparisonProducts as $product)
                            @php
                                preg_match('/(\d+(?:[.,]\d+)?)\s*кВт/ui', $product->name, $powerMatch);
                                $productUrl = '/' . $product->category->slug . '/' . $product->slug;
                            @endphp
                            <tr>
                                <td>
                                    <div class="pellet-comparison__model">
                                        <a href="{{ $productUrl }}"><img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy"></a>
                                        <a href="{{ $productUrl }}">{{ $product->name }}</a>
                                    </div>
                                </td>
                                <td>{{ $powerMatch[1] ?? 'Уточнить' }}{{ isset($powerMatch[1]) ? ' кВт' : '' }}</td>
                                <td>{{ $comparisonNotes[$product->slug] ?? 'Подбор по параметрам объекта' }}</td>
                                <td class="pellet-comparison__price">{{ number_format((float) $product->price, 0, '.', ' ') }} BYN</td>
                                <td>{{ $product->availabilityLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endif
