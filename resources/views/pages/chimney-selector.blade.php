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
                        <label>Марка стали<select name="steel"><option value="">Подобрать из доступных</option><option value="430">AISI 430</option><option value="304">AISI 304</option><option value="316">AISI 316</option><option value="321">AISI 321</option></select></label>
                        <label>Длина моно-участка<select name="mono_length"><option value="0.5">0,5 м</option><option value="1" selected>1 м</option><option value="1.5">1,5 м</option><option value="2">2 м</option></select></label>
                        <label>Адаптер на печь<select name="stove_adapter"><option value="yes" selected>Нужен</option><option value="no">Не нужен</option></select></label>
                    </div>

                    <div class="cs-step"><span>03</span><div><h2>Схема и высота</h2><p>Выберите основные элементы трассы. Точный состав проверит специалист.</p></div></div>
                    <div class="cs-fields">
                        <label>Схема<select name="scheme"><option value="straight">Прямая через кровлю</option><option value="tee">Через стену с тройником</option><option value="elbows">Со смещением и коленами</option></select></label>
                        <label>Ориентировочная высота<select name="height"><option value="3">3 м</option><option value="4">4 м</option><option value="5" selected>5 м</option><option value="6">6 м</option><option value="7">7 м</option><option value="8">8 м</option></select></label>
                        <label>Шибер<select name="damper"><option value="no" selected>Не нужен</option><option value="yes">Нужен</option></select></label>
                        <label>Проходы через перекрытие (ППУ)<select name="ceiling_passes"><option value="0">Нет</option><option value="1" selected>1 проход</option><option value="2">2 прохода</option></select></label>
                        <label>Хомуты<select name="clamps"><option value="auto" selected>Рассчитать по высоте</option><option value="0">Не нужны</option><option value="1">1 шт.</option><option value="2">2 шт.</option><option value="3">3 шт.</option><option value="4">4 шт.</option><option value="5">5 шт.</option><option value="6">6 шт.</option></select></label>
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
.chimney-selector-page{--cs-red:#e34234;--cs-dark:#111;--cs-line:#e6e6e3;background:#fafaf8}.cs-hero{padding:70px 0;background:#111;color:#fff}.cs-hero__grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:48px;align-items:center}.cs-hero h1{max-width:780px;margin:12px 0 16px;color:#fff;font-size:clamp(38px,5vw,68px);line-height:1.02}.cs-hero p{max-width:700px;margin:0;color:#c8c8c8;font-size:18px;line-height:1.6}.cs-kicker{color:#ff7065;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.cs-equipment{margin-top:24px;padding:15px 18px;border:1px solid #383838;border-radius:14px;background:#1b1b1b}.cs-discount{display:flex;flex-direction:column;padding:30px;border-radius:24px;background:var(--cs-red)}.cs-discount strong{font-size:58px;line-height:1}.cs-discount span{margin-top:10px;line-height:1.4}.cs-layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(340px,.85fr);gap:32px;align-items:start}.cs-form,.cs-result{padding:30px;border:1px solid var(--cs-line);border-radius:24px;background:#fff}.cs-result{position:sticky;top:110px;min-height:350px}.cs-step{display:flex;gap:16px;margin:0 0 18px}.cs-step:not(:first-child){margin-top:34px;padding-top:28px;border-top:1px solid var(--cs-line)}.cs-step>span{display:grid;flex:0 0 38px;height:38px;place-items:center;border-radius:50%;background:#111;color:#fff;font-size:12px;font-weight:700}.cs-step h2{margin:0 0 4px;font-size:23px}.cs-step p{margin:0;color:#777;font-size:14px}.cs-options{display:flex;flex-wrap:wrap;gap:8px}.cs-options input{position:absolute;opacity:0}.cs-options span{display:block;padding:11px 14px;border:1px solid var(--cs-line);border-radius:10px;cursor:pointer}.cs-options input:checked+span{border-color:#111;background:#111;color:#fff}.cs-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:26px}.cs-fields label{font-size:13px;font-weight:700}.cs-fields select{width:100%;height:50px;margin-top:7px;padding:0 14px;border:1px solid var(--cs-line);border-radius:10px;background:#fff}.cs-safety{margin:14px 0 0;color:#777;font-size:12px;line-height:1.5}.cs-result__empty{padding:60px 15px;text-align:center;color:#777}.cs-result__empty span{font-size:42px}.cs-result h2{font-size:24px}.cs-result__list{display:grid;gap:12px;margin:20px 0}.cs-result__item{display:grid;grid-template-columns:58px 1fr auto;gap:12px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--cs-line)}.cs-result__item img{width:58px;height:58px;object-fit:contain}.cs-result__item a{color:#111;font-size:13px;font-weight:700}.cs-result__item small{display:block;color:#777}.cs-result__price{font-weight:700;white-space:nowrap}.cs-result__totals{padding:18px;border-radius:14px;background:#f5f5f2}.cs-result__row{display:flex;justify-content:space-between;gap:12px;margin-bottom:8px}.cs-result__row--total{margin:14px 0 0;padding-top:14px;border-top:1px solid #ddd;font-size:18px;font-weight:800}.cs-result__saving{color:var(--cs-red);font-weight:700}.cs-result__actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.cs-manager{padding:55px 0;background:#eeeae4}.cs-manager__box{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}.cs-manager h2{margin:8px 0}.cs-manager p{margin:0;color:#666}.cs-manager form{display:grid;grid-template-columns:1fr auto;gap:10px}.cs-manager input{height:54px;padding:0 16px;border:1px solid #ccc;border-radius:10px;background:#fff}@media(max-width:991px){.cs-hero__grid,.cs-layout,.cs-manager__box{grid-template-columns:1fr}.cs-discount{max-width:330px}.cs-result{position:static}.cs-manager form{grid-template-columns:1fr}}@media(max-width:575px){.cs-hero{padding:42px 0}.cs-hero h1{font-size:38px}.cs-form,.cs-result{padding:20px;border-radius:18px}.cs-fields,.cs-result__actions{grid-template-columns:1fr}.cs-result__item{grid-template-columns:48px 1fr}.cs-result__price{grid-column:2}.cs-options span{padding:9px 11px;font-size:13px}}
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

    function money(value) {
        return Number(value).toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' BYN';
    }
    function esc(value) {
        var node = document.createElement('div');
        node.textContent = String(value);
        return node.innerHTML;
    }
    function choose(need, settings, used) {
        function matches(product, ignoreSteel) {
            var targetDiameter = need.diameterMode === 'outer' ? settings.outerDiameter : settings.diameter;
            var productDiameter = need.diameterMode === 'outer' ? (product.outer_diameter || product.diameter) : product.diameter;
            return product.kind === need.kind && !used[product.id]
                && (!targetDiameter || productDiameter === targetDiameter || !productDiameter)
                && (need.system === 'any' || product.system === need.system)
                && (ignoreSteel || settings.steel === '' || product.steel === settings.steel || !product.steel)
                && (!need.lengthMm || product.length_mm === need.lengthMm);
        }
        var picked = catalog.find(function (product) { return matches(product, false); });
        if (!picked && settings.steel !== '') picked = catalog.find(function (product) { return matches(product, true); });
        if (picked) used[picked.id] = true;
        return picked;
    }
    function addPipeNeeds(needs, length, system, label) {
        var wholeMetres = Math.floor(length);
        if (wholeMetres > 0) needs.push({kind:'pipe', quantity:wholeMetres, system:system, lengthMm:1000, label:label + ' · 1 м'});
        if (length - wholeMetres >= 0.49) needs.push({kind:'pipe', quantity:1, system:system, lengthMm:500, label:label + ' · 0,5 м'});
    }
    function buildNeeds(settings) {
        var needs = [];
        var height = Math.max(1, Math.ceil(settings.height));
        var monoLength = Math.max(0.5, Number(settings.monoLength));
        if (settings.stoveAdapter) needs.push({kind:'adapter', quantity:1, system:'any', label:'Адаптер на патрубок печи'});
        if (settings.system === 'combined') {
            addPipeNeeds(needs, monoLength, 'mono', 'Участок от оборудования · моно');
            if (settings.damper) needs.push({kind:'damper', quantity:1, system:'mono', label:'Шибер'});
            needs.push({kind:'transition', quantity:1, system:'any', label:'Переход моно / термо'});
            addPipeNeeds(needs, Math.max(0.5, height - monoLength), 'sandwich', 'Наружная трасса · утеплённая');
        } else {
            if (settings.damper) needs.push({kind:'damper', quantity:1, system:settings.system, label:'Шибер'});
            addPipeNeeds(needs, height, settings.system, settings.system === 'sandwich' ? 'Утеплённая трасса' : 'Одностенная трасса');
        }
        if (settings.scheme === 'tee') {
            needs.push({kind:'tee', quantity:1, system:settings.system === 'mono' ? 'mono' : 'sandwich', label:'Тройник 87°'});
            needs.push({kind:'condensate', quantity:1, system:'any', label:'Ревизия / конденсатоотвод'});
        }
        if (settings.scheme === 'elbows') needs.push({kind:'elbow', quantity:2, system:settings.system === 'sandwich' ? 'sandwich' : 'mono', label:'Колена для смещения'});
        if (settings.ceilingPasses > 0) needs.push({kind:'ceiling_pass', quantity:settings.ceilingPasses, system:'any', diameterMode:'outer', label:'ППУ · проход через перекрытие'});
        if (settings.clamps > 0) needs.push({kind:'clamp', quantity:settings.clamps, system:'any', diameterMode:'outer', label:'Хомуты'});
        if (settings.umbrella) needs.push({kind:'umbrella', quantity:1, system:'any', label:'Зонт'});
        return needs;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var data = new FormData(form);
        var height = Number(data.get('height'));
        var clampValue = data.get('clamps');
        var settings = {
            diameter:data.get('diameter'), system:data.get('system'), steel:data.get('steel'), scheme:data.get('scheme'),
            height:height, monoLength:Number(data.get('mono_length')), stoveAdapter:data.get('stove_adapter') === 'yes',
            damper:data.get('damper') === 'yes', ceilingPasses:Number(data.get('ceiling_passes')),
            clamps:clampValue === 'auto' ? Math.max(2, Math.ceil(height / 2)) : Number(clampValue),
            umbrella:data.get('umbrella') === 'yes', outerDiameter:null
        };
        var needs = buildNeeds(settings);
        var used = {};
        var rows = needs.map(function (need) {
            var product = choose(need, settings, used);
            if (product && need.kind === 'pipe' && product.system === 'sandwich' && product.outer_diameter) settings.outerDiameter = product.outer_diameter;
            return product ? {product:product, quantity:need.quantity, label:need.label} : null;
        }).filter(Boolean);
        if (!rows.length) {
            result.innerHTML = '<div class="cs-result__empty"><h2>Точных позиций пока не найдено</h2><p>Отправьте параметры менеджеру — он соберёт комплект вручную.</p><a href="#manager-check" class="tf-btn animate-btn">Передать менеджеру</a></div>';
            return;
        }
        var subtotal = rows.reduce(function (sum, row) { return sum + row.product.price * row.quantity; }, 0);
        var saving = unlocked ? subtotal * discount / 100 : 0;
        var total = subtotal - saving;
        var missing = needs.length - rows.length;
        var systemLabel = settings.system === 'combined' ? 'моно ' + settings.monoLength.toLocaleString('ru-RU') + ' м + утеплённый' : (settings.system === 'sandwich' ? 'полностью утеплённый' : 'полностью моно');
        var names = rows.map(function (row) { return row.label + ': ' + row.quantity + ' × ' + row.product.name; }).join('; ');
        message.value = 'Подбор дымохода: Ø ' + settings.diameter + ' мм, ' + systemLabel + ', сталь ' + (settings.steel || 'из доступных')
            + ', схема ' + settings.scheme + ', высота ' + settings.height + ' м, адаптер ' + (settings.stoveAdapter ? 'да' : 'нет')
            + ', шибер ' + (settings.damper ? 'да' : 'нет') + ', ППУ ' + settings.ceilingPasses + ', хомуты ' + settings.clamps
            + ', зонт ' + (settings.umbrella ? 'да' : 'нет') + '. Предварительный состав: ' + names;
        result.innerHTML = '<h2>Предварительный комплект</h2><p class="text-caption-01 cl-text-2">Ø ' + settings.diameter + ' мм · ' + systemLabel + ' · ' + settings.height + ' м</p>'
            + (missing ? '<p class="cs-safety">Не все специальные узлы найдены в точном исполнении. Недостающие позиции добавит менеджер.</p>' : '')
            + '<div class="cs-result__list">' + rows.map(function (row) {
                return '<div class="cs-result__item"><img src="' + esc(row.product.image) + '" alt=""><div><a href="' + esc(row.product.url) + '">' + esc(row.product.name)
                    + '</a><small>' + esc(row.label) + ' · ' + row.quantity + ' шт.</small></div><div class="cs-result__price">' + money(row.product.price * row.quantity) + '</div></div>';
            }).join('') + '</div><div class="cs-result__totals"><div class="cs-result__row"><span>Стоимость товаров</span><strong>' + money(subtotal) + '</strong></div>'
            + (unlocked ? '<div class="cs-result__row cs-result__saving"><span>Скидка на дымоход −' + discount + '%</span><strong>−' + money(saving) + '</strong></div>' : '<div class="cs-result__row cs-result__saving"><span>Добавьте печь, камин или котёл — получите −' + discount + '%</span></div>')
            + '<div class="cs-result__row cs-result__row--total"><span>Итого</span><span>' + money(total) + '</span></div></div><div class="cs-result__actions"><button type="button" class="tf-btn animate-btn" data-add-kit>'
            + (missing ? 'Добавить найденные позиции' : 'Добавить комплект') + '</button><a href="#manager-check" class="tf-btn btn-white btn-stroke">Проверить с менеджером</a></div>';

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
    });
})();
</script>
@endpush
