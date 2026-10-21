@extends('layouts.amerce')

@section('title', 'Как подобрать газовый счётчик — простой онлайн-подбор | KOTLOV')
@section('description', 'Простой подбор газового счётчика по расходу плиты, котла и колонки, направлению газа, монтажной длине и месту установки. Объясняем G1,6, G2,5, G4 и G6.')

@php
    use App\Support\Catalog\BelomoGasMeterDesignations;
    use App\Support\Catalog\GasMeterSelectionGuide;
@endphp

@push('styles')
<style>
    .gm-guide{padding-bottom:72px}.gm-guide__intro{max-width:860px;margin:0 auto 34px;color:var(--text-2);font-size:17px;line-height:1.65;text-align:center}
    .gm-picker{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:24px;margin-bottom:48px}
    .gm-panel{padding:28px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 12px 35px rgba(20,30,20,.05)}
    .gm-step{margin-bottom:26px}.gm-step:last-child{margin-bottom:0}.gm-step h2{margin:0 0 6px;font-size:21px}.gm-step>p{margin:0 0 14px;color:var(--text-2);font-size:14px}
    .gm-appliances{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.gm-appliance{position:relative;display:flex;align-items:flex-start;gap:10px;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer}.gm-appliance:has(input:checked){border-color:#111;background:#f7f7f7}.gm-appliance input{margin-top:3px}.gm-appliance strong,.gm-appliance small{display:block}.gm-appliance small{margin-top:3px;color:var(--text-2)}
    .gm-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.gm-field label{display:block;margin-bottom:6px;font-size:13px;font-weight:650}.gm-field select,.gm-field input{width:100%;height:46px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:#fff}
    .gm-calculate{display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:100%;min-height:52px;padding:12px 24px;border:0;border-radius:999px;background:#111;color:#fff;font-weight:700;line-height:1.2;text-align:center}.gm-calculate:hover{background:var(--primary)}
    .gm-result{position:sticky;top:110px;align-self:start}.gm-result__empty{display:grid;place-items:center;min-height:320px;text-align:center;color:var(--text-2)}.gm-result__empty span{display:block;margin-bottom:12px;font-size:44px}.gm-result__summary{padding:18px;border-radius:14px;background:#111;color:#fff}.gm-result__summary small{display:block;opacity:.72}.gm-result__summary strong{display:block;margin:3px 0 5px;font-size:28px}.gm-result__summary em{display:block;margin-top:6px;font-size:12px;font-style:normal;opacity:.72}.gm-result__models{display:grid;gap:10px;margin-top:14px}.gm-result__model{padding:14px;border:1px solid var(--line);border-radius:12px}.gm-result__model--best{border:2px solid #27833f;background:#f7faf6}.gm-result__badge{display:inline-block;margin-bottom:7px;padding:4px 8px;border-radius:999px;background:#27833f;color:#fff;font-size:11px;font-weight:700}.gm-result__family{margin-bottom:3px;color:#27833f;font-size:12px;font-weight:700}.gm-result__model strong{display:block}.gm-result__model p{margin:5px 0;color:var(--text-2);font-size:13px}.gm-result__commercial{display:flex;justify-content:space-between;gap:8px;margin:10px 0;font-size:13px}.gm-result__commercial b{color:var(--primary)}.gm-result__model a{display:inline-flex;justify-content:center;width:100%;padding:10px 14px;border-radius:999px;background:#111;color:#fff;font-size:13px;font-weight:700}.gm-result__model a:hover{background:var(--primary)}.gm-result__model a.gm-result__tech{margin-top:7px;border:1px solid #111;background:#fff;color:#111}.gm-result__model a.gm-result__tech:hover{border-color:var(--primary);color:var(--primary)}.gm-result__warning{margin:14px 0 0;padding:12px;border-radius:10px;background:#fff6df;color:#745500;font-size:13px;line-height:1.45}
    .gm-section{margin-top:46px}.gm-section h2{margin-bottom:10px}.gm-section__lead{max-width:820px;color:var(--text-2)}.gm-family-grid,.gm-sizes{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:20px}.gm-family-card,.gm-size{padding:20px;border:1px solid var(--line);border-radius:16px}.gm-family-card strong,.gm-size strong{display:block;font-size:21px}.gm-family-card span,.gm-size span{display:block;margin:4px 0 9px;color:var(--primary);font-weight:650}.gm-family-card p,.gm-size p{margin:0;color:var(--text-2);font-size:14px;line-height:1.5}.gm-family-card small{display:block;margin-top:10px;color:var(--text-2)}
    .gm-checklist{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0 0;padding:0;list-style:none}.gm-checklist li{padding:18px;border-radius:14px;background:#f7f7f7}.gm-checklist strong{display:block;margin-bottom:4px}.gm-note{margin-top:32px;padding:20px;border-left:4px solid #df4145;background:#fff5f5;line-height:1.55}.gm-links{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.gm-links a{display:inline-flex;padding:11px 17px;border:1px solid #111;border-radius:999px;font-weight:650}.gm-links a:first-child{background:#111;color:#fff}
    @media(max-width:991px){.gm-picker{grid-template-columns:1fr}.gm-result{position:static}.gm-family-grid,.gm-sizes{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:575px){.gm-guide{padding-bottom:48px}.gm-panel{padding:20px}.gm-appliances,.gm-fields,.gm-checklist,.gm-family-grid,.gm-sizes{grid-template-columns:1fr}.gm-guide__intro{text-align:left;font-size:15px}}
</style>
@endpush

@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a><i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ \App\Support\KnowledgeBase::URL }}" class="text-caption-01 cl-text-3 link">База знаний</a><i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Подбор газового счётчика</p>
                </div>
                <h1 class="h3">Как подобрать газовый счётчик без сложных терминов</h1>
            </div>
        </div>
    </section>

    <section class="gm-guide flat-spacing">
        <div class="container">
            <p class="gm-guide__intro">Подбор работает по всей линейке БелОМО из прямого прайса производителя. Сначала показываем подходящие модели в наличии, затем остальные точные исполнения под заказ. Для заказной позиции можно сразу отправить запрос цены и срока поставки.</p>

            <div class="gm-picker" data-gm-picker>
                <div class="gm-panel">
                    <div class="gm-step">
                        <h2>1. Что будет работать на газе?</h2>
                        <p>Цифры ниже ориентировочные. Их можно заменить общим расходом из проекта или паспортов.</p>
                        <div class="gm-appliances">
                            <label class="gm-appliance"><input type="checkbox" value="1.2" data-gm-appliance><span><strong>Газовая плита</strong><small>примерно 1,2 м³/ч</small></span></label>
                            <label class="gm-appliance"><input type="checkbox" value="2.5" data-gm-appliance><span><strong>Газовая колонка</strong><small>примерно 2,5 м³/ч</small></span></label>
                            <label class="gm-appliance"><input type="checkbox" value="2.8" data-gm-appliance><span><strong>Котёл до 24 кВт</strong><small>примерно 2,8 м³/ч</small></span></label>
                            <label class="gm-appliance"><input type="checkbox" value="4" data-gm-appliance><span><strong>Котёл 25–35 кВт</strong><small>примерно 4 м³/ч</small></span></label>
                        </div>
                    </div>
                    <div class="gm-step">
                        <h2>2. Уточните подключение</h2>
                        <div class="gm-fields">
                            <div class="gm-field"><label for="gm-range">Примерный диапазон расхода</label><select id="gm-range" data-gm-range><option value="">Не знаю — выберу приборы выше</option><option value="2.5">До 2,5 м³/ч — обычно только плита</option><option value="4">До 4 м³/ч — плита или колонка</option><option value="6">До 6 м³/ч — котёл и плита</option><option value="10">До 10 м³/ч — несколько приборов</option><option value="16">До 16 м³/ч — повышенный расход по проекту</option></select></div>
                            <div class="gm-field"><label for="gm-flow">Точный расход — только если знаете</label><input id="gm-flow" type="number" min="0" max="50" step="0.01" placeholder="Можно не заполнять" data-gm-flow></div>
                            <div class="gm-field"><label for="gm-direction">Газ входит</label><select id="gm-direction" data-gm-direction><option value="">Не знаю</option><option value="left">Слева</option><option value="right">Справа</option></select></div>
                            <div class="gm-field"><label for="gm-length">Межосевое расстояние</label><select id="gm-length" data-gm-length><option value="">Не знаю</option><option value="110">110 мм</option><option value="200">200 мм</option><option value="250">250 мм</option></select></div>
                            <div class="gm-field"><label for="gm-place">Место установки</label><select id="gm-place" data-gm-place><option value="warm">Отапливаемое помещение</option><option value="cold">Холодное помещение или улица</option></select></div>
                            <div class="gm-field"><label for="gm-tech">Какой тип предпочитаете?</label><select id="gm-tech" data-gm-tech><option value="">Показать оба варианта</option><option value="diaphragm">Обычный диафрагменный</option><option value="ultrasonic">Современный ультразвуковой</option></select></div>
                            <div class="gm-field"><label for="gm-thermal">Нужен термокомпенсатор?</label><select id="gm-thermal" data-gm-thermal><option value="">Не знаю — показать оба варианта</option><option value="yes">Да, с термокомпенсатором</option><option value="no">Нет, без термокомпенсатора</option></select></div>
                        </div>
                    </div>
                    <button type="button" class="gm-calculate" data-gm-calculate>Подобрать счётчик</button>
                </div>

                <aside class="gm-panel gm-result" aria-live="polite">
                    <div class="gm-result__empty" data-gm-empty><div><span>↗</span><strong>Здесь появится результат</strong><p>Отметьте приборы выше или выберите понятный диапазон расхода.</p></div></div>
                    <div hidden data-gm-output><div class="gm-result__summary"><small data-gm-total-label>Суммарный расход</small><strong data-gm-total></strong><span data-gm-size></span><em data-gm-family-count></em></div><div class="gm-result__models" data-gm-models></div><p class="gm-result__warning" data-gm-warning></p></div>
                </aside>
            </div>

            <section class="gm-section">
                <h2>{{ count($families) }} серий БелОМО в полном подборе</h2>
                <p class="gm-section__lead">Подборщик использует всю линейку из прямого прайса БелОМО и переводит параметры покупателя в точный артикул. Наличие всегда получает первый приоритет.</p>
                <div class="gm-family-grid">
                    @foreach ($families as $family)
                        <article class="gm-family-card">
                            <strong>{{ $family['label'] }}</strong>
                            <span>до {{ rtrim(rtrim(number_format($family['max_flow'], 1, ',', ''), '0'), ',') }} м³/ч</span>
                            <p>{{ $family['technology_label'] }}. Подходит: {{ $family['purpose'] }}.</p>
                            <small>{{ $family['executions'] > 1 ? $family['executions'].' исполнения в спецификации' : '1 исполнение в спецификации' }}</small>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="gm-section">
                <h2>Что означают G1,6, G2,5, G4 и G6</h2>
                <p class="gm-section__lead">Это пропускная способность счётчика. Сначала складывают максимальный расход всех приборов, которые могут работать одновременно, затем выбирают счётчик, способный пропустить этот объём.</p>
                <div class="gm-sizes">
                    @foreach ($sizes as $size => $data)
                        <article class="gm-size"><strong>{{ $size }}</strong><span>до {{ rtrim(rtrim(number_format($data['max'], 1, ',', ''), '0'), ',') }} м³/ч</span><p>{{ $data['purpose'] }}.</p></article>
                    @endforeach
                </div>
            </section>

            <section class="gm-section">
                <h2>Четыре проверки перед заказом</h2>
                <ul class="gm-checklist">
                    <li><strong>Расход</strong>Берём данные из паспортов плиты, колонки и котла. Площадь дома сама по себе расход не определяет.</li>
                    <li><strong>Левый или правый</strong>Смотрим на лицевую сторону: с какой стороны газ входит в счётчик. На корпусе также есть стрелка потока.</li>
                    <li><strong>Монтажная длина</strong>Измеряем расстояние между центрами патрубков: обычно 110, 200 или 250 мм.</li>
                    <li><strong>Температура</strong>Для холодного места рассматриваем исполнение с термокомпенсацией; допустимый диапазон обязательно сверяем по паспорту.</li>
                </ul>
                <div class="gm-note"><strong>Важно:</strong> результат подборщика — предварительная рекомендация. Окончательную модель, направление потока, резьбу и разрешённое исполнение нужно сверить с проектом и газоснабжающей организацией. Монтаж и пломбирование выполняет уполномоченный специалист.</div>
                <div class="gm-links"><a href="/schetchiki-gaza">Перейти в каталог счётчиков</a><a href="{{ BelomoGasMeterDesignations::PAGE_URL }}">Расшифровка КАТА и СКАТ</a></div>
            </section>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.querySelector('[data-gm-picker]');
    if (!root) return;
    var models = @json($models, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    var limits = [
        { size: 'G1,6', max: 2.5 }, { size: 'G2,5', max: 4 },
        { size: 'G4', max: 6 }, { size: 'G6', max: 10 }, { size: 'G10', max: 16 }
    ];
    var format = function (value) { return value.toLocaleString('ru-RU', { maximumFractionDigits: 2 }); };
    var escapeHtml = function (value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char];
        });
    };

    root.querySelector('[data-gm-calculate]').addEventListener('click', function () {
        var exact = parseFloat(root.querySelector('[data-gm-flow]').value.replace(',', '.'));
        var range = parseFloat(root.querySelector('[data-gm-range]').value);
        var applianceTotal = Array.from(root.querySelectorAll('[data-gm-appliance]:checked')).reduce(function (sum, item) { return sum + Number(item.value); }, 0);
        var usesExact = Number.isFinite(exact) && exact > 0;
        var usesRange = !usesExact && Number.isFinite(range) && range > 0;
        var total = usesExact ? exact : (usesRange ? range : applianceTotal);
        if (!total) {
            root.querySelector('[data-gm-range]').focus();
            return;
        }

        var target = limits.find(function (item) { return total <= item.max; });
        var output = root.querySelector('[data-gm-output]');
        root.querySelector('[data-gm-empty]').hidden = true;
        output.hidden = false;
        root.querySelector('[data-gm-total-label]').textContent = usesRange ? 'Выбранный диапазон' : 'Примерный суммарный расход';
        root.querySelector('[data-gm-total]').textContent = (usesRange ? 'до ' : '') + format(total) + ' м³/ч';

        if (!target) {
            root.querySelector('[data-gm-size]').textContent = 'Нужен класс выше G6 — требуется инженерный подбор.';
            root.querySelector('[data-gm-models]').innerHTML = '';
            root.querySelector('[data-gm-warning]').textContent = 'При расходе свыше 10 м³/ч обратитесь к специалисту: важны давление, диаметр трубы и проект.';
            return;
        }

        var direction = root.querySelector('[data-gm-direction]').value;
        var length = root.querySelector('[data-gm-length]').value;
        var technology = root.querySelector('[data-gm-tech]').value;
        var thermal = root.querySelector('[data-gm-thermal]').value;
        var cold = root.querySelector('[data-gm-place]').value === 'cold';
        var nearLimit = !usesRange && total > target.max * .9;
        var exactMatches = models.filter(function (model) {
            return model.size === target.size
                && (!direction || model.technology === 'ultrasonic' || model.direction === direction)
                && (!length || model.technology === 'ultrasonic' || model.length === Number(length))
                && (!technology || model.technology === technology)
                && (!thermal || Boolean(model.thermal) === (thermal === 'yes'));
        });
        var approximate = exactMatches.length === 0;
        var matches = (approximate ? models.filter(function (model) {
            return model.size === target.size
                && (!technology || model.technology === technology)
                && (!thermal || Boolean(model.thermal) === (thermal === 'yes'));
        }) : exactMatches).sort(function (a, b) {
            var isSgmnG4 = function (model) { return String(model.name || '').toUpperCase().includes('СГМН-1-1-1-G4'); };
            var sgmnFitsRequest = target.size === 'G4'
                && (!technology || technology === 'diaphragm')
                && (!direction || direction === 'left')
                && (!length || length === '250');
            if (sgmnFitsRequest && isSgmnG4(a) !== isSgmnG4(b)) return isSgmnG4(a) ? -1 : 1;
            var score = function (model) {
                return (direction && model.direction === direction ? 4 : 0)
                    + (length && model.length === Number(length) ? 4 : 0)
                    + (technology && model.technology === technology ? 3 : 0)
                    + (thermal && Boolean(model.thermal) === (thermal === 'yes') ? 5 : 0)
                    + (cold && model.thermal ? 2 : 0);
            };
            if (Boolean(a.stock_priority) !== Boolean(b.stock_priority)) return a.stock_priority ? -1 : 1;
            if (approximate && score(a) !== score(b)) return score(b) - score(a);
            if (Boolean(a.request_only) !== Boolean(b.request_only)) return a.request_only ? 1 : -1;
            if (cold && Boolean(a.thermal) !== Boolean(b.thermal)) return a.thermal ? -1 : 1;
            if (Boolean(a.buyable) !== Boolean(b.buyable)) return a.buyable ? -1 : 1;
            if ((a.availability_status === 'in_stock') !== (b.availability_status === 'in_stock')) return a.availability_status === 'in_stock' ? -1 : 1;
            return (a.price || Number.MAX_SAFE_INTEGER) - (b.price || Number.MAX_SAFE_INTEGER);
        });

        root.querySelector('[data-gm-size]').textContent = 'Предварительно подходит ' + target.size + ' (до ' + format(target.max) + ' м³/ч).';
        var familyCount = new Set(matches.map(function (model) { return model.family_key; })).size;
        root.querySelector('[data-gm-family-count]').textContent = familyCount
            ? (approximate ? 'Показываем ближайшие модели по расходу. В наличии идут первыми, остальные доступны под заказ.' : 'Подходящих серий: ' + familyCount + '. В наличии идут первыми, затем вся линейка под заказ.')
            : 'Точного исполнения в программе не найдено.';
        var list = root.querySelector('[data-gm-models]');
        list.innerHTML = '';
        matches.forEach(function (model, index) {
            var card = document.createElement('article');
            card.className = 'gm-result__model' + (index === 0 ? ' gm-result__model--best' : '');
            var isSgmnG4 = String(model.name || '').toUpperCase().includes('СГМН-1-1-1-G4');
            var differences = [];
            if (approximate && direction && model.technology !== 'ultrasonic' && model.direction !== direction) differences.push('сторону входа газа');
            if (approximate && length && model.technology !== 'ultrasonic' && model.length !== Number(length)) differences.push('межосевое расстояние');
            card.innerHTML = (model.stock_priority ? '<span class="gm-result__badge">В наличии · показываем первым</span>' : (isSgmnG4 ? '<span class="gm-result__badge" style="background:#777">Популярный G4 · под заказ</span>' : '<span class="gm-result__badge" style="background:#777">Под заказ</span>'))
                + '<div class="gm-result__family">Тип: ' + escapeHtml(model.family_label) + '</div>'
                + '<strong>' + escapeHtml(model.name) + '</strong>'
                + '<p>Артикул ' + escapeHtml(model.article_label || model.article) + ' · ' + escapeHtml(model.technology_label) + ' · ' + escapeHtml(model.direction_label) + (model.length ? ' · L=' + model.length + ' мм' : '') + (model.thermal ? ' · с термокомпенсатором' : ' · без термокомпенсатора') + '</p>'
                + (approximate ? '<p><strong>Предварительный вариант.</strong> ' + (differences.length ? 'Нужно уточнить ' + escapeHtml(differences.join(' и ')) + '.' : 'Сверьте подключение перед заказом.') + '</p>' : '')
                + '<div class="gm-result__commercial"><span>' + escapeHtml(model.availability_label) + '</span><b>' + escapeHtml(model.price_label) + '</b></div>'
                + (model.request_only
                    ? '<a href="#ask" data-bs-toggle="modal" class="btn-product-availability-request" data-product-id="' + escapeHtml(model.product_id || '') + '" data-product-name="' + escapeHtml(model.name + ' · артикул ' + (model.article_label || model.article)) + '" data-product-url="' + escapeHtml(window.location.href) + '">Запросить цену и срок →</a>'
                    : '<a href="' + escapeHtml(model.url) + '">Выбрать и купить →</a>')
                + (model.technical_url ? '<a class="gm-result__tech" href="' + escapeHtml(model.technical_url) + '">Проверить обозначение КАТА/СКАТ</a>' : '');
            list.appendChild(card);
        });
        if (!matches.length) list.innerHTML = '<article class="gm-result__model"><strong>Для этого диапазона нужна модель под заказ</strong><p>Покажем доступные исполнения в каталоге, а подключение можно уточнить перед оформлением.</p><a href="/schetchiki-gaza">Открыть весь каталог →</a></article>';
        var warnings = [];
        if (nearLimit) warnings.push('Расход близок к пределу ' + target.size + ': проверьте паспортные значения, возможно потребуется следующий класс.');
        if (!direction) warnings.push('Если выбираете диафрагменный счётчик, посмотрите на старом приборе или трубе, с какой стороны входит газ.');
        if (!length) warnings.push('Для замены измерьте расстояние между центрами патрубков.');
        if (!thermal) warnings.push('Термокомпенсатор нужен, если по проекту требуется учитывать изменение объёма газа при перепадах температуры. Если не уверены — покажите паспорт старого счётчика специалисту.');
        if (cold && thermal === 'no') warnings.push('Для холодного места вариант без термокомпенсатора выбирайте только после проверки паспорта и проекта.');
        if (cold) warnings.push('Для холодного места обязательно сверьте допустимую температуру конкретного исполнения с паспортом и проектом.');
        warnings.push('Перед заказом сверьте резьбу и разрешённое исполнение с проектом газоснабжения.');
        root.querySelector('[data-gm-warning]').textContent = warnings.join(' ');
        output.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
})();
</script>
@endpush
