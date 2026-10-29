@extends('layouts.amerce')

@section('title', 'Обозначения счетчиков газа БелОМО КАТА и СКАТ — варианты исполнения | KOTLOV')
@section('description', 'Расшифровка обозначений ультразвуковых счетчиков газа БелОМО КАТА и СКАТ: типоразмер, цифровой выход, NB-IoT/GSM, клапан, датчик давления, резьба. Таблица всех вариантов исполнения — база знаний KOTLOV.')

@php
    use App\Support\Catalog\BelomoGasMeterDesignations as Belomo;

    $optionsUsed = collect($models)->mapWithKeys(fn ($m, $key) => [$key => $m['options']]);
    $adapterLabels = ['pc' => 'ПК', 'modbus' => 'Modbus'];
@endphp

@push('styles')
<style>
    .bm-doc{padding-bottom:72px}
    .bm-doc__actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;margin-top:20px}
    .bm-doc__btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:999px;border:1px solid #111;font-weight:600;color:#111;background:#fff;text-decoration:none}
    .bm-doc__btn--dark{background:#111;color:#fff}
    .bm-doc__btn:hover{opacity:.85}
    .bm-doc__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
    .bm-doc__card{padding:28px;border:1px solid var(--line);border-radius:20px;background:#fff}
    .bm-doc__card h2{margin:0 0 4px;font-size:clamp(22px,2.4vw,30px)}
    .bm-doc__meta{margin:0 0 18px;color:var(--text-2);font-size:13px}
    .bm-doc__formula{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:18px;padding:14px 16px;border-radius:12px;background:var(--bg);font-family:ui-monospace,Menlo,Consolas,monospace;font-size:15px}
    .bm-doc__formula span{padding:3px 8px;border-radius:6px;background:#fff;border:1px solid var(--line)}
    .bm-doc__formula b{font-family:inherit}
    .bm-doc__legend{display:grid;gap:14px;margin:0}
    .bm-doc__legend dt{font-weight:650;margin-bottom:4px}
    .bm-doc__legend dd{margin:0;color:var(--text-2);font-size:14px;line-height:1.55}
    .bm-doc__legend code{color:#111;font-weight:700;background:none;padding:0}
    .bm-doc__example{margin-top:18px;padding:14px 16px;border-left:3px solid #111;background:#f7f7f7;font-size:14px;line-height:1.55}
    .bm-doc__section{margin-top:44px}
    .bm-doc__section > h2{margin-bottom:8px}
    .bm-doc__section > p{color:var(--text-2);max-width:820px}
    .bm-doc__matrix{width:100%;border-collapse:collapse;font-size:14px;background:#fff}
    .bm-doc__matrix th,.bm-doc__matrix td{padding:11px 12px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
    .bm-doc__matrix th{background:var(--bg);font-weight:650}
    .bm-doc__matrix td.c{text-align:center}
    .bm-doc__filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:18px 0 14px}
    .bm-doc__filters input,.bm-doc__filters select{height:44px;padding:0 14px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:14px}
    .bm-doc__filters input{flex:1 1 260px;min-width:0}
    .bm-doc__count{color:var(--text-2);font-size:13px;margin-left:auto}
    .bm-doc__table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:14px}
    .bm-doc__code{font-family:ui-monospace,Menlo,Consolas,monospace;white-space:nowrap;cursor:copy}
    .bm-doc__code:hover{text-decoration:underline}
    .bm-doc__tag{display:inline-block;padding:2px 8px;border-radius:999px;background:var(--bg);font-size:12px;white-space:nowrap}
    .bm-doc__note{margin-top:20px;padding:16px 18px;border-left:3px solid #111;background:#f7f7f7;color:var(--text-2);font-size:13px;line-height:1.55}
    @media(max-width:767px){
        .bm-doc{padding-bottom:48px}
        .bm-doc__grid{grid-template-columns:1fr}
        .bm-doc__card{padding:20px}
        .bm-doc__count{margin-left:0;width:100%}
        .bm-doc__matrix th,.bm-doc__matrix td{padding:9px 8px;font-size:13px}
    }
</style>
@endpush

@section('content')
<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ \App\Support\KnowledgeBase::URL }}" class="text-caption-01 cl-text-3 link">База знаний</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Обозначения КАТА и СКАТ</p>
                </div>
                <h1 class="h3">Обозначения счетчиков газа БелОМО КАТА и СКАТ</h1>
                <p class="text-body-1 cl-text-2">
                    Техническая информация: как расшифровать наименование ультразвукового счетчика
                    и какой вариант исполнения указать в заказе.
                </p>
                <div class="bm-doc__actions">
                    <a class="bm-doc__btn bm-doc__btn--dark" href="{{ \App\Support\Catalog\GasMeterSelectionGuide::PAGE_URL }}">Подобрать счётчик</a>
                    <a class="bm-doc__btn" href="{{ Belomo::PDF_URL }}" target="_blank" rel="noopener">Скачать PDF производителя</a>
                    <a class="bm-doc__btn" href="{{ Belomo::CSV_URL }}">Скачать таблицу (Excel/CSV)</a>
                    <a class="bm-doc__btn" href="/brands/belomo">Счетчики БелОМО в каталоге</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bm-doc flat-spacing">
        <div class="container">

            <div class="bm-doc__grid">
                <article class="bm-doc__card">
                    <h2>КАТА</h2>
                    <p class="bm-doc__meta">{{ $models['kata']['title'] }} · {{ $models['kata']['tu'] }} · базовое обозначение {{ $models['kata']['base'] }}</p>
                    <div class="bm-doc__formula"><b>КАТА</b> – <span>G4</span> <span>RK</span> – <span>2</span></div>
                    <dl class="bm-doc__legend">
                        <div>
                            <dt>1. Типоразмер</dt>
                            <dd><code>G4</code>, <code>G6</code></dd>
                        </div>
                        <div>
                            <dt>2. Дополнительное устройство (не указывается в базовом исполнении)</dt>
                            @foreach ($models['kata']['options'] as $option)
                                <dd><code>{{ $option }}</code> — {{ Belomo::OPTIONS[$option] }}</dd>
                            @endforeach
                        </div>
                        <div>
                            <dt>3. Трубная резьба на присоединительных патрубках</dt>
                            @foreach (Belomo::THREADS as $num => $thread)
                                <dd><code>{{ $num }}</code> — {{ $thread }}</dd>
                            @endforeach
                        </div>
                    </dl>
                    <div class="bm-doc__example">
                        <strong>Пример:</strong> «КАТА – G4 RK – 2 (GSM)» — типоразмер G4, беспроводная передача данных
                        по GSM и клапан, резьба G1. Обозначение исполнения <span class="bm-doc__code">8348.00.00.000-43</span>.
                    </div>
                </article>

                <article class="bm-doc__card">
                    <h2>СКАТ</h2>
                    <p class="bm-doc__meta">{{ $models['skat']['title'] }} · {{ $models['skat']['tu'] }} · базовое обозначение {{ $models['skat']['base'] }}</p>
                    <div class="bm-doc__formula"><b>СКАТ</b> – <span>G10</span> <span>RKP</span></div>
                    <dl class="bm-doc__legend">
                        <div>
                            <dt>1. Типоразмер</dt>
                            <dd><code>G6</code>, <code>G10</code></dd>
                        </div>
                        <div>
                            <dt>2. Дополнительное устройство (не указывается в базовом исполнении)</dt>
                            @foreach ($models['skat']['options'] as $option)
                                <dd><code>{{ $option }}</code> — {{ Belomo::OPTIONS[$option] }}</dd>
                            @endforeach
                        </div>
                    </dl>
                    <div class="bm-doc__example">
                        <strong>Пример:</strong> «СКАТ – G10 RKP (NB-IoT)» — типоразмер G10, передача данных по NB-IoT,
                        клапан и датчик давления. Обозначение исполнения <span class="bm-doc__code">8349.00.00.000-17</span>.
                    </div>
                </article>
            </div>

            <div class="bm-doc__section">
                <h2>Какие опции есть у каждой модели</h2>
                <p>Для исполнений R, RK, RP и RKP в заказе обязательно указывайте канал связи — NB-IoT или GSM: это разные варианты исполнения.
                    Адаптер для подключения ПК или Modbus поставляется только с цифровым выходом (B, у СКАТ — также BP).</p>
                <div class="bm-doc__table-wrap">
                    <table class="bm-doc__matrix">
                        <thead>
                            <tr>
                                <th>Опция</th>
                                <th>Расшифровка</th>
                                <th class="c">КАТА</th>
                                <th class="c">СКАТ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>—</strong></td>
                                <td>Базовое исполнение без дополнительного устройства</td>
                                <td class="c">✓</td>
                                <td class="c">✓</td>
                            </tr>
                            @foreach (Belomo::OPTIONS as $option => $label)
                                <tr>
                                    <td><strong>{{ $option }}</strong></td>
                                    <td>{{ $label }}</td>
                                    <td class="c">{{ in_array($option, $optionsUsed['kata'], true) ? '✓' : '—' }}</td>
                                    <td class="c">{{ in_array($option, $optionsUsed['skat'], true) ? '✓' : '—' }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td><strong>Резьба 1/2/3</strong></td>
                                <td>G¾ / G1 / G1¼ на присоединительных патрубках</td>
                                <td class="c">✓</td>
                                <td class="c">—</td>
                            </tr>
                            <tr>
                                <td><strong>Адаптер</strong></td>
                                <td>Для подключения ПК или по протоколу Modbus</td>
                                <td class="c">с B</td>
                                <td class="c">с B, BP</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bm-doc__section" id="variants">
                <h2>Все варианты исполнения</h2>
                <p>Таблица соответствия обозначения исполнения и наименования счетчика при заказе и в другой документации.
                    Нажмите на обозначение, чтобы скопировать его.</p>

                <div class="bm-doc__filters" data-bm-filters>
                    <input type="search" placeholder="Поиск: G6 RK, GSM, Modbus, 8348…-43" data-bm-search aria-label="Поиск по вариантам исполнения">
                    <select data-bm-model aria-label="Модель">
                        <option value="">Все модели</option>
                        @foreach ($models as $key => $model)
                            <option value="{{ $key }}">{{ $model['name'] }}</option>
                        @endforeach
                    </select>
                    <select data-bm-size aria-label="Типоразмер">
                        <option value="">Все типоразмеры</option>
                        @foreach (['G4', 'G6', 'G10'] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                    <select data-bm-network aria-label="Связь">
                        <option value="">Любая связь</option>
                        <option value="NB-IoT">NB-IoT</option>
                        <option value="GSM">GSM</option>
                        <option value="none">Без беспроводной связи</option>
                    </select>
                    <span class="bm-doc__count" data-bm-count>{{ count($variants) }} из {{ count($variants) }}</span>
                </div>

                <div class="bm-doc__table-wrap">
                    <table class="bm-doc__matrix">
                        <thead>
                            <tr>
                                <th>Обозначение</th>
                                <th>Наименование при заказе</th>
                                <th>Типоразмер</th>
                                <th>Опция</th>
                                <th>Резьба</th>
                                <th>Связь</th>
                                <th>Адаптер</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variants as $row)
                                <tr data-bm-row
                                    data-model="{{ $row['model'] }}"
                                    data-size="{{ $row['size'] }}"
                                    data-network="{{ $row['network'] ?? 'none' }}"
                                    data-text="{{ mb_strtolower($row['code'] . ' ' . $row['name']) }}">
                                    <td><span class="bm-doc__code" data-bm-copy title="Скопировать">{{ $row['code'] }}</span></td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['size'] }}</td>
                                    <td>{{ $row['option'] ?? '—' }}</td>
                                    <td>{{ $row['thread'] ? $row['thread'] . ' · ' . Belomo::THREADS[$row['thread']] : '—' }}</td>
                                    <td>@if ($row['network'])<span class="bm-doc__tag">{{ $row['network'] }}</span>@else — @endif</td>
                                    <td>{{ $row['adapter'] ? $adapterLabels[$row['adapter']] : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bm-doc__note">
                    Источник: таблицы соответствия обозначений ОАО «БелОМО» 8348.00.00.000 ТБ (КАТА) и 8349.00.00.000 ТБ (СКАТ).
                    Перед заказом сверяйте вариант исполнения с проектом и требованиями газоснабжающей организации.
                    Нужна помощь с подбором или наличием — пишите на <a href="mailto:info@kotlov.by">info@kotlov.by</a> или звоните +375 (29) 377-70-92.
                </div>
            </div>

        </div>
    </section>

</main>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.querySelector('[data-bm-filters]');
    if (!root) return;
    var search = root.querySelector('[data-bm-search]');
    var model = root.querySelector('[data-bm-model]');
    var size = root.querySelector('[data-bm-size]');
    var network = root.querySelector('[data-bm-network]');
    var count = root.querySelector('[data-bm-count]');
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-bm-row]'));

    function apply() {
        var terms = search.value.toLowerCase().split(/\s+/).filter(Boolean);
        var shown = 0;
        rows.forEach(function (row) {
            var ok = (!model.value || row.dataset.model === model.value)
                && (!size.value || row.dataset.size === size.value)
                && (!network.value || row.dataset.network === network.value)
                && terms.every(function (t) { return row.dataset.text.indexOf(t) !== -1; });
            row.hidden = !ok;
            if (ok) shown++;
        });
        count.textContent = shown + ' из ' + rows.length;
    }

    [search, model, size, network].forEach(function (el) { el.addEventListener('input', apply); });

    document.querySelectorAll('[data-bm-copy]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!navigator.clipboard) return;
            var text = el.textContent.trim();
            navigator.clipboard.writeText(text).then(function () {
                el.textContent = 'Скопировано';
                setTimeout(function () { el.textContent = text; }, 1000);
            });
        });
    });
})();
</script>
@endpush
