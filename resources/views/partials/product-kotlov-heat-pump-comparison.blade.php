@if (($models ?? collect())->count() > 1)
@push('styles')
<style>
    .kotlov-hp-compare { padding: 52px 0 0; }
    .kotlov-hp-compare__heading { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 22px; }
    .kotlov-hp-compare__heading p { max-width: 660px; }
    .kotlov-hp-compare__scroll { overflow-x: auto; border: 1px solid var(--line); border-radius: 20px; background: var(--white); scrollbar-width: thin; }
    .kotlov-hp-compare__table { width: 100%; min-width: 940px; border-collapse: collapse; }
    .kotlov-hp-compare__table th { padding: 14px 18px; border-bottom: 1px solid var(--line); background: var(--bg-2); color: var(--text-2); font-size: 12px; font-weight: 600; text-align: left; }
    .kotlov-hp-compare__table td { padding: 17px 18px; border-bottom: 1px solid var(--line); color: var(--text); font-size: 14px; vertical-align: middle; }
    .kotlov-hp-compare__table tr:last-child td { border-bottom: 0; }
    .kotlov-hp-compare__table tr.is-current td { background: var(--bg-2); }
    .kotlov-hp-compare__table tr.is-current td:first-child { box-shadow: inset 4px 0 0 var(--primary); }
    .kotlov-hp-compare__model { display: flex; align-items: center; gap: 12px; min-width: 245px; }
    .kotlov-hp-compare__model img { width: 58px; height: 58px; flex: 0 0 58px; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); object-fit: contain; }
    .kotlov-hp-compare__model a { font-weight: 650; line-height: 1.35; }
    .kotlov-hp-compare__current { display: inline-flex; margin-top: 5px; color: var(--primary); font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .kotlov-hp-compare__price { white-space: nowrap; font-weight: 650; }
    .kotlov-hp-compare__link { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-weight: 650; }
    .kotlov-hp-compare__hint { display: none; margin-top: 10px; color: var(--text-2); font-size: 12px; }

    @media (max-width: 767px) {
        .kotlov-hp-compare { padding-top: 36px; }
        .kotlov-hp-compare__heading { align-items: flex-start; flex-direction: column; gap: 8px; }
        .kotlov-hp-compare__hint { display: block; }
    }
</style>
@endpush

<section class="kotlov-hp-compare" aria-labelledby="kotlov-hp-compare-title">
    <div class="container">
        <div class="kotlov-hp-compare__heading">
            <div>
                <p class="text-caption-01 text-primary fw-semibold mb-8">Линейка KOTLOV GE</p>
                <h3 id="kotlov-hp-compare-title">Сравните модели по ключевым параметрам</h3>
            </div>
            <p class="text-body-2 cl-text-2 mb-0">Таблица помогает сузить выбор, но окончательную мощность подтверждаем расчётом теплопотерь и параметров системы отопления.</p>
        </div>

        <div class="kotlov-hp-compare__scroll" tabindex="0" aria-label="Сравнение моделей KOTLOV GE">
            <table class="kotlov-hp-compare__table">
                <thead>
                    <tr>
                        <th>Модель</th>
                        <th>Мощность</th>
                        <th>Хладагент</th>
                        <th>Температура воды</th>
                        <th>Питание</th>
                        <th>Цена</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($models as $model)
                        @php
                            $modelProduct = $model['product'];
                            $modelProfile = $model['profile'];
                            $isCurrent = (int) $modelProduct->id === (int) $currentProduct->id;
                            $modelUrl = '/' . $modelProduct->category->slug . '/' . $modelProduct->slug;
                        @endphp
                        <tr @class(['is-current' => $isCurrent])>
                            <td>
                                <div class="kotlov-hp-compare__model">
                                    <img loading="lazy" width="58" height="58" src="{{ $modelProduct->image_url }}"
                                         alt="{{ $modelProduct->name }}"
                                         onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
                                    <div>
                                        <a href="{{ $modelUrl }}" class="link">{{ $modelProfile['model'] ?: $modelProduct->name }}</a>
                                        @if ($isCurrent)
                                            <span class="kotlov-hp-compare__current">Вы смотрите</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $modelProfile['power'] ?: 'По расчёту' }}</td>
                            <td>{{ $modelProfile['refrigerant'] ?: 'Уточняется' }}</td>
                            <td>{{ $modelProfile['flow_temperature'] ?: 'Уточняется' }}</td>
                            <td>{{ $modelProfile['power_supply'] ?: 'Уточняется' }}</td>
                            <td class="kotlov-hp-compare__price">{{ number_format((float) $modelProduct->price, 2, '.', ' ') }} BYN</td>
                            <td>
                                @if ($isCurrent)
                                    <a href="#engineeringCalculation" data-bs-toggle="modal" class="kotlov-hp-compare__link text-primary">Рассчитать <span aria-hidden="true">→</span></a>
                                @else
                                    <a href="{{ $modelUrl }}" class="kotlov-hp-compare__link">Подробнее <span aria-hidden="true">→</span></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="kotlov-hp-compare__hint mb-0">Проведите по таблице влево, чтобы увидеть все параметры.</p>
    </div>
</section>
@endif
