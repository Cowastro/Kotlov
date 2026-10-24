@extends('layouts.amerce')

@section('title', 'Подбор дымохода из нержавеющей стали | KOTLOV.BY')
@section('meta_description', 'Пошаговый подбор дымохода по диаметру, марке стали, схеме и высоте. Готовый список комплектующих и скидка 10% при покупке с печью, камином или котлом.')

@section('content')
<main class="chimney-selector-page">
    <section class="cs-hero">
        <div class="container">
            <div class="cs-hero__grid">
                <div>
                    <span class="cs-kicker">Подбор за несколько минут</span>
                    <h1>Соберите дымоход под своё оборудование</h1>
                    <p>Укажите диаметр патрубка, исполнение и схему. Подборщик в первую очередь соберёт систему «{{ $preferredBrandName }}», найдёт совместимые позиции и рассчитает предварительную стоимость.</p>
                    @if ($equipment)
                        <div class="cs-equipment"><strong>Выбрано оборудование:</strong> {{ $equipment->name }}@if($defaultDiameter) · патрубок {{ $defaultDiameter }} мм @endif</div>
                    @endif
                </div>
                <div class="cs-discount">
                    <strong>−{{ number_format($discountPercent, 0) }}%</strong>
                    <span>на весь дымоход при покупке с печью, камином или котлом</span>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="cs-layout">
                <form class="cs-form" data-cs-form>
                    <div class="cs-step"><span>01</span><div><h2>Диаметр дымохода</h2><p>Он должен совпадать с диаметром выходного патрубка оборудования.</p></div></div>
                    <div class="cs-options" data-choice="diameter">
                        @foreach (['100','110','115','120','130','150','160','180','200'] as $diameter)
                            <label><input type="radio" name="diameter" value="{{ $diameter }}" @checked($defaultDiameter === $diameter || (!$defaultDiameter && $diameter === '115'))><span>Ø {{ $diameter }} мм</span></label>
                        @endforeach
                    </div>

                    <div class="cs-step"><span>02</span><div><h2>Исполнение и сталь</h2><p>Основная система — «{{ $preferredBrandName }}». Обычно у печи ставится моно, а наружная трасса собирается из утеплённых элементов.</p></div></div>
                    <div class="cs-fields">
                        <label>Состав системы<select name="system"><option value="combined">Моно + утеплённый (рекомендуется)</option><option value="sandwich">Полностью утеплённый</option><option value="mono">Полностью моно</option></select></label>
                        <label>Марка стали<select name="steel"><option value="">Подобрать из доступных</option><option value="430" selected>AISI 430</option><option value="304">AISI 304</option><option value="316">AISI 316</option><option value="321">AISI 321</option></select></label>
                        <label>Толщина стали трубы<select name="thickness"><option value="">Подобрать из доступных</option><option value="0.5">0,5 мм</option><option value="0.8" selected>0,8 мм</option></select></label>
                        <label>Материал моно-участка<select name="mono_material" data-mono-material><option value="stainless" selected>Нержавеющая сталь «Теплов и Сухов»</option><option value="mc_black" data-mc-black-option>Окрашенная система «Теплов и Сухов» MC Black</option><option value="black_darco" data-darco-option>Чёрная сталь DARCO, 2 мм</option></select></label>
                        <label>Длина моно-участка<select name="mono_length"><option value="0.25">0,25 м</option><option value="0.5">0,5 м</option><option value="0.75">0,75 м</option><option value="1" selected>1 м</option><option value="1.25">1,25 м</option><option value="1.5">1,5 м</option><option value="1.75">1,75 м</option><option value="2">2 м</option></select></label>
                        <label>Адаптер на печь<select name="stove_adapter"><option value="yes" selected>Нужен</option><option value="no">Не нужен</option></select></label>
                        <label>Диаметр трассы после адаптера<select name="chimney_diameter" data-chimney-diameter></select></label>
                    </div>
                    <p class="cs-field-note">Складские диаметры трассы: Ø120, Ø150, Ø180 и Ø200. Для патрубка Ø130 можно выбрать адаптер на Ø120 или Ø150.</p>

                    <div class="cs-step"><span>03</span><div><h2>Схема и высота</h2><p>Выберите основные элементы трассы. Точный состав проверит специалист.</p></div></div>
                    <div class="cs-fields">
                        <label>Схема<select name="scheme"><option value="straight">Прямая через кровлю</option><option value="tee">Через стену с тройником</option><option value="elbows">Со смещением и коленами</option></select></label>
                        <label>Угол колен для смещения<select name="elbow_angle"><option value="45" selected>2 × 45°</option><option value="90">1 × 90°</option></select></label>
                        <label>Ориентировочная высота<select name="height"><option value="3">3 м</option><option value="4">4 м</option><option value="5" selected>5 м</option><option value="6">6 м</option><option value="7">7 м</option><option value="8">8 м</option></select></label>
                        <label>Шибер<select name="damper"><option value="no" selected>Не нужен</option><option value="yes">Нужен</option></select></label>
                        <label>Проходы через перекрытие (ППУ)<select name="ceiling_passes"><option value="0">Нет</option><option value="1" selected>1 проход</option><option value="2">2 прохода</option></select></label>
                        <label>Обычные соединительные хомуты<select name="clamps"><option value="auto" selected>Рассчитать по высоте</option><option value="0">Не нужны</option><option value="1">1 шт.</option><option value="2">2 шт.</option><option value="3">3 шт.</option><option value="4">4 шт.</option><option value="5">5 шт.</option><option value="6">6 шт.</option></select></label>
                        <label>Растяжки для высокой мачты<select name="guy_wires"><option value="no" selected>Не нужны</option><option value="yes">Нужны</option></select></label>
                        <label>Зонт<select name="umbrella"><option value="yes" selected>Нужен</option><option value="no">Не нужен</option></select></label>
                    </div>
                    <button type="submit" class="tf-btn animate-btn w-100">Показать комплект</button>
                    <p class="cs-safety">Результат предварительный. Перед покупкой менеджер проверит высоту, проходы перекрытий, пожарные отступы, тягу и условия монтажа.</p>
                </form>

                <aside class="cs-result" data-cs-result aria-live="polite">
                    <div class="cs-result__empty"><span class="icon icon-CheckCircle"></span><h2>Комплект появится здесь</h2><p>Заполните параметры и нажмите «Показать комплект».</p></div>
                </aside>
            </div>
        </div>
    </section>

    <section class="cs-manager" id="manager-check">
        <div class="container"><div class="cs-manager__box">
            <div><span class="cs-kicker">Проверка специалистом</span><h2>Передайте схему менеджеру</h2><p>Проверим совместимость, доберём недостающие узлы и уточним наличие.</p></div>
            <form action="{{ route('ask.store') }}" method="POST" data-cs-manager-form>@csrf<x-form-protection />
                <input type="hidden" name="name" value="Подбор дымохода">
                <input type="hidden" name="source" value="chimney_selector">
                <input type="hidden" name="product_url" value="{{ url()->current() }}">
                <input type="hidden" name="message" value="Нужна проверка комплекта дымохода" data-cs-message>
                <input type="tel" name="phone" placeholder="+375 (XX) XXX-XX-XX" required>
                <button class="tf-btn btn-white">Отправить на проверку</button>
            </form>
        </div></div>
    </section>
</main>
@endsection

@push('styles')
<style>
.chimney-selector-page{--cs-red:#e34234;--cs-dark:#111;--cs-line:#e6e6e3;background:#fafaf8}.cs-hero{padding:70px 0;background:#111;color:#fff}.cs-hero__grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:48px;align-items:center}.cs-hero h1{max-width:780px;margin:12px 0 16px;color:#fff;font-size:clamp(38px,5vw,68px);line-height:1.02}.cs-hero p{max-width:700px;margin:0;color:#c8c8c8;font-size:18px;line-height:1.6}.cs-kicker{color:#ff7065;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.cs-equipment{margin-top:24px;padding:15px 18px;border:1px solid #383838;border-radius:14px;background:#1b1b1b}.cs-discount{display:flex;flex-direction:column;padding:30px;border-radius:24px;background:var(--cs-red)}.cs-discount strong{font-size:58px;line-height:1}.cs-discount span{margin-top:10px;line-height:1.4}.cs-layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(340px,.85fr);gap:32px;align-items:start}.cs-form,.cs-result{padding:30px;border:1px solid var(--cs-line);border-radius:24px;background:#fff}.cs-result{position:sticky;top:110px;min-height:350px}.cs-step{display:flex;gap:16px;margin:0 0 18px}.cs-step:not(:first-child){margin-top:34px;padding-top:28px;border-top:1px solid var(--cs-line)}.cs-step>span{display:grid;flex:0 0 38px;height:38px;place-items:center;border-radius:50%;background:#111;color:#fff;font-size:12px;font-weight:700}.cs-step h2{margin:0 0 4px;font-size:23px}.cs-step p{margin:0;color:#777;font-size:14px}.cs-options{display:flex;flex-wrap:wrap;gap:8px}.cs-options input{position:absolute;opacity:0}.cs-options span{display:block;padding:11px 14px;border:1px solid var(--cs-line);border-radius:10px;cursor:pointer}.cs-options input:checked+span{border-color:#111;background:#111;color:#fff}.cs-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}.cs-fields label{font-size:13px;font-weight:700}.cs-fields select{width:100%;height:50px;margin-top:7px;padding:0 14px;border:1px solid var(--cs-line);border-radius:10px;background:#fff}.cs-field-note{margin:0 0 26px;color:#777;font-size:12px}.cs-safety{margin:14px 0 0;color:#777;font-size:12px;line-height:1.5}.cs-result__empty{padding:60px 15px;text-align:center;color:#777}.cs-result__empty span{font-size:42px}.cs-result h2{font-size:24px}.cs-result__list{display:grid;gap:12px;margin:20px 0}.cs-result__item{display:grid;grid-template-columns:58px 1fr auto;gap:12px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--cs-line)}.cs-result__item img{width:58px;height:58px;object-fit:contain}.cs-result__item a{color:#111;font-size:13px;font-weight:700}.cs-result__item small{display:block;color:#777}.cs-result__controls{display:flex;align-items:center;gap:4px;margin-top:8px}.cs-result__quantity{min-width:28px;text-align:center;font-size:13px;font-weight:800}.cs-result__control{display:grid;width:30px;height:30px;padding:0;place-items:center;border:1px solid #ddd;border-radius:8px;background:#fff;color:#111;font-size:18px;line-height:1}.cs-result__control:hover{border-color:#111}.cs-result__remove{margin-left:5px;padding:5px;border:0;background:transparent;color:#777;font-size:12px;text-decoration:underline}.cs-result__price{font-weight:700;white-space:nowrap}.cs-result__totals{padding:18px;border-radius:14px;background:#f5f5f2}.cs-result__row{display:flex;justify-content:space-between;gap:12px;margin-bottom:8px}.cs-result__row--total{margin:14px 0 0;padding-top:14px;border-top:1px solid #ddd;font-size:18px;font-weight:800}.cs-result__saving{color:var(--cs-red);font-weight:700}.cs-result__actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.cs-manager{padding:55px 0;background:#eeeae4}.cs-manager__box{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}.cs-manager h2{margin:8px 0}.cs-manager p{margin:0;color:#666}.cs-manager form{display:grid;grid-template-columns:1fr auto;gap:10px}.cs-manager input{height:54px;padding:0 16px;border:1px solid #ccc;border-radius:10px;background:#fff}@media(max-width:991px){.cs-hero__grid,.cs-layout,.cs-manager__box{grid-template-columns:1fr}.cs-discount{max-width:330px}.cs-result{position:static}.cs-manager form{grid-template-columns:1fr}}@media(max-width:575px){.cs-hero{padding:42px 0}.cs-hero h1{font-size:38px}.cs-form,.cs-result{padding:20px;border-radius:18px}.cs-fields,.cs-result__actions{grid-template-columns:1fr}.cs-result__item{grid-template-columns:48px 1fr}.cs-result__price{grid-column:2}.cs-options span{padding:9px 11px;font-size:13px}}
.cs-addon{margin-top:14px}.cs-addon__toggle{width:100%;height:48px;border:1px dashed #aaa;border-radius:12px;background:#fff;font-weight:800}.cs-addon__panel{margin-top:10px;padding:12px;border:1px solid var(--cs-line);border-radius:14px;background:#fafaf8}.cs-addon__panel[hidden]{display:none}.cs-addon__search{width:100%;height:46px;padding:0 14px;border:1px solid #ccc;border-radius:10px;background:#fff}.cs-addon__results{display:grid;gap:7px;margin-top:10px}.cs-addon__result{display:grid;grid-template-columns:42px 1fr auto;gap:9px;align-items:center;padding:8px;border:1px solid #e3e3df;border-radius:10px;background:#fff;text-align:left}.cs-addon__result img{width:42px;height:42px;object-fit:contain}.cs-addon__result strong{display:block;font-size:12px;line-height:1.25}.cs-addon__result small{color:#777}.cs-addon__add{width:32px;height:32px;border:0;border-radius:50%;background:#111;color:#fff;font-size:20px}.cs-addon__empty{padding:10px;color:#777;font-size:12px;text-align:center}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var catalog = @json($catalog);
    var unlocked = @json($discountUnlocked);
    var discount = {{ (float) $discountPercent }};
    var equipmentId = @json(($equipmentInCart ?? false) ? null : $equipment?->id);
    var form = document.querySelector('[data-cs-form]');
    var result = document.querySelector('[data-cs-result]');
    var message = document.querySelector('[data-cs-message]');
    if (!form || !result) return;

    var standardDiameters = {
        '100':['120'], '110':['120'], '115':['120'], '120':['120'],
        '130':['120','150'], '150':['150'], '160':['180'], '180':['180'], '200':['200']
    };
    var preferredDiameter = {'100':'120', '110':'120', '115':'120', '120':'120', '130':'150', '150':'150', '160':'180', '180':'180', '200':'200'};
    var chimneyDiameterSelect = form.querySelector('[data-chimney-diameter]');
    var monoMaterialSelect = form.querySelector('[data-mono-material]');
    function syncMonoMaterial() {
        var darcoOption = monoMaterialSelect.querySelector('[data-darco-option]');
        var mcBlackOption = monoMaterialSelect.querySelector('[data-mc-black-option]');
        var selected = form.querySelector('input[name="diameter"]:checked');
        var inlet = selected ? selected.value : '115';
        var darcoAvailable = ['120','150'].includes(chimneyDiameterSelect.value);
        var mcBlackDiameter = inlet === '115' ? '115' : chimneyDiameterSelect.value;
        var mcBlackAvailable = ['115','150'].includes(mcBlackDiameter) && catalog.some(function (product) {
            return product.material === 'mc_black' && product.kind === 'pipe' && product.system === 'mono' && product.diameter === mcBlackDiameter;
        });
        darcoOption.disabled = !darcoAvailable;
        mcBlackOption.disabled = !mcBlackAvailable;
        if ((!darcoAvailable && monoMaterialSelect.value === 'black_darco') || (!mcBlackAvailable && monoMaterialSelect.value === 'mc_black')) monoMaterialSelect.value = 'stainless';
    }
    function syncChimneyDiameters() {
        var selected = form.querySelector('input[name="diameter"]:checked');
        var inlet = selected ? selected.value : '115';
        var previous = chimneyDiameterSelect.value;
        var options = standardDiameters[inlet] || [inlet];
        chimneyDiameterSelect.innerHTML = options.map(function (diameter) { return '<option value="' + diameter + '">Ø ' + diameter + ' мм</option>'; }).join('');
        chimneyDiameterSelect.value = options.includes(previous) ? previous : (preferredDiameter[inlet] || options[0]);
        syncMonoMaterial();
    }
    form.querySelectorAll('input[name="diameter"]').forEach(function (input) { input.addEventListener('change', syncChimneyDiameters); });
    chimneyDiameterSelect.addEventListener('change', syncMonoMaterial);
    syncChimneyDiameters();

    function money(value) {
        return Number(value).toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' BYN';
    }
    function esc(value) {
        var node = document.createElement('div');
        node.textContent = String(value);
        return node.innerHTML;
    }
    function normalizeSearch(value) {
        return String(value || '').toLocaleLowerCase('ru-RU').replace(/ё/g, 'е').replace(/[^a-zа-я0-9]+/gi, ' ').trim();
    }
    function findAddons(query, settings, rows) {
        var normalized = normalizeSearch(query);
        var tokens = normalized.split(' ').filter(Boolean);
        var aliases = {
            'теплообменник':['теплообменник','регистр','теплосъемник'],
            'теплосъемник':['теплосъемник','теплообменник','регистр'],
            'бак':['бак','емкость'],
            'шибер':['шибер','задвижка']
        };
        return catalog.filter(function (product) {
            if (!product.preferred) return false;
            if (product.diameter && product.diameter !== settings.diameter && product.diameter !== settings.chimneyDiameter) return false;
            var haystack = normalizeSearch(product.name + ' ' + (product.category || ''));
            if (!tokens.length) return /теплообмен|регистр|теплосъем|бак/.test(haystack);
            return tokens.every(function (token) {
                return (aliases[token] || [token]).some(function (variant) { return haystack.includes(variant); });
            });
        }).sort(function (a, b) {
            var aAdded = rows.some(function (row) { return row.product.id === a.id; }) ? 1 : 0;
            var bAdded = rows.some(function (row) { return row.product.id === b.id; }) ? 1 : 0;
            return aAdded - bAdded || Number(b.preferred) - Number(a.preferred) || a.name.localeCompare(b.name, 'ru');
        }).slice(0, 8);
    }
    function choose(need, settings, used) {
        function matches(product, ignoreSteel, allowMissingDiameter) {
            var targetDiameter = need.targetDiameter || (need.diameterMode === 'outer'
                ? settings.outerDiameter
                : (need.kind === 'adapter' ? settings.diameter : settings.chimneyDiameter));
            var productDiameter = need.diameterMode === 'outer' ? (product.outer_diameter || product.diameter) : product.diameter;
            var adapterPairMatches = need.kind === 'adapter' && need.toDiameter
                ? Array.isArray(product.diameter_pair) && product.diameter_pair.includes(settings.diameter) && product.diameter_pair.includes(need.toDiameter)
                : null;
            var diameterRangeMatches = need.kind === 'ceiling_pass' && targetDiameter && Array.isArray(product.diameter_pair)
                ? Number(targetDiameter) >= Number(product.diameter_pair[0]) && Number(targetDiameter) <= Number(product.diameter_pair[1])
                : false;
            return product.kind === need.kind && !used[product.id]
                && (adapterPairMatches !== null ? adapterPairMatches : (diameterRangeMatches || !targetDiameter || productDiameter === targetDiameter || (allowMissingDiameter && !productDiameter && !need.exactDiameter)))
                && (need.system === 'any' || product.system === need.system)
                && (need.system !== 'sandwich' || !settings.outerDiameter || product.outer_diameter === settings.outerDiameter)
                && (!need.brandSlug || product.brand_slug === need.brandSlug)
                && (!need.material || product.material === need.material)
                && (need.material === 'black_steel' || ignoreSteel || settings.steel === '' || product.steel === settings.steel || !product.steel)
                && (need.kind !== 'pipe' || (need.thickness || settings.thickness) === '' || product.thickness === (need.thickness || settings.thickness))
                && (!need.angle || product.angle === need.angle || (need.angle === '90' && product.angle === '87'))
                && (!need.lengthMm || product.length_mm === need.lengthMm);
        }
        var picked = catalog.find(function (product) { return matches(product, false, false); })
            || catalog.find(function (product) { return matches(product, false, true); });
        if (!picked && settings.steel !== '' && need.kind !== 'pipe') {
            picked = catalog.find(function (product) { return matches(product, true, false); })
                || catalog.find(function (product) { return matches(product, true, true); });
        }
        if (picked && need.kind !== 'pipe') used[picked.id] = true;
        return picked;
    }
    function addPipeNeeds(needs, length, system, label, options) {
        var quarterMetres = Math.max(1, Math.round(length * 4));
        var wholeMetres = Math.floor(quarterMetres / 4);
        var remainder = quarterMetres % 4;
        function add(lengthMm, quantity, suffix) {
            if (quantity > 0) needs.push(Object.assign({kind:'pipe', quantity:quantity, system:system, lengthMm:lengthMm, label:label + ' · ' + suffix}, options || {}));
        }
        add(1000, wholeMetres, '1 м');
        if (remainder >= 2) { add(500, 1, '0,5 м'); remainder -= 2; }
        if (remainder === 1) add(250, 1, '0,25 м');
    }
    function buildNeeds(settings) {
        var needs = [];
        var height = Math.max(1, Math.ceil(settings.height));
        var monoLength = Math.max(0.25, Number(settings.monoLength));
        var monoOptions = settings.monoMaterial === 'black_darco'
            ? {material:'black_steel', brandSlug:'darco', thickness:'2'}
            : (settings.monoMaterial === 'mc_black'
                ? {material:'mc_black', brandSlug:'teplov-i-suhov', targetDiameter:settings.diameter === '115' ? '115' : settings.chimneyDiameter}
                : {material:'stainless'});
        var monoSectionLabel = settings.monoMaterial === 'black_darco'
            ? 'Чёрный участок DARCO · 2 мм'
            : (settings.monoMaterial === 'mc_black' ? 'Окрашенный участок «Теплов и Сухов» MC Black' : 'Участок от оборудования · моно');
        if (settings.stoveAdapter) needs.push({kind:'adapter', quantity:1, system:'any', toDiameter:settings.diameter === settings.chimneyDiameter ? null : settings.chimneyDiameter, label:'Адаптер на патрубок печи' + (settings.diameter === settings.chimneyDiameter ? '' : ' · ' + settings.diameter + '/' + settings.chimneyDiameter)});
        if (settings.system === 'combined') {
            addPipeNeeds(needs, monoLength, 'mono', monoSectionLabel, monoOptions);
            if (settings.damper) needs.push(Object.assign({kind:'damper', quantity:1, system:'mono', label:'Шибер'}, monoOptions));
            needs.push({kind:'transition', quantity:1, system:'any', label:'Переход моно / термо'});
            addPipeNeeds(needs, Math.max(0.5, height - monoLength), 'sandwich', 'Наружная трасса · утеплённая', {material:'stainless'});
        } else {
            var fullSystemOptions = settings.system === 'mono' ? monoOptions : {material:'stainless'};
            if (settings.damper) needs.push(Object.assign({kind:'damper', quantity:1, system:settings.system, label:'Шибер'}, fullSystemOptions));
            addPipeNeeds(needs, height, settings.system, settings.system === 'sandwich' ? 'Утеплённая трасса' : monoSectionLabel, fullSystemOptions);
        }
        if (settings.scheme === 'tee') {
            needs.push({kind:'tee', quantity:1, system:settings.system === 'mono' ? 'mono' : 'sandwich', label:'Тройник 87°'});
            needs.push({kind:'condensate', quantity:1, system:'any', label:'Ревизия / конденсатоотвод'});
        }
        if (settings.scheme === 'elbows') {
            var elbowOptions = settings.system === 'sandwich' ? {material:'stainless'} : monoOptions;
            needs.push(Object.assign({kind:'elbow', quantity:settings.elbowAngle === '45' ? 2 : 1, system:settings.system === 'sandwich' ? 'sandwich' : 'mono', angle:settings.elbowAngle, label:'Колена для смещения · ' + settings.elbowAngle + '°'}, elbowOptions));
        }
        if (settings.ceilingPasses > 0) needs.push({kind:'ceiling_pass', quantity:settings.ceilingPasses, system:'any', material:settings.system === 'mono' ? monoOptions.material : 'stainless', diameterMode:'outer', exactDiameter:true, label:'ППУ · проход через перекрытие'});
        if (settings.clamps > 0) needs.push({kind:'clamp', quantity:settings.clamps, system:'any', diameterMode:settings.system === 'mono' ? 'inner' : 'outer', exactDiameter:true, label:'Обычные соединительные хомуты'});
        if (settings.guyWires) needs.push({kind:'guy_wire', quantity:1, system:'any', diameterMode:settings.system === 'mono' ? 'inner' : 'outer', label:'Хомут для растяжек высокой мачты'});
        if (settings.umbrella) needs.push({kind:'umbrella', quantity:1, system:settings.system === 'mono' ? 'mono' : 'sandwich', material:'stainless', label:'Зонт / завершение дымохода'});
        return needs;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var data = new FormData(form);
        var height = Number(data.get('height'));
        var clampValue = data.get('clamps');
        var settings = {
            diameter:data.get('diameter'), system:data.get('system'), steel:data.get('steel'), thickness:data.get('thickness'), scheme:data.get('scheme'),
            height:height, monoLength:Number(data.get('mono_length')), monoMaterial:data.get('mono_material'), stoveAdapter:data.get('stove_adapter') === 'yes', elbowAngle:data.get('elbow_angle'),
            damper:data.get('damper') === 'yes', ceilingPasses:Number(data.get('ceiling_passes')),
            clamps:clampValue === 'auto' ? Math.max(2, Math.ceil(height / 2)) : Number(clampValue), guyWires:data.get('guy_wires') === 'yes',
            umbrella:data.get('umbrella') === 'yes', outerDiameter:null, chimneyDiameter:data.get('chimney_diameter')
        };
        if (!settings.stoveAdapter) settings.chimneyDiameter = settings.diameter;
        var needs = buildNeeds(settings);
        var used = {};
        var matchedNeeds = 0;
        var missingLabels = [];
        var rawRows = needs.map(function (need) {
            var product = choose(need, settings, used);
            var quantity = need.quantity;
            var label = need.label;
            if (!product && need.kind === 'pipe' && need.lengthMm === 1000) {
                product = choose(Object.assign({}, need, {lengthMm:500}), settings, used);
                quantity *= 2;
                label = label.replace('1 м', 'секции 0,5 м');
                if (!product) {
                    product = choose(Object.assign({}, need, {lengthMm:250}), settings, used);
                    quantity *= 2;
                    label = label.replace('секции 0,5 м', 'секции 0,25 м');
                }
            } else if (!product && need.kind === 'pipe' && need.lengthMm === 500) {
                product = choose(Object.assign({}, need, {lengthMm:250}), settings, used);
                quantity *= 2;
                label = label.replace('0,5 м', 'секции 0,25 м');
            }
            if (product && product.system === 'sandwich' && product.outer_diameter && ['transition','pipe'].includes(need.kind)) settings.outerDiameter = product.outer_diameter;
            if (product) matchedNeeds++;
            else missingLabels.push(label);
            return product ? {product:product, quantity:quantity, label:label} : null;
        }).filter(Boolean);
        var rows = rawRows.reduce(function (resultRows, row) {
            var existing = resultRows.find(function (item) { return item.product.id === row.product.id && item.label === row.label; });
            if (existing) existing.quantity += row.quantity;
            else resultRows.push(row);
            return resultRows;
        }, []);
        if (!rows.length) {
            result.innerHTML = '<div class="cs-result__empty"><h2>Точных позиций пока не найдено</h2><p>Отправьте параметры менеджеру — он соберёт комплект вручную.</p><a href="#manager-check" class="tf-btn animate-btn">Передать менеджеру</a></div>';
            return;
        }
        var missing = missingLabels.length;
        var monoLabel = settings.monoMaterial === 'black_darco'
            ? 'чёрный DARCO 2 мм'
            : (settings.monoMaterial === 'mc_black' ? 'окрашенный «Теплов и Сухов» MC Black' : 'моно из нержавеющей стали');
        var systemLabel = settings.system === 'combined' ? monoLabel + ' ' + settings.monoLength.toLocaleString('ru-RU') + ' м + утеплённый' : (settings.system === 'sandwich' ? 'полностью утеплённый' : 'полностью ' + monoLabel);
        var diameterLabel = settings.diameter === settings.chimneyDiameter ? 'Ø ' + settings.diameter + ' мм' : 'патрубок Ø ' + settings.diameter + ' → дымоход Ø ' + settings.chimneyDiameter + ' мм';
        function renderBundle() {
            if (!rows.length) {
                message.value = 'Подбор дымохода очищен пользователем.';
                result.innerHTML = '<div class="cs-result__empty"><h2>В комплекте нет позиций</h2><p>Измените параметры или снова нажмите «Показать комплект».</p></div>';
                return;
            }
            var subtotal = rows.reduce(function (sum, row) { return sum + row.product.price * row.quantity; }, 0);
            var eligibleSubtotal = rows.reduce(function (sum, row) { return sum + (row.product.discount_eligible ? row.product.price * row.quantity : 0); }, 0);
            var saving = unlocked ? eligibleSubtotal * discount / 100 : 0;
            var total = subtotal - saving;
            var names = rows.map(function (row) { return row.label + ': ' + row.quantity + ' × ' + row.product.name; }).join('; ');
            message.value = 'Подбор дымохода: ' + diameterLabel + ', ' + systemLabel + ', сталь ' + (settings.steel || 'из доступных')
                + ', толщина трубы ' + (settings.thickness ? settings.thickness.replace('.', ',') + ' мм' : 'из доступных')
                + ', схема ' + settings.scheme + (settings.scheme === 'elbows' ? ', колена ' + settings.elbowAngle + '°' : '') + ', высота ' + settings.height + ' м, адаптер ' + (settings.stoveAdapter ? 'да' : 'нет')
                + ', шибер ' + (settings.damper ? 'да' : 'нет') + ', ППУ ' + settings.ceilingPasses + ', хомуты ' + settings.clamps
                + ', растяжки ' + (settings.guyWires ? 'да' : 'нет') + ', зонт ' + (settings.umbrella ? 'да' : 'нет') + '. Скорректированный состав: ' + names;
            result.innerHTML = '<h2>Предварительный комплект</h2><p class="text-caption-01 cl-text-2">' + diameterLabel + ' · ' + systemLabel + ' · ' + settings.height + ' м</p>'
                + (missing ? '<p class="cs-safety"><strong>Нужно подобрать вручную:</strong> ' + esc(Array.from(new Set(missingLabels)).join(', ')) + '. Менеджер проверит эти позиции.</p>' : '')
                + '<div class="cs-result__list">' + rows.map(function (row, index) {
                    return '<div class="cs-result__item"><img src="' + esc(row.product.image) + '" alt=""><div><a href="' + esc(row.product.url) + '">' + esc(row.product.name)
                        + '</a><small>' + esc(row.label) + '</small><div class="cs-result__controls"><button type="button" class="cs-result__control" data-row-decrease="' + index + '" aria-label="Уменьшить количество">−</button>'
                        + '<span class="cs-result__quantity" aria-label="Количество">' + row.quantity + '</span><button type="button" class="cs-result__control" data-row-increase="' + index + '" aria-label="Увеличить количество"' + (row.quantity >= 99 ? ' disabled' : '') + '>+</button>'
                        + '<button type="button" class="cs-result__remove" data-row-remove="' + index + '">Удалить</button></div></div><div class="cs-result__price">' + money(row.product.price * row.quantity) + '</div></div>';
                }).join('') + '</div><div class="cs-result__totals"><div class="cs-result__row"><span>Стоимость товаров</span><strong>' + money(subtotal) + '</strong></div>'
                + (unlocked ? '<div class="cs-result__row cs-result__saving"><span>Скидка на дымоход −' + discount + '%</span><strong>−' + money(saving) + '</strong></div>' : '<div class="cs-result__row cs-result__saving"><span>Добавьте печь, камин или котёл — получите −' + discount + '%</span></div>')
                + '<div class="cs-result__row cs-result__row--total"><span>Итого</span><span>' + money(total) + '</span></div></div><div class="cs-result__actions"><button type="button" class="tf-btn animate-btn" data-add-kit>'
                + (missing ? 'Добавить найденные позиции' : 'Добавить комплект') + '</button><a href="#manager-check" class="tf-btn btn-white btn-stroke">Проверить с менеджером</a></div>'
                + '<div class="cs-addon"><button type="button" class="cs-addon__toggle" data-addon-toggle>+ Добавить элемент «Теплов и Сухов»</button><div class="cs-addon__panel" data-addon-panel hidden>'
                + '<input type="search" class="cs-addon__search" data-addon-search placeholder="Умный поиск: теплообменник, бак, шибер…" autocomplete="off"><div class="cs-addon__results" data-addon-results></div></div></div>';

            var addonPanel = result.querySelector('[data-addon-panel]');
            var addonInput = result.querySelector('[data-addon-search]');
            var addonResults = result.querySelector('[data-addon-results]');
            function renderAddonResults() {
                var found = findAddons(addonInput.value, settings, rows);
                addonResults.innerHTML = found.length ? found.map(function (product) {
                    return '<div class="cs-addon__result"><img src="' + esc(product.image) + '" alt=""><div><strong>' + esc(product.name) + '</strong><small>' + money(product.price) + '</small></div>'
                        + '<button type="button" class="cs-addon__add" data-addon-id="' + product.id + '" aria-label="Добавить элемент">+</button></div>';
                }).join('') : '<div class="cs-addon__empty">Совместимых элементов «Теплов и Сухов» не найдено.</div>';
                addonResults.querySelectorAll('[data-addon-id]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var product = catalog.find(function (item) { return item.id === Number(button.dataset.addonId) && item.preferred; });
                        if (!product) return;
                        var existing = rows.find(function (row) { return row.product.id === product.id; });
                        if (existing) existing.quantity = Math.min(99, existing.quantity + 1);
                        else rows.push({product:product, quantity:1, label:'Добавлено вручную · Теплов и Сухов'});
                        renderBundle();
                    });
                });
            }
            result.querySelector('[data-addon-toggle]').addEventListener('click', function () {
                addonPanel.hidden = !addonPanel.hidden;
                if (!addonPanel.hidden) { renderAddonResults(); addonInput.focus(); }
            });
            addonInput.addEventListener('input', renderAddonResults);

            result.querySelectorAll('[data-row-decrease]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var index = Number(button.dataset.rowDecrease);
                    if (rows[index].quantity > 1) rows[index].quantity--;
                    else rows.splice(index, 1);
                    renderBundle();
                });
            });
            result.querySelectorAll('[data-row-increase]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var index = Number(button.dataset.rowIncrease);
                    rows[index].quantity = Math.min(99, rows[index].quantity + 1);
                    renderBundle();
                });
            });
            result.querySelectorAll('[data-row-remove]').forEach(function (button) {
                button.addEventListener('click', function () {
                    rows.splice(Number(button.dataset.rowRemove), 1);
                    renderBundle();
                });
            });
            var add = result.querySelector('[data-add-kit]');
            add.addEventListener('click', function () {
                add.disabled = true;
                var csrf = document.querySelector('meta[name="csrf-token"]').content;
                var additions = rows.slice();
                if (equipmentId) additions.unshift({product:{id:equipmentId}, quantity:1});
                additions.reduce(function (chain, row) {
                    return chain.then(function () {
                        return fetch(@json(route('cart.add')), {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({product_id:row.product.id,quantity:row.quantity})})
                            .then(function (response) { if (!response.ok) throw new Error(); return response.json(); });
                    });
                }, Promise.resolve()).then(function () { window.location = @json(route('cart')); })
                    .catch(function () { add.disabled = false; add.textContent = 'Не всё добавилось — проверить корзину'; });
            });
        }
        renderBundle();
    });
})();
</script>
@endpush
