@extends('layouts.amerce')

@section('title', 'Подбор дымохода из нержавеющей стали | KOTLOV.BY')
@section('meta_description', 'Пошаговый подбор дымохода по диаметру, стали, схеме и высоте. Скидка 5% за самостоятельный подбор и 10% на дымоход с печью, камином или котлом.')
@section('body-class', ($managerMode ?? false) ? 'chimney-manager-mode' : '')

@section('content')
<main class="chimney-selector-page {{ ($managerMode ?? false) ? 'chimney-selector-page--manager' : '' }}">
    <section class="cs-hero">
        <div class="container">
            <div class="cs-hero__grid">
                <div>
                    <span class="cs-kicker">{{ ($managerMode ?? false) ? 'Рабочий инструмент менеджера' : 'Подбор за несколько минут' }}</span>
                    <h1>{{ ($managerMode ?? false) ? (($managerCalculation ?? null) ? (($managerCalculation->status === 'pending' ? 'Работа с заявкой ' : 'Расчёт ').$managerCalculation->number) : 'Новый расчёт дымохода') : 'Соберите дымоход под своё оборудование' }}</h1>
                    <p>{{ ($managerMode ?? false) ? 'Задайте параметры, скорректируйте комплект и сохраните расчёт в CRM.' : 'Соберите комплект «'.$preferredBrandName.'», получите скидку за самостоятельный подбор и отправьте схему менеджеру на бесплатную проверку.' }}</p>
                    <div class="cs-hero__actions"><a href="{{ \App\Support\KnowledgeBase::URL }}" class="tf-btn btn-white btn-stroke">База знаний</a><a href="#calculator" class="tf-btn animate-btn">Рассчитать комплект</a></div>
                    @if ($equipment)
                        <div class="cs-equipment"><strong>Выбрано оборудование:</strong> {{ $equipment->name }}@if($defaultDiameter) · патрубок {{ $defaultDiameter }} мм @endif</div>
                    @endif
                </div>
                <div class="cs-discount">
                    <div><strong>−{{ number_format($selfSelectionDiscountPercent, 0) }}%</strong><span>за самостоятельный подбор комплекта</span></div>
                    <div><strong>−{{ number_format($discountPercent, 0) }}%</strong><span>на дымоход вместе с печью, камином или котлом</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing" id="calculator">
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
                        <label>Топливо<select name="fuel"><option value="wood" selected>Дрова</option><option value="coal">Уголь / брикеты</option><option value="pellet">Пеллеты</option><option value="gas">Газ</option></select></label>
                        <label>Режим работы<select name="operation"><option value="normal" selected>Обычный периодический</option><option value="intensive">Интенсивный / банная печь</option><option value="continuous">Длительный непрерывный</option></select></label>
                        <label>Марка стали<select name="steel" data-steel><option value="auto" selected>Рекомендовать автоматически</option><option value="">Подобрать из доступных</option><option value="430">AISI 430</option><option value="304">AISI 304</option><option value="316">AISI 316</option><option value="321">AISI 321</option></select></label>
                        <label>Толщина стали трубы<select name="thickness" data-thickness><option value="auto" selected>Рекомендовать автоматически</option><option value="">Подобрать из доступных</option><option value="0.5">0,5 мм</option><option value="0.8">0,8 мм</option><option value="1">1,0 мм</option></select></label>
                        <label data-mono-material-field>Материал моно-участка<select name="mono_material" data-mono-material><option value="stainless" selected>Нержавеющая сталь «Теплов и Сухов»</option><option value="mc_black" data-mc-black-option>Окрашенная система «Теплов и Сухов» MC Black</option><option value="black_darco" data-darco-option>Чёрная сталь DARCO, 2 мм</option></select></label>
                        <label data-combined-field>Длина моно-участка<select name="mono_length"><option value="0.25">0,25 м</option><option value="0.5">0,5 м</option><option value="0.75">0,75 м</option><option value="1" selected>1 м</option><option value="1.25">1,25 м</option><option value="1.5">1,5 м</option><option value="1.75">1,75 м</option><option value="2">2 м</option></select></label>
                        <label>Адаптер на печь<select name="stove_adapter"><option value="yes" selected>Нужен</option><option value="no">Не нужен</option></select></label>
                        <label>Диаметр трассы после адаптера<select name="chimney_diameter" data-chimney-diameter></select></label>
                        <label>Нагрев воды<select name="water_heating"><option value="none" selected>Не нужен</option><option value="water_tank">Бак на трубе</option><option value="heat_exchanger">Теплообменник / регистр для выносного бака</option></select></label>
                    </div>
                    <p class="cs-recommendation" data-material-recommendation>Для дровяной печи рекомендуем AISI 430, 0,8 мм.</p>
                    <p class="cs-field-note">Складские диаметры трассы: Ø120, Ø150, Ø180 и Ø200. Для патрубка Ø130 можно выбрать адаптер на Ø120 или Ø150.</p>
                    <p class="cs-field-note">Бак на трубе и теплообменник — альтернативные решения. Выбранный элемент ставится на стартовом моно-участке до перехода на утеплённый дымоход.</p>

                    <div class="cs-step"><span>03</span><div><h2>Схема и высота</h2><p>Выберите основные элементы трассы. Точный состав проверит специалист.</p></div></div>
                    <div class="cs-fields">
                        <label>Схема<select name="scheme"><option value="straight">Прямая через кровлю</option><option value="tee">Через стену с тройником</option><option value="elbows">Со смещением и коленами</option></select></label>
                        <label data-elbows-field>Угол колен для смещения<select name="elbow_angle"><option value="45" selected>2 × 45°</option><option value="90">1 × 90°</option></select></label>
                        <label>Общая вертикальная высота, м<input type="number" name="height" value="5" min="1" max="20" step="0.25" inputmode="decimal"></label>
                        <label data-tee-field>Горизонтальный участок через стену, м<input type="number" name="horizontal_length" value="0.5" min="0.25" max="3" step="0.25" inputmode="decimal"></label>
                        <label data-tee-field>Проход через стену (ППУ)<select name="wall_pass"><option value="yes" selected>Нужен</option><option value="no">Не нужен</option></select></label>
                        <label data-tee-field>Вылет трубы от стены<select name="wall_mount_reach"><option value="0" selected>Обычное крепление</option><option value="700">Регулируемое до 700 мм</option><option value="1000">Регулируемое до 1000 мм</option><option value="1200">Регулируемое до 1200 мм</option></select></label>
                        <label data-elbows-field>Длина участка между коленами, м<input type="number" name="offset_length" value="0.5" min="0.25" max="3" step="0.25" inputmode="decimal"></label>
                        <label data-elbows-field>Где находится смещение<select name="offset_system"><option value="mono">Внутри — моно</option><option value="sandwich">Снаружи — утеплённый</option></select></label>
                        <label>Шибер<select name="damper"><option value="no" selected>Не нужен</option><option value="yes">Нужен</option></select></label>
                        <label>Проходы через перекрытие (ППУ)<select name="ceiling_passes"><option value="0">Нет</option><option value="1" selected>1 проход</option><option value="2">2 прохода</option></select></label>
                        <label>Обычные соединительные хомуты<select name="clamps"><option value="auto" selected>Рассчитать по высоте</option><option value="0">Не нужны</option><option value="1">1 шт.</option><option value="2">2 шт.</option><option value="3">3 шт.</option><option value="4">4 шт.</option><option value="5">5 шт.</option><option value="6">6 шт.</option></select></label>
                        <label data-tee-field>Стеновые крепления<select name="wall_mounts"><option value="auto" selected>Рассчитать автоматически</option><option value="0">Не нужны</option><option value="1">1 шт.</option><option value="2">2 шт.</option><option value="3">3 шт.</option><option value="4">4 шт.</option><option value="5">5 шт.</option></select></label>
                        <label>Свободная высота над последним креплением, м<input type="number" name="free_mast_height" value="1" min="0" max="5" step="0.25" inputmode="decimal"></label>
                        <label>Растяжки для высокой мачты<select name="guy_wires"><option value="auto" selected>Автоматически</option><option value="no">Не нужны</option><option value="yes">Нужны</option></select></label>
                        <label>Кровля<select name="roof_type"><option value="pitched_metal" selected>Скатная металлическая</option><option value="pitched_soft">Скатная мягкая</option><option value="flat">Плоская</option><option value="none">Кровлю не проходим</option></select></label>
                        <label data-roof-field>Угол кровли, °<input type="number" name="roof_angle" value="30" min="0" max="60" step="5" inputmode="numeric"></label>
                        <label data-roof-field>Расстояние от трубы до конька, м<input type="number" name="ridge_distance" value="1.5" min="0" max="10" step="0.25" inputmode="decimal"></label>
                        <label>Зонт<select name="umbrella"><option value="yes" selected>Нужен</option><option value="no">Не нужен</option></select></label>
                    </div>
                    <div class="cs-form__actions">
                        <button type="submit" class="tf-btn animate-btn" data-calculate-button>Рассчитать и показать комплект</button>
                        <button type="button" class="tf-btn btn-white btn-stroke" data-clear-calculation>Очистить</button>
                    </div>
                    <div class="cs-live-status" data-live-status aria-live="polite"><span></span>Комплект пересчитывается автоматически при изменении параметров.</div>
                    <p class="cs-safety">Результат предварительный. Перед покупкой менеджер проверит высоту, проходы перекрытий, пожарные отступы, тягу и условия монтажа.</p>
                </form>

                <aside class="cs-result" data-cs-result aria-live="polite">
                    <div class="cs-result__empty"><span class="icon icon-CheckCircle"></span><h2>Комплект появится здесь</h2><p>Заполните параметры и нажмите «Показать комплект».</p></div>
                </aside>
            </div>
        </div>
    </section>

    <div class="modal modalCentered fade cs-check-modal" id="chimneyCheckModal" tabindex="-1" aria-labelledby="chimneyCheckModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="icon-close-popup" data-bs-dismiss="modal" aria-label="Закрыть"><i class="icon-X2"></i></button>
                <div class="modal-heading">
                    <span class="cs-kicker">{{ ($managerMode ?? false) ? 'Сохранение в CRM' : 'Проверка специалистом' }}</span>
                    <h3 class="title-pop" id="chimneyCheckModalTitle">{{ ($managerMode ?? false) ? (($managerCalculation ?? null) ? 'Сохранить изменения' : 'Создать расчёт') : 'Передайте схему менеджеру' }}</h3>
                    <p class="desc-pop cl-text-2">{{ ($managerMode ?? false) ? 'Укажите данные клиента. Состав, схема, цена и скидка сохранятся в расчёте.' : 'Оставьте имя и телефон. Создадим лид с выбранной схемой и составом комплекта, проверим совместимость и свяжемся с вами.' }}</p>
                </div>
                <div class="modal-main">
                    <form action="{{ ($managerMode ?? false) ? route('admin.chimney-calculator.save') : route('chimney-calculation-requests.store') }}" method="POST" data-cs-manager-form>@csrf<x-form-protection />
                        @if($managerCalculation ?? null)<input type="hidden" name="calculation_id" value="{{ $managerCalculation->id }}">@endif
                        <input type="hidden" name="source_url" value="{{ url()->current() }}" data-cs-source-url>
                        <input type="hidden" name="selector_token" value="{{ $selectorToken }}">
                        @if($equipment)<input type="hidden" name="equipment_product_id" value="{{ $equipment->id }}">@endif
                        <input type="hidden" name="configuration" value="Нужна проверка комплекта дымохода" data-cs-message>
                        <span data-cs-items hidden></span>
                        <label>{{ ($managerMode ?? false) ? 'Имя клиента' : 'Ваше имя' }}<input type="text" name="customer_name" value="{{ old('customer_name', $managerCalculation?->customer_name ?? '') }}" placeholder="Как обращаться к клиенту" required autocomplete="name"></label>
                        <label>Телефон<input type="tel" name="customer_phone" value="{{ old('customer_phone', $managerCalculation?->customer_phone ?? '') }}" placeholder="+375 (XX) XXX-XX-XX" required autocomplete="tel"></label>
                        @if($managerMode ?? false)<label>Email клиента<input type="email" name="customer_email" value="{{ old('customer_email', $managerCalculation?->customer_email ?? '') }}" placeholder="client@example.by" autocomplete="email"></label>@endif
                        <div class="cs-check-modal__feedback" data-cs-request-feedback hidden aria-live="polite"></div>
                        <button class="tf-btn animate-btn w-100" data-cs-request-submit disabled>{{ ($managerMode ?? false) ? (($managerCalculation ?? null) ? 'Сохранить изменения' : 'Создать расчёт') : 'Создать заявку на проверку' }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection

@push('styles')
<style>
.chimney-selector-page{--cs-red:#e34234;--cs-dark:#111;--cs-line:#e6e6e3;background:#fafaf8}.cs-hero{padding:70px 0;background:#111;color:#fff}.cs-hero__grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:48px;align-items:center}.cs-hero h1{max-width:780px;margin:12px 0 16px;color:#fff;font-size:clamp(38px,5vw,68px);line-height:1.02}.cs-hero p{max-width:700px;margin:0;color:#c8c8c8;font-size:18px;line-height:1.6}.cs-kicker{color:#ff7065;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.cs-equipment{margin-top:24px;padding:15px 18px;border:1px solid #383838;border-radius:14px;background:#1b1b1b}.cs-discount{display:flex;flex-direction:column;padding:30px;border-radius:24px;background:var(--cs-red)}.cs-discount strong{font-size:58px;line-height:1}.cs-discount span{margin-top:10px;line-height:1.4}.cs-layout{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(340px,.85fr);gap:32px;align-items:start}.cs-form,.cs-result{padding:30px;border:1px solid var(--cs-line);border-radius:24px;background:#fff}.cs-result{position:sticky;top:110px;min-height:350px}.cs-step{display:flex;gap:16px;margin:0 0 18px}.cs-step:not(:first-child){margin-top:34px;padding-top:28px;border-top:1px solid var(--cs-line)}.cs-step>span{display:grid;flex:0 0 38px;height:38px;place-items:center;border-radius:50%;background:#111;color:#fff;font-size:12px;font-weight:700}.cs-step h2{margin:0 0 4px;font-size:23px}.cs-step p{margin:0;color:#777;font-size:14px}.cs-options{display:flex;flex-wrap:wrap;gap:8px}.cs-options input{position:absolute;opacity:0}.cs-options span{display:block;padding:11px 14px;border:1px solid var(--cs-line);border-radius:10px;cursor:pointer}.cs-options input:checked+span{border-color:#111;background:#111;color:#fff}.cs-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}.cs-fields label{font-size:13px;font-weight:700}.cs-fields select,.cs-fields input{width:100%;height:50px;margin-top:7px;padding:0 14px;border:1px solid var(--cs-line);border-radius:10px;background:#fff}.cs-fields [hidden]{display:none}.cs-field-note{margin:0 0 26px;color:#777;font-size:12px}.cs-form__actions{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px}.cs-form__actions .tf-btn{min-height:50px}.cs-safety{margin:14px 0 0;color:#777;font-size:12px;line-height:1.5}.cs-result__empty{padding:60px 15px;text-align:center;color:#777}.cs-result__empty span{font-size:42px}.cs-result h2{font-size:24px}.cs-result__list{display:grid;gap:12px;margin:20px 0}.cs-result__item{display:grid;grid-template-columns:58px 1fr auto;gap:12px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--cs-line)}.cs-result__item img{width:58px;height:58px;object-fit:contain}.cs-result__item a{color:#111;font-size:13px;font-weight:700}.cs-result__item small{display:block;color:#777}.cs-result__controls{display:flex;align-items:center;gap:4px;margin-top:8px}.cs-result__quantity{min-width:28px;text-align:center;font-size:13px;font-weight:800}.cs-result__control{display:grid;width:30px;height:30px;padding:0;place-items:center;border:1px solid #ddd;border-radius:8px;background:#fff;color:#111;font-size:18px;line-height:1}.cs-result__control:hover{border-color:#111}.cs-result__remove{margin-left:5px;padding:5px;border:0;background:transparent;color:#777;font-size:12px;text-decoration:underline}.cs-result__price{font-weight:700;white-space:nowrap}.cs-result__totals{padding:18px;border-radius:14px;background:#f5f5f2}.cs-result__row{display:flex;justify-content:space-between;gap:12px;margin-bottom:8px}.cs-result__row--total{margin:14px 0 0;padding-top:14px;border-top:1px solid #ddd;font-size:18px;font-weight:800}.cs-result__saving{color:var(--cs-red);font-weight:700}.cs-result__actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.cs-manager{padding:55px 0;background:#eeeae4}.cs-manager__box{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}.cs-manager h2{margin:8px 0}.cs-manager p{margin:0;color:#666}.cs-manager form{display:grid;grid-template-columns:1fr 1fr auto;gap:10px}.cs-manager input{height:54px;padding:0 16px;border:1px solid #ccc;border-radius:10px;background:#fff}@media(max-width:991px){.cs-hero__grid,.cs-layout,.cs-manager__box{grid-template-columns:1fr}.cs-discount{max-width:330px}.cs-result{position:static}.cs-manager form{grid-template-columns:1fr}}@media(max-width:575px){.cs-hero{padding:42px 0}.cs-hero h1{font-size:38px}.cs-form,.cs-result{padding:20px;border-radius:18px}.cs-fields,.cs-form__actions,.cs-result__actions{grid-template-columns:1fr}.cs-result__item{grid-template-columns:48px 1fr}.cs-result__price{grid-column:2}.cs-options span{padding:9px 11px;font-size:13px}}
.cs-addon{margin-top:14px}.cs-addon__toggle{display:flex;width:100%;height:48px;padding:0 14px;align-items:center;justify-content:center;border:1px dashed #aaa;border-radius:12px;background:#fff;font-weight:800;line-height:1.2;text-align:center}.cs-addon__panel{margin-top:10px;padding:12px;border:1px solid var(--cs-line);border-radius:14px;background:#fafaf8}.cs-addon__panel[hidden]{display:none}.cs-addon__search{width:100%;height:46px;padding:0 14px;border:1px solid #ccc;border-radius:10px;background:#fff}.cs-addon__results{display:grid;gap:7px;margin-top:10px}.cs-addon__result{display:grid;grid-template-columns:42px 1fr auto;gap:9px;align-items:center;padding:8px;border:1px solid #e3e3df;border-radius:10px;background:#fff;text-align:left}.cs-addon__result img{width:42px;height:42px;object-fit:contain}.cs-addon__result strong{display:block;font-size:12px;line-height:1.25}.cs-addon__result small{color:#777}.cs-addon__add{width:32px;height:32px;border:0;border-radius:50%;background:#111;color:#fff;font-size:20px}.cs-addon__empty{padding:10px;color:#777;font-size:12px;text-align:center}
.cs-cart-feedback{margin-top:12px;padding:13px 14px;border-radius:12px;font-size:13px;line-height:1.45}.cs-cart-feedback[hidden]{display:none}.cs-cart-feedback--partial{border:1px solid #f0c36a;background:#fff7df;color:#654b00}.cs-cart-feedback strong{display:block;margin-bottom:5px}.cs-cart-feedback ul{margin:6px 0 0;padding-left:18px}.cs-cart-feedback a{color:inherit;font-weight:800;text-decoration:underline}
.cs-live-status{display:flex;gap:8px;align-items:center;margin-top:10px;color:#687176;font-size:12px}.cs-live-status span{width:8px;height:8px;border-radius:50%;background:#1d9b55;box-shadow:0 0 0 4px #e7f5ed}.cs-live-status.is-updating span{background:#e09b19;box-shadow:0 0 0 4px #fff2d8}.cs-live-status.is-error span{background:#d94135;box-shadow:0 0 0 4px #fde7e5}
.cs-diagram{margin:16px 0;padding:16px;border:1px solid #dfe4e6;border-radius:14px;background:#f5f7f8;color:#111}.cs-diagram__head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}.cs-diagram__title{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.cs-diagram__hint{color:#777;font-size:11px}.cs-schematic{display:block;width:100%;height:auto;max-height:570px;border-radius:10px;background:#fff}.cs-schematic--assembly{max-height:none;margin-top:12px;border:1px solid #e1e5e6}.cs-schematic text{font-family:Arial,sans-serif}.cs-schematic__part{cursor:pointer}.cs-schematic__part:hover .cs-schematic__hotspot,.cs-schematic__part:focus .cs-schematic__hotspot{stroke:var(--cs-red)!important;filter:drop-shadow(0 0 3px rgba(227,66,52,.35))}.cs-schematic__part:hover image,.cs-schematic__part:focus image{filter:drop-shadow(0 3px 5px rgba(227,66,52,.45))}.cs-diagram__subtitle{margin:14px 0 7px;color:#111;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.cs-diagram__toolbar{display:flex;justify-content:flex-end;margin-top:10px}.cs-diagram__download{padding:8px 11px;border:1px solid #cfd4d6;border-radius:8px;background:#fff;color:#111;font-size:12px;font-weight:700}.cs-diagram__download:hover{border-color:#111}.cs-result__item{transition:background .2s,box-shadow .2s}.cs-result__item.is-diagram-highlight,.cs-result__item:target{margin:-7px;padding:7px 7px 19px;border-radius:10px;background:#fff4df;box-shadow:0 0 0 2px #f0b84b}.cs-share{width:100%;margin-top:10px}.cs-recommendation{margin:8px 0 0;color:#666;font-size:12px}@media(max-width:575px){.cs-diagram{padding:10px}.cs-diagram__head{align-items:flex-start;flex-direction:column}.cs-schematic{max-height:none}}
.cs-result__actions .tf-btn{display:flex;min-width:0;min-height:58px;padding:12px 14px;align-items:center;justify-content:center;line-height:1.2;text-align:center}.cs-check-modal .modal-content{padding:30px;border:0;border-radius:22px}.cs-check-modal .modal-heading{padding-right:30px}.cs-check-modal .title-pop{margin:7px 0 8px}.cs-check-modal form{display:grid;gap:14px}.cs-check-modal form label{font-size:13px;font-weight:700}.cs-check-modal form input{width:100%;height:52px;margin-top:7px;padding:0 14px;border:1px solid var(--cs-line);border-radius:10px;background:#fff}.cs-check-modal__feedback{padding:12px 14px;border-radius:10px;background:#fff3dc;color:#654b00;font-size:13px}.cs-check-modal__feedback.is-success{background:#e8f6ed;color:#176b39}.cs-check-modal__feedback.is-error{background:#fdebea;color:#a52a21}@media(max-width:575px){.cs-result__actions .tf-btn{min-height:52px}.cs-check-modal .modal-content{padding:22px}}
.cs-hero__actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.cs-hero__actions .tf-btn{min-width:190px}.cs-discount{gap:0}.cs-discount>div{display:flex;flex-direction:column;padding:18px 0}.cs-discount>div+div{border-top:1px solid rgba(255,255,255,.35)}.cs-discount strong{font-size:46px}.cs-discount span{display:block;margin-top:7px}@media(max-width:575px){.cs-hero__actions{display:grid}.cs-discount{max-width:none}}
.chimney-selector-page--manager .cs-hero{padding:28px 0}.chimney-selector-page--manager .cs-hero h1{font-size:clamp(28px,3vw,42px)}.chimney-selector-page--manager .cs-hero__actions{display:none}.chimney-selector-page--manager .flat-spacing{padding-top:28px;padding-bottom:28px}.chimney-selector-page--manager .cs-result{top:18px}.chimney-selector-page--manager .cs-result__actions{grid-template-columns:1fr}.chimney-manager-mode{background:#fafaf8}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var catalog = @json($catalog);
    var unlocked = @json($discountUnlocked);
    var discount = {{ (float) $discountPercent }};
    var selfDiscount = {{ (float) $selfSelectionDiscountPercent }};
    var managerMode = @json((bool) ($managerMode ?? false));
    var managerCanEdit = @json(!($managerCalculation ?? null) || $managerCalculation->status === 'pending');
    var selectorToken = @json($selectorToken);
    var equipmentId = @json(($equipmentInCart ?? false) ? null : $equipment?->id);
    var equipmentName = @json($equipment?->name);
    var form = document.querySelector('[data-cs-form]');
    var result = document.querySelector('[data-cs-result]');
    var message = document.querySelector('[data-cs-message]');
    var requestItems = document.querySelector('[data-cs-items]');
    var requestSubmit = document.querySelector('[data-cs-request-submit]');
    var managerForm = document.querySelector('[data-cs-manager-form]');
    var sourceUrlInput = document.querySelector('[data-cs-source-url]');
    var requestFeedback = document.querySelector('[data-cs-request-feedback]');
    var calculateButton = form ? form.querySelector('[data-calculate-button]') : null;
    var clearButton = form ? form.querySelector('[data-clear-calculation]') : null;
    var liveStatus = form ? form.querySelector('[data-live-status]') : null;
    var currentCalculationUrl = window.location.href;
    if (!form || !result) return;

    var standardDiameters = {
        '100':['120'], '110':['120'], '115':['120'], '120':['120'],
        '130':['120','150'], '150':['150'], '160':['180'], '180':['180'], '200':['200']
    };
    var preferredDiameter = {'100':'120', '110':'120', '115':'120', '120':'120', '130':'150', '150':'150', '160':'180', '180':'180', '200':'200'};
    var chimneyDiameterSelect = form.querySelector('[data-chimney-diameter]');
    var monoMaterialSelect = form.querySelector('[data-mono-material]');
    var sharedParams = new URLSearchParams(window.location.search);
    var sharedChimneyDiameter = sharedParams.get('chimney_diameter');
    var restoreSharedItems = sharedParams.get('kit') === '1';
    var calculationActive = true;
    Array.from(form.elements).forEach(function (field) {
        if (!field.name || field.name === 'chimney_diameter' || !sharedParams.has(field.name)) return;
        var value = sharedParams.get(field.name);
        if (field.type === 'radio') field.checked = field.value === value;
        else if (field.tagName === 'SELECT' && !Array.from(field.options).some(function (option) { return option.value === value; })) return;
        else field.value = value;
    });
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
        chimneyDiameterSelect.value = options.includes(sharedChimneyDiameter) ? sharedChimneyDiameter : (options.includes(previous) ? previous : (preferredDiameter[inlet] || options[0]));
        sharedChimneyDiameter = null;
        syncMonoMaterial();
    }
    function syncConditionalFields() {
        var scheme = form.elements.scheme.value;
        var system = form.elements.system.value;
        form.querySelectorAll('[data-tee-field]').forEach(function (field) { field.hidden = scheme !== 'tee'; });
        form.querySelectorAll('[data-elbows-field]').forEach(function (field) { field.hidden = scheme !== 'elbows'; });
        form.querySelectorAll('[data-roof-field]').forEach(function (field) { field.hidden = form.elements.roof_type.value === 'none'; });
        form.querySelectorAll('[data-mono-material-field]').forEach(function (field) { field.hidden = system === 'sandwich'; });
        form.querySelectorAll('[data-combined-field]').forEach(function (field) { field.hidden = system !== 'combined'; });
    }
    function recommendMaterial(fuel, operation) {
        if (fuel === 'gas') return {steel:'316', thickness:'0.5', text:'Для газа рекомендуем кислотостойкую AISI 316, 0,5 мм из-за конденсата.'};
        if (fuel === 'pellet') return {steel:'304', thickness:'0.5', text:'Для пеллет рекомендуем AISI 304, 0,5 мм.'};
        if (fuel === 'coal' || operation === 'intensive') return {steel:'321', thickness:'0.8', text:'Для угля или интенсивной банной топки рекомендуем жаростойкую AISI 321, 0,8 мм.'};
        if (operation === 'continuous') return {steel:'304', thickness:'0.8', text:'Для длительной работы рекомендуем AISI 304, 0,8 мм.'};
        return {steel:'430', thickness:'0.8', text:'Для обычной дровяной печи рекомендуем AISI 430, 0,8 мм.'};
    }
    function syncMaterialRecommendation() {
        var recommendation = recommendMaterial(form.elements.fuel.value, form.elements.operation.value);
        form.querySelector('[data-material-recommendation]').textContent = recommendation.text;
    }
    function roofHeightAdvice(roofType, ridgeDistance) {
        if (roofType === 'none') return 'Проход кровли не выбран.';
        if (ridgeDistance <= 1.5) return 'Оголовок должен быть не ниже чем на 0,5 м выше конька.';
        if (ridgeDistance <= 3) return 'Оголовок должен быть не ниже уровня конька.';
        return 'Высоту оголовка проверяют по линии 10° вниз от конька.';
    }
    form.querySelectorAll('input[name="diameter"]').forEach(function (input) { input.addEventListener('change', syncChimneyDiameters); });
    chimneyDiameterSelect.addEventListener('change', syncMonoMaterial);
    form.elements.scheme.addEventListener('change', syncConditionalFields);
    form.elements.system.addEventListener('change', syncConditionalFields);
    form.elements.roof_type.addEventListener('change', syncConditionalFields);
    form.elements.fuel.addEventListener('change', syncMaterialRecommendation);
    form.elements.operation.addEventListener('change', syncMaterialRecommendation);
    syncChimneyDiameters();
    syncConditionalFields();
    syncMaterialRecommendation();

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
            var diameterRangeMatches = ['ceiling_pass','roof_flashing'].includes(need.kind) && targetDiameter && Array.isArray(product.diameter_pair)
                ? Number(targetDiameter) >= Number(product.diameter_pair[0]) && Number(targetDiameter) <= Number(product.diameter_pair[1])
                : false;
            var productName = normalizeSearch(product.name);
            return product.kind === need.kind && !used[product.id]
                && (adapterPairMatches !== null ? adapterPairMatches : (diameterRangeMatches || !targetDiameter || productDiameter === targetDiameter || (allowMissingDiameter && !productDiameter && !need.exactDiameter)))
                && (need.system === 'any' || product.system === need.system)
                && (need.system !== 'sandwich' || !settings.outerDiameter || product.outer_diameter === settings.outerDiameter)
                && (!need.brandSlug || product.brand_slug === need.brandSlug)
                && (!need.material || product.material === need.material)
                && (need.material === 'black_steel' || ignoreSteel || settings.steel === '' || product.steel === settings.steel || !product.steel)
                && (need.kind !== 'pipe' || (need.thickness || settings.thickness) === '' || product.thickness === (need.thickness || settings.thickness))
                && (!need.angle || product.angle === need.angle || (need.angle === '90' && product.angle === '87'))
                && (!need.lengthMm || product.length_mm === need.lengthMm)
                && (!need.mountLengthMm || product.mount_length_mm === need.mountLengthMm)
                && (!need.nameIncludes || productName.includes(normalizeSearch(need.nameIncludes)))
                && (!need.roofType || need.roofType === 'pitched_metal' || productName.includes('master flash') || productName.includes('мастер флеш'))
                && (need.roofType !== 'flat' || productName.includes('прямой'));
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
    function addWaterHeatingNeed(needs, settings) {
        if (settings.waterHeating === 'water_tank') {
            needs.push({kind:'water_tank', quantity:1, system:'mono', brandSlug:'teplov-i-suhov', label:'Бак на трубе для нагрева воды'});
        } else if (settings.waterHeating === 'heat_exchanger') {
            needs.push({kind:'heat_exchanger', quantity:1, system:'mono', brandSlug:'teplov-i-suhov', label:'Теплообменник / регистр для выносного бака'});
        }
    }
    function buildNeeds(settings) {
        var needs = [];
        var height = Math.max(1, Math.round(settings.height * 4) / 4);
        var monoLength = Math.min(Math.max(0.25, Number(settings.monoLength)), Math.max(0.25, height - 0.5));
        settings.monoLength = monoLength;
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
            addWaterHeatingNeed(needs, settings);
            needs.push({kind:'transition', quantity:1, system:'any', label:'Переход моно / термо'});
            addPipeNeeds(needs, Math.max(0.5, height - monoLength), 'sandwich', 'Наружная трасса · утеплённая', {material:'stainless'});
        } else {
            var fullSystemOptions = settings.system === 'mono' ? monoOptions : {material:'stainless'};
            if (settings.system === 'sandwich') {
                if (settings.damper) needs.push({kind:'damper', quantity:1, system:'mono', material:'stainless', label:'Шибер у печи · моно'});
                addWaterHeatingNeed(needs, settings);
                if (settings.damper || settings.waterHeating !== 'none') {
                    needs.push({kind:'transition', quantity:1, system:'any', label:'Переход стартового моно-участка на утеплённую систему'});
                }
            } else if (settings.damper) {
                needs.push(Object.assign({kind:'damper', quantity:1, system:settings.system, label:'Шибер'}, fullSystemOptions));
            }
            if (settings.system === 'mono') addWaterHeatingNeed(needs, settings);
            addPipeNeeds(needs, height, settings.system, settings.system === 'sandwich' ? 'Утеплённая трасса' : monoSectionLabel, fullSystemOptions);
        }
        if (settings.scheme === 'tee') {
            var teeSystem = settings.system === 'mono' ? 'mono' : 'sandwich';
            var teeOptions = teeSystem === 'mono' ? monoOptions : {material:'stainless'};
            addPipeNeeds(needs, settings.horizontalLength, teeSystem, 'Горизонтальный участок через стену', teeOptions);
            needs.push({kind:'tee', quantity:1, system:teeSystem, label:'Тройник 87°'});
            needs.push({kind:'condensate', quantity:1, system:'any', label:'Ревизия / конденсатоотвод'});
            needs.push({kind:'support', quantity:1, system:teeSystem, material:teeSystem === 'mono' ? monoOptions.material : 'stainless', diameterMode:teeSystem === 'mono' ? 'inner' : 'outer', exactDiameter:true, label:'Опорная монтажная площадка под тройник'});
            if (settings.wallMounts > 0 && settings.wallMountReach > 0) {
                needs.push({kind:'wall_mount', quantity:settings.wallMounts, system:'any', material:'stainless', mountLengthMm:settings.wallMountReach, label:'Регулируемые стеновые крепления · ' + settings.wallMountReach + ' мм'});
                needs.push({kind:'mount', quantity:settings.wallMounts, system:'any', material:'stainless', nameIncludes:'хомут штанги регулируемой', diameterMode:teeSystem === 'mono' ? 'inner' : 'outer', exactDiameter:true, label:'Хомуты для регулируемых стеновых креплений'});
            } else if (settings.wallMounts > 0) {
                needs.push({kind:'wall_mount', quantity:settings.wallMounts, system:'any', material:'stainless', diameterMode:teeSystem === 'mono' ? 'inner' : 'outer', exactDiameter:true, label:'Стеновые крепления наружной трассы'});
            }
        }
        if (settings.scheme === 'elbows') {
            var offsetSystem = settings.offsetSystem === 'sandwich' ? 'sandwich' : 'mono';
            var elbowOptions = offsetSystem === 'sandwich' ? {material:'stainless'} : monoOptions;
            needs.push(Object.assign({kind:'elbow', quantity:settings.elbowAngle === '45' ? 2 : 1, system:offsetSystem, angle:settings.elbowAngle, label:'Колена для смещения · ' + settings.elbowAngle + '°'}, elbowOptions));
            addPipeNeeds(needs, settings.offsetLength, offsetSystem, 'Участок между коленами', elbowOptions);
        }
        var totalPasses = settings.ceilingPasses + (settings.scheme === 'tee' && settings.wallPass ? 1 : 0);
        if (totalPasses > 0) needs.push({kind:'ceiling_pass', quantity:totalPasses, system:'any', material:settings.system === 'mono' ? monoOptions.material : 'stainless', diameterMode:'outer', exactDiameter:true, label:'ППУ · проходы через перекрытие / стену'});
        if (settings.clamps > 0) needs.push({kind:'clamp', quantity:settings.clamps, system:'any', diameterMode:settings.system === 'mono' ? 'inner' : 'outer', exactDiameter:true, label:'Обычные соединительные хомуты'});
        if (settings.guyWires) needs.push({kind:'guy_wire', quantity:1, system:'any', diameterMode:settings.system === 'mono' ? 'inner' : 'outer', label:'Хомут для растяжек высокой мачты'});
        if (settings.roofType !== 'none') needs.push({kind:'roof_flashing', quantity:1, system:'any', material:'stainless', roofType:settings.roofType, diameterMode:settings.system === 'mono' ? 'inner' : 'outer', exactDiameter:true, label:'Фартук / проход кровли · ' + settings.roofAngle + '°'});
        if (settings.umbrella) needs.push({kind:'umbrella', quantity:1, system:settings.system === 'mono' ? 'mono' : 'sandwich', material:'stainless', label:'Зонт / завершение дымохода'});
        return needs;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        calculationActive = true;
        var data = new FormData(form);
        var height = Number(data.get('height'));
        var clampValue = data.get('clamps');
        var wallMountValue = data.get('wall_mounts');
        var freeMastHeight = Number(data.get('free_mast_height'));
        var guyWireValue = data.get('guy_wires');
        var recommendation = recommendMaterial(data.get('fuel'), data.get('operation'));
        var selectedSteel = data.get('steel') === 'auto' ? recommendation.steel : data.get('steel');
        var selectedThickness = data.get('thickness') === 'auto' ? recommendation.thickness : data.get('thickness');
        var settings = {
            diameter:data.get('diameter'), system:data.get('system'), fuel:data.get('fuel'), operation:data.get('operation'), steel:selectedSteel, thickness:selectedThickness, scheme:data.get('scheme'),
            height:height, monoLength:Number(data.get('mono_length')), monoMaterial:data.get('mono_material'), stoveAdapter:data.get('stove_adapter') === 'yes', elbowAngle:data.get('elbow_angle'),
            horizontalLength:Number(data.get('horizontal_length')), wallPass:data.get('wall_pass') === 'yes', wallMountReach:Number(data.get('wall_mount_reach')), offsetLength:Number(data.get('offset_length')), offsetSystem:data.get('offset_system'),
            damper:data.get('damper') === 'yes', waterHeating:data.get('water_heating') || 'none', ceilingPasses:Number(data.get('ceiling_passes')),
            clamps:clampValue === 'auto' ? Math.max(2, Math.ceil(height / 2)) : Number(clampValue),
            wallMounts:wallMountValue === 'auto' ? Math.max(2, Math.ceil(height / 2)) : Number(wallMountValue),
            freeMastHeight:freeMastHeight, guyWires:guyWireValue === 'yes' || (guyWireValue === 'auto' && freeMastHeight > 1.5),
            roofType:data.get('roof_type'), roofAngle:Number(data.get('roof_angle')), ridgeDistance:Number(data.get('ridge_distance')),
            umbrella:data.get('umbrella') === 'yes', outerDiameter:null, chimneyDiameter:data.get('chimney_diameter'), recommendation:recommendation.text
        };
        settings.roofAdvice = roofHeightAdvice(settings.roofType, settings.ridgeDistance);
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
        var sharedItems = sharedParams.get('items');
        if (restoreSharedItems && sharedItems) {
            var restoredRows = sharedItems.split(',').map(function (entry) {
                var parts = entry.split(':');
                var product = catalog.find(function (item) { return item.id === Number(parts[0]); });
                var quantity = Math.max(1, Math.min(99, Number(parts[1]) || 1));
                return product ? {product:product, quantity:quantity, label:'Сохранённый комплект'} : null;
            }).filter(Boolean).reduce(function (resultRows, row) {
                var existing = resultRows.find(function (item) { return item.product.id === row.product.id; });
                if (existing) existing.quantity = Math.min(99, existing.quantity + row.quantity);
                else resultRows.push(row);
                return resultRows;
            }, []);
            if (restoredRows.length) rows = restoredRows;
        }
        restoreSharedItems = false;
        if (!rows.length) {
            requestItems.innerHTML = '';
            requestSubmit.disabled = true;
            message.value = 'Точных позиций по выбранным параметрам не найдено.';
            result.innerHTML = '<div class="cs-result__empty"><h2>Для этой конфигурации нет готового комплекта</h2><p>Измените диаметр или материал либо свяжитесь с менеджером для ручного подбора.</p></div>';
            if (liveStatus) { liveStatus.className = 'cs-live-status is-error'; liveStatus.lastChild.textContent = 'Готовые позиции не найдены — измените параметры или обратитесь к менеджеру.'; }
            if (calculateButton) calculateButton.textContent = 'Нет готового комплекта — изменить параметры';
            return;
        }
        var missing = missingLabels.length;
        var monoLabel = settings.monoMaterial === 'black_darco'
            ? 'чёрный DARCO 2 мм'
            : (settings.monoMaterial === 'mc_black' ? 'окрашенный «Теплов и Сухов» MC Black' : 'моно из нержавеющей стали');
        var systemLabel = settings.system === 'combined' ? monoLabel + ' ' + settings.monoLength.toLocaleString('ru-RU') + ' м + утеплённый' : (settings.system === 'sandwich' ? 'полностью утеплённый' : 'полностью ' + monoLabel);
        var waterHeatingLabel = settings.waterHeating === 'water_tank' ? 'бак на трубе' : (settings.waterHeating === 'heat_exchanger' ? 'теплообменник / регистр' : 'не нужен');
        var diameterLabel = settings.diameter === settings.chimneyDiameter ? 'Ø ' + settings.diameter + ' мм' : 'патрубок Ø ' + settings.diameter + ' → дымоход Ø ' + settings.chimneyDiameter + ' мм';
        function diagramHtml() {
            var stackX = settings.scheme === 'tee' ? 292 : (settings.scheme === 'elbows' ? 258 : 220);
            var equipmentX = settings.scheme === 'tee' ? 96 : (settings.scheme === 'elbows' ? 172 : 220);
            var roofShown = settings.roofType !== 'none';
            var roofY = 166;
            var topY = roofShown ? 76 : 104;
            var transitionY = settings.scheme === 'elbows' ? 286 : 316;

            function rowIndex(kinds) {
                return rows.findIndex(function (row) { return kinds.indexOf(row.product.kind) !== -1; });
            }
            function part(kinds, markup, label) {
                var index = rowIndex(kinds);
                if (index < 0) return markup;
                return '<a href="#cs-kit-row-' + index + '" class="cs-schematic__part" data-diagram-row="' + index + '" aria-label="' + esc(label) + '">' + markup + '</a>';
            }
            function pipePath(path, insulated, label) {
                var markup = insulated
                    ? '<path class="cs-schematic__hotspot" d="' + path + '" fill="none" stroke="#aeb8bd" stroke-width="22" stroke-linecap="round" stroke-linejoin="round"/><path d="' + path + '" fill="none" stroke="#3e4548" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>'
                    : '<path class="cs-schematic__hotspot" d="' + path + '" fill="none" stroke="#34383a" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>';
                return part(['pipe'], markup, label);
            }
            function callout(x, y, title, text, anchor) {
                return '<text x="' + x + '" y="' + y + '" text-anchor="' + (anchor || 'start') + '" fill="#111" font-size="12" font-weight="700">' + esc(title) + '</text>'
                    + (text ? '<text x="' + x + '" y="' + (y + 15) + '" text-anchor="' + (anchor || 'start') + '" fill="#687176" font-size="10">' + esc(text) + '</text>' : '');
            }

            var svg = [];
            svg.push('<svg class="cs-schematic" data-route-schematic viewBox="0 0 440 620" role="img" aria-labelledby="chimney-schematic-title chimney-schematic-desc">');
            svg.push('<title id="chimney-schematic-title">Техническая схема подобранного дымохода</title><desc id="chimney-schematic-desc">Схема показывает оборудование, трассу дымохода, проходы, кровлю, крепления и основные размеры.</desc>');
            svg.push('<defs><marker id="cs-arrow" markerWidth="7" markerHeight="7" refX="3.5" refY="3.5" orient="auto-start-reverse"><path d="M0,0 L7,3.5 L0,7 Z" fill="#6d767a"/></marker><pattern id="cs-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><line x1="0" y1="0" x2="0" y2="6" stroke="#d6dcdf" stroke-width="2"/></pattern></defs>');
            svg.push('<rect x="1" y="1" width="438" height="618" rx="12" fill="#fff" stroke="#e0e5e7"/>');
            svg.push('<text x="22" y="28" fill="#111" font-size="14" font-weight="700">Эскиз монтажа · вид сбоку</text><text x="22" y="45" fill="#788186" font-size="10">Предварительная схема — размеры и пожарные отступы проверяет специалист</text>');
            svg.push('<line x1="40" y1="496" x2="402" y2="496" stroke="#a8afb2" stroke-width="3"/><text x="402" y="512" text-anchor="end" fill="#7a8387" font-size="10">уровень пола</text>');

            if (settings.scheme === 'tee') {
                svg.push('<rect x="192" y="188" width="16" height="308" fill="url(#cs-hatch)" stroke="#bcc4c7"/><text x="182" y="478" text-anchor="end" fill="#7a8387" font-size="10">стена</text>');
            }
            if (roofShown) {
                svg.push('<path d="M48 184 L218 116 L400 184" fill="none" stroke="#7f898d" stroke-width="5" stroke-linejoin="round"/><path d="M48 184 L218 116 L400 184 L400 196 L218 128 L48 196 Z" fill="url(#cs-hatch)" opacity=".9"/>');
                svg.push(callout(386, 210, 'Кровля ' + settings.roofAngle + '°', settings.roofType === 'flat' ? 'плоская' : 'скатная', 'end'));
                svg.push(part(['roof_flashing'], '<path class="cs-schematic__hotspot" d="M' + (stackX - 25) + ' ' + (roofY + 7) + ' L' + stackX + ' ' + (roofY - 5) + ' L' + (stackX + 25) + ' ' + (roofY + 7) + '" fill="none" stroke="#e34234" stroke-width="6" stroke-linecap="round"/>', 'Кровельный проход'));
            }

            svg.push(part(['other'], '<path class="cs-schematic__hotspot" d="M' + (equipmentX - 35) + ' 486 L' + (equipmentX - 31) + ' 425 Q' + equipmentX + ' 398 ' + (equipmentX + 31) + ' 425 L' + (equipmentX + 35) + ' 486 Z" fill="#282c2e" stroke="#111" stroke-width="2"/><rect x="' + (equipmentX - 23) + '" y="440" width="46" height="30" rx="4" fill="#454b4e"/>', 'Печь, камин или котёл'));
            svg.push(callout(equipmentX, 525, 'Оборудование', 'патрубок Ø' + settings.diameter + ' мм', 'middle'));

            var lowerPath;
            var upperPath;
            if (settings.scheme === 'tee') {
                lowerPath = 'M' + equipmentX + ' 420 L' + equipmentX + ' 382 L' + stackX + ' 382 L' + stackX + ' ' + transitionY;
                upperPath = 'M' + stackX + ' ' + transitionY + ' L' + stackX + ' ' + topY;
                svg.push(part(['tee'], '<circle class="cs-schematic__hotspot" cx="' + stackX + '" cy="382" r="18" fill="#fff" stroke="#34383a" stroke-width="8"/><line x1="' + stackX + '" y1="400" x2="' + stackX + '" y2="452" stroke="#34383a" stroke-width="8"/><rect x="' + (stackX - 27) + '" y="451" width="54" height="10" rx="3" fill="#34383a"/>', 'Тройник с опорой'));
                if (settings.wallPass) svg.push(part(['ceiling_pass'], '<rect class="cs-schematic__hotspot" x="184" y="358" width="32" height="48" fill="#fff" stroke="#e34234" stroke-width="3"/><path d="M189 363 L211 401 M189 375 L207 405" stroke="#e9b4ae" stroke-width="2"/>', 'Проход стены'));
                svg.push(callout(62, 354, 'Горизонталь ' + settings.horizontalLength.toLocaleString('ru-RU') + ' м', settings.wallPass ? 'с проходом стены' : 'без прохода стены'));
            } else if (settings.scheme === 'elbows') {
                lowerPath = 'M' + equipmentX + ' 420 L' + equipmentX + ' 342 L' + stackX + ' 286';
                upperPath = 'M' + stackX + ' ' + transitionY + ' L' + stackX + ' ' + topY;
                svg.push(part(['elbow'], '<circle class="cs-schematic__hotspot" cx="' + equipmentX + '" cy="342" r="11" fill="#fff" stroke="#e34234" stroke-width="4"/><circle class="cs-schematic__hotspot" cx="' + stackX + '" cy="286" r="11" fill="#fff" stroke="#e34234" stroke-width="4"/>', 'Колена смещения'));
                svg.push(callout(68, 310, 'Смещение ' + settings.offsetLength.toLocaleString('ru-RU') + ' м', '2 колена по ' + settings.elbowAngle + '°'));
            } else {
                lowerPath = 'M' + stackX + ' 420 L' + stackX + ' ' + transitionY;
                upperPath = 'M' + stackX + ' ' + transitionY + ' L' + stackX + ' ' + topY;
            }

            if (settings.system === 'mono') {
                svg.push(pipePath(lowerPath, false, 'Одностенный участок'));
                svg.push(pipePath(upperPath, false, 'Одностенный вертикальный участок'));
            } else if (settings.system === 'sandwich') {
                svg.push(pipePath(lowerPath, true, 'Утеплённый участок'));
                svg.push(pipePath(upperPath, true, 'Утеплённый вертикальный участок'));
            } else {
                svg.push(pipePath(lowerPath, false, monoLabel + ' · ' + settings.monoLength.toLocaleString('ru-RU') + ' м'));
                svg.push(part(['transition'], '<rect class="cs-schematic__hotspot" x="' + (stackX - 15) + '" y="' + (transitionY - 12) + '" width="30" height="24" rx="5" fill="#fff" stroke="#e34234" stroke-width="4"/>', 'Переход на утеплённый дымоход'));
                svg.push(pipePath(upperPath, true, 'Утеплённый участок'));
            }

            if (settings.stoveAdapter) svg.push(part(['adapter'], '<path class="cs-schematic__hotspot" d="M' + (equipmentX - 13) + ' 423 L' + (equipmentX - 8) + ' 404 L' + (equipmentX + 8) + ' 404 L' + (equipmentX + 13) + ' 423 Z" fill="#fff" stroke="#e34234" stroke-width="3"/>', 'Адаптер Ø' + settings.chimneyDiameter));
            if (settings.damper) svg.push(part(['damper'], '<rect class="cs-schematic__hotspot" x="' + (equipmentX - 18) + '" y="365" width="36" height="16" rx="3" fill="#fff" stroke="#e34234" stroke-width="3"/><line x1="' + (equipmentX + 18) + '" y1="373" x2="' + (equipmentX + 34) + '" y2="373" stroke="#34383a" stroke-width="3"/>', 'Шибер'));
            var waterHeatingX = settings.scheme === 'tee' ? (equipmentX + 184) / 2 : (settings.scheme === 'elbows' ? (equipmentX + stackX) / 2 : equipmentX);
            var waterHeatingY = settings.scheme === 'tee' ? 366 : (settings.scheme === 'elbows' ? 300 : 328);
            var waterCalloutX = settings.scheme === 'tee' ? 52 : 400;
            var waterCalloutY = settings.scheme === 'tee' ? 320 : 342;
            var waterCalloutAnchor = settings.scheme === 'tee' ? 'start' : 'end';
            if (settings.waterHeating === 'water_tank') {
                svg.push(part(['water_tank'], '<rect class="cs-schematic__hotspot" x="' + (waterHeatingX - 27) + '" y="' + waterHeatingY + '" width="54" height="32" rx="8" fill="#c1c9cc" stroke="#687176" stroke-width="2"/><rect x="' + (waterHeatingX - 7) + '" y="' + (waterHeatingY - 2) + '" width="14" height="36" rx="5" fill="#f7f8f8" stroke="#687176" stroke-width="2"/>', 'Бак на трубе'));
                svg.push(callout(waterCalloutX, waterCalloutY, 'Бак на трубе', 'стартовый моно-участок', waterCalloutAnchor));
            } else if (settings.waterHeating === 'heat_exchanger') {
                svg.push(part(['heat_exchanger'], '<rect class="cs-schematic__hotspot" x="' + (waterHeatingX - 23) + '" y="' + (waterHeatingY - 2) + '" width="46" height="36" rx="7" fill="#fff" stroke="#e34234" stroke-width="3"/><path d="M' + (waterHeatingX - 14) + ' ' + (waterHeatingY + 8) + ' H' + (waterHeatingX + 14) + ' M' + (waterHeatingX - 14) + ' ' + (waterHeatingY + 16) + ' H' + (waterHeatingX + 14) + ' M' + (waterHeatingX - 14) + ' ' + (waterHeatingY + 24) + ' H' + (waterHeatingX + 14) + '" stroke="#687176" stroke-width="3"/>', 'Теплообменник для выносного бака'));
                svg.push(callout(waterCalloutX, waterCalloutY, 'Теплообменник', 'для выносного бака', waterCalloutAnchor));
            }

            for (var passIndex = 0; passIndex < settings.ceilingPasses; passIndex++) {
                var passY = 248 + passIndex * 54;
                svg.push('<line x1="45" y1="' + passY + '" x2="395" y2="' + passY + '" stroke="#c9d0d3" stroke-width="6"/>');
                svg.push(part(['ceiling_pass'], '<rect class="cs-schematic__hotspot" x="' + (stackX - 27) + '" y="' + (passY - 12) + '" width="54" height="24" fill="#fff" stroke="#e34234" stroke-width="3"/>', 'ППУ прохода перекрытия'));
            }
            if (settings.ceilingPasses) svg.push(callout(52, 238, 'ППУ × ' + settings.ceilingPasses, 'проход перекрытия'));

            var mountCount = settings.scheme === 'tee' ? Math.max(0, Math.min(4, Number(settings.wallMounts) || 0)) : 0;
            for (var mountIndex = 0; mountIndex < mountCount; mountIndex++) {
                var mountY = 226 + mountIndex * 58;
                var wallX = settings.scheme === 'tee' ? 200 : stackX + 72;
                svg.push(part(['wall_mount','mount'], '<path class="cs-schematic__hotspot" d="M' + stackX + ' ' + mountY + ' L' + wallX + ' ' + mountY + '" fill="none" stroke="#e34234" stroke-width="4"/><circle cx="' + stackX + '" cy="' + mountY + '" r="7" fill="#fff" stroke="#e34234" stroke-width="3"/>', 'Стеновое крепление'));
            }
            if (mountCount) svg.push(callout(385, 320, 'Крепления × ' + mountCount, settings.wallMountReach ? 'вылет ' + settings.wallMountReach + ' мм' : '', 'end'));

            if (settings.guyWires) {
                svg.push(part(['guy_wire'], '<path class="cs-schematic__hotspot" d="M' + stackX + ' 106 L' + (stackX - 92) + ' 182 M' + stackX + ' 106 L' + (stackX + 92) + ' 182" fill="none" stroke="#e34234" stroke-width="2" stroke-dasharray="6 4"/><circle cx="' + stackX + '" cy="106" r="6" fill="#fff" stroke="#e34234" stroke-width="2"/>', 'Растяжки высокой мачты'));
            }
            if (settings.umbrella) svg.push(part(['umbrella'], '<path class="cs-schematic__hotspot" d="M' + (stackX - 28) + ' ' + (topY - 8) + ' Q' + stackX + ' ' + (topY - 34) + ' ' + (stackX + 28) + ' ' + (topY - 8) + ' Z" fill="#34383a" stroke="#34383a" stroke-width="3"/><line x1="' + stackX + '" y1="' + (topY - 8) + '" x2="' + stackX + '" y2="' + (topY + 5) + '" stroke="#34383a" stroke-width="4"/>', 'Завершение дымохода'));

            svg.push('<line x1="28" y1="420" x2="28" y2="' + topY + '" stroke="#6d767a" stroke-width="1.5" marker-start="url(#cs-arrow)" marker-end="url(#cs-arrow)"/><text x="20" y="' + ((420 + topY) / 2) + '" transform="rotate(-90 20 ' + ((420 + topY) / 2) + ')" text-anchor="middle" fill="#374044" font-size="11" font-weight="700">Высота ' + esc(settings.height.toLocaleString('ru-RU')) + ' м</text>');
            if (settings.scheme === 'tee') svg.push('<line x1="' + equipmentX + '" y1="558" x2="' + stackX + '" y2="558" stroke="#6d767a" marker-start="url(#cs-arrow)" marker-end="url(#cs-arrow)"/><text x="' + ((equipmentX + stackX) / 2) + '" y="574" text-anchor="middle" fill="#374044" font-size="10">' + esc(settings.horizontalLength.toLocaleString('ru-RU')) + ' м</text>');
            svg.push('<text x="418" y="28" text-anchor="end" fill="#e34234" font-size="12" font-weight="700">Ø ' + settings.chimneyDiameter + ' мм</text>');
            svg.push('<line x1="22" y1="588" x2="418" y2="588" stroke="#e0e5e7"/><rect x="55" y="601" width="14" height="6" rx="3" fill="#34383a"/><text x="75" y="608" fill="#687176" font-size="9">моно</text><rect x="116" y="599" width="18" height="10" rx="5" fill="#aeb8bd"/><rect x="121" y="601" width="8" height="6" rx="3" fill="#3e4548"/><text x="141" y="608" fill="#687176" font-size="9">утеплённый</text><rect x="225" y="599" width="12" height="10" rx="2" fill="#fff" stroke="#e34234" stroke-width="2"/><text x="244" y="608" fill="#687176" font-size="9">узел / крепление</text>');
            svg.push('</svg>');

            function assemblySequenceRank(row) {
                var kind = row.product.kind;
                var system = row.product.system;
                var combined = settings.system === 'combined';
                if (kind === 'umbrella') return 0;
                if (kind === 'roof_flashing') return 10;
                if (kind === 'guy_wire') return 18;
                if (kind === 'pipe') {
                    if (!combined) return 20;
                    return system === 'sandwich' ? 20 : 50;
                }
                if (kind === 'elbow') {
                    if (!combined) return 24;
                    return system === 'sandwich' ? 24 : 54;
                }
                if (kind === 'clamp') return 27;
                if (kind === 'mount' || kind === 'wall_mount') return 28;
                if (kind === 'ceiling_pass') return 30;
                if (kind === 'transition') return 40;
                if (kind === 'water_tank' || kind === 'heat_exchanger') return 55;
                if (kind === 'tee') return 58;
                if (kind === 'damper') return 60;
                if (kind === 'condensate') return 70;
                if (kind === 'support') return 80;
                if (kind === 'adapter') return settings.scheme === 'tee' ? 65 : 90;
                return 55;
            }
            var assemblyRows = rows.map(function (row, index) { return {row:row, rowIndex:index}; }).sort(function (a, b) {
                return assemblySequenceRank(a.row) - assemblySequenceRank(b.row) || a.rowIndex - b.rowIndex;
            });
            var assemblyHeight = Math.max(540, 116 + assemblyRows.length * 76);
            var assembly = [];
            function labelLines(value) {
                var words = String(value || '').split(/\s+/);
                var lines = ['', ''];
                words.forEach(function (word) {
                    var target = lines[0].length < 31 ? 0 : 1;
                    if (target === 1 && (lines[1] + ' ' + word).trim().length > 34) return;
                    lines[target] = (lines[target] + ' ' + word).trim();
                });
                return lines.filter(Boolean);
            }
            assembly.push('<svg class="cs-schematic cs-schematic--assembly" data-chimney-schematic viewBox="0 0 720 ' + assemblyHeight + '" role="img" aria-labelledby="chimney-assembly-title chimney-assembly-desc">');
            assembly.push('<title id="chimney-assembly-title">Схема сборки модульного дымохода</title><desc id="chimney-assembly-desc">Взрыв-схема показывает реальные элементы выбранного комплекта, их количество и последовательность сборки.</desc>');
            assembly.push('<defs><pattern id="cs-brick" width="30" height="16" patternUnits="userSpaceOnUse"><path d="M0 0H30M0 16H30M0 0V16M15 0V8M7.5 8V16M30 0V16" fill="none" stroke="#e8c99f" stroke-width="1"/></pattern><linearGradient id="cs-shaft" x1="0" x2="1"><stop offset="0" stop-color="#92999c"/><stop offset=".48" stop-color="#f4f6f6"/><stop offset="1" stop-color="#737b7f"/></linearGradient></defs>');
            assembly.push('<rect x="1" y="1" width="718" height="' + (assemblyHeight - 2) + '" rx="12" fill="#fff" stroke="#e0e5e7"/>');
            assembly.push('<text x="28" y="34" fill="#25292b" font-size="17" font-weight="800">СХЕМА СБОРКИ МОДУЛЬНОГО ДЫМОХОДА</text><path d="M28 48H692" stroke="#d9dddf"/><text x="692" y="34" text-anchor="end" fill="#e2a900" font-size="12" font-weight="700">ТЕПЛОВ И СУХОВ · Ø ' + settings.chimneyDiameter + ' ММ</text>');
            assembly.push('<rect x="305" y="68" width="110" height="' + (assemblyHeight - 108) + '" fill="#fffaf0"/><rect x="305" y="68" width="110" height="' + (assemblyHeight - 108) + '" fill="url(#cs-brick)" opacity=".72"/><line x1="360" y1="74" x2="360" y2="' + (assemblyHeight - 54) + '" stroke="#e2a900" stroke-width="3" stroke-dasharray="7 7"/>');
            assemblyRows.forEach(function (entry, assemblyIndex) {
                var row = entry.row;
                var index = entry.rowIndex;
                var y = 86 + assemblyIndex * 76;
                var left = assemblyIndex % 2 === 0;
                var imageX = 329;
                var textX = left ? 276 : 444;
                var lineStart = left ? 328 : 392;
                var lineEnd = left ? 286 : 434;
                var anchor = left ? 'end' : 'start';
                var lines = labelLines(row.label || row.product.name);
                var imageMarkup = '<rect class="cs-schematic__hotspot" x="326" y="' + (y - 4) + '" width="68" height="68" rx="8" fill="#fff" stroke="#cbd1d3" stroke-width="1.5"/><image href="' + esc(row.product.image) + '" x="' + imageX + '" y="' + y + '" width="62" height="60" preserveAspectRatio="xMidYMid meet"/><circle cx="360" cy="' + (y + 67) + '" r="4" fill="#e2a900"/>';
                assembly.push('<a href="#cs-kit-row-' + index + '" class="cs-schematic__part" data-diagram-row="' + index + '" aria-label="' + esc(row.label) + '">' + imageMarkup + '</a>');
                assembly.push('<path d="M' + lineStart + ' ' + (y + 25) + ' L' + lineEnd + ' ' + (y + 25) + '" stroke="#70777a" stroke-width="1"/><circle cx="' + lineStart + '" cy="' + (y + 25) + '" r="2.5" fill="#34393b"/>');
                lines.forEach(function (line, lineIndex) {
                    assembly.push('<text x="' + textX + '" y="' + (y + 18 + lineIndex * 15) + '" text-anchor="' + anchor + '" fill="#272b2d" font-size="12" font-weight="' + (lineIndex === 0 ? '700' : '400') + '">' + esc(line) + '</text>');
                });
                assembly.push('<text x="' + textX + '" y="' + (y + 52) + '" text-anchor="' + anchor + '" fill="#7a8285" font-size="10">' + row.quantity + ' шт. · позиция ' + (index + 1) + '</text>');
            });
            assembly.push('<path d="M30 ' + (assemblyHeight - 38) + 'H690" stroke="#b7bec1"/><text x="30" y="' + (assemblyHeight - 18) + '" fill="#6f777a" font-size="10">Схема предварительная. Очерёдность, опоры, проходы и пожарные отступы проверяет специалист.</text>');
            assembly.push('</svg>');

            return '<div class="cs-diagram"><div class="cs-diagram__head"><div class="cs-diagram__title">Схема трассы</div><div class="cs-diagram__hint">Нажмите на элемент, чтобы перейти к позиции комплекта</div></div>'
                + svg.join('') + '<div class="cs-diagram__subtitle">Комплектующие и последовательность сборки</div>' + assembly.join('')
                + '<div class="cs-diagram__toolbar"><button type="button" class="cs-diagram__download" data-download-schematic>Скачать монтажную схему SVG</button></div></div>';
        }
        function shareUrl() {
            var url = new URL(window.location.href);
            var equipment = url.searchParams.get('equipment');
            url.search = '';
            if (equipment) url.searchParams.set('equipment', equipment);
            new FormData(form).forEach(function (value, key) { url.searchParams.set(key, value); });
            url.searchParams.set('items', rows.map(function (row) { return row.product.id + ':' + row.quantity; }).join(','));
            url.searchParams.set('kit', '1');
            return url.toString();
        }
        function renderBundle() {
            requestItems.innerHTML = rows.map(function (row, index) {
                return '<input type="hidden" name="items[' + index + '][product_id]" value="' + row.product.id + '"><input type="hidden" name="items[' + index + '][quantity]" value="' + row.quantity + '">';
            }).join('');
            currentCalculationUrl = shareUrl();
            requestSubmit.disabled = rows.length === 0;
            if (!rows.length) {
                message.value = 'Подбор дымохода очищен пользователем.';
                result.innerHTML = '<div class="cs-result__empty"><h2>В комплекте нет позиций</h2><p>Измените параметры или снова нажмите «Показать комплект».</p></div>';
                return;
            }
            var subtotal = rows.reduce(function (sum, row) { return sum + row.product.price * row.quantity; }, 0);
            var eligibleSubtotal = rows.reduce(function (sum, row) { return sum + (row.product.discount_eligible ? row.product.price * row.quantity : 0); }, 0);
            var effectiveDiscount = unlocked ? discount : selfDiscount;
            var saving = eligibleSubtotal * effectiveDiscount / 100;
            var total = subtotal - saving;
            var names = rows.map(function (row) { return row.label + ': ' + row.quantity + ' × ' + row.product.name; }).join('; ');
            message.value = 'Подбор дымохода: ' + diameterLabel + ', ' + systemLabel + ', сталь ' + (settings.steel || 'из доступных')
                + ', толщина трубы ' + (settings.thickness ? settings.thickness.replace('.', ',') + ' мм' : 'из доступных')
                + ', рекомендация: ' + settings.recommendation + ' ' + settings.roofAdvice + ', схема ' + settings.scheme
                + (settings.scheme === 'tee' ? ', горизонталь ' + settings.horizontalLength + ' м, проход стены ' + (settings.wallPass ? 'да' : 'нет') + ', вылет крепления ' + (settings.wallMountReach || 'обычное') : '')
                + (settings.scheme === 'elbows' ? ', колена ' + settings.elbowAngle + '°, участок ' + settings.offsetLength + ' м' : '')
                + ', высота ' + settings.height + ' м, кровля ' + settings.roofType + ' ' + settings.roofAngle + '°, до конька ' + settings.ridgeDistance + ' м, свободная мачта ' + settings.freeMastHeight + ' м, адаптер ' + (settings.stoveAdapter ? 'да' : 'нет')
                + ', шибер ' + (settings.damper ? 'да' : 'нет') + ', ППУ ' + settings.ceilingPasses + ', хомуты ' + settings.clamps
                + ', нагрев воды: ' + waterHeatingLabel + ', растяжки ' + (settings.guyWires ? 'да' : 'нет') + ', зонт ' + (settings.umbrella ? 'да' : 'нет') + '. Скорректированный состав: ' + names;
            var resultActions = managerMode
                ? (managerCanEdit ? '<button type="button" class="tf-btn animate-btn" data-bs-toggle="modal" data-bs-target="#chimneyCheckModal">' + (@json(($managerCalculation ?? null) ? 'Сохранить изменения' : 'Создать расчёт')) + '</button>' : '<div class="cs-safety">Расчёт уже преобразован в заказ и доступен только для просмотра.</div>')
                : '<button type="button" class="tf-btn animate-btn" data-add-kit>' + (missing ? 'Добавить найденные позиции' : 'Добавить комплект') + '</button><button type="button" class="tf-btn btn-white btn-stroke" data-bs-toggle="modal" data-bs-target="#chimneyCheckModal">Бесплатно проверить</button>';
            result.innerHTML = '<h2>Предварительный комплект</h2><p class="text-caption-01 cl-text-2">' + diameterLabel + ' · ' + systemLabel + ' · ' + settings.height + ' м</p>'
                + '<p class="cs-recommendation">' + esc(settings.recommendation + ' ' + settings.roofAdvice) + '</p>' + diagramHtml()
                + (missing ? '<p class="cs-safety"><strong>Нужно подобрать вручную:</strong> ' + esc(Array.from(new Set(missingLabels)).join(', ')) + '. Менеджер проверит эти позиции.</p>' : '')
                + '<div class="cs-result__list">' + rows.map(function (row, index) {
                    return '<div class="cs-result__item" id="cs-kit-row-' + index + '" data-kit-kind="' + esc(row.product.kind) + '"><img src="' + esc(row.product.image) + '" alt=""><div><a href="' + esc(row.product.url) + '">' + esc(row.product.name)
                        + '</a><small>' + esc(row.label) + '</small><div class="cs-result__controls"><button type="button" class="cs-result__control" data-row-decrease="' + index + '" aria-label="Уменьшить количество">−</button>'
                        + '<span class="cs-result__quantity" aria-label="Количество">' + row.quantity + '</span><button type="button" class="cs-result__control" data-row-increase="' + index + '" aria-label="Увеличить количество"' + (row.quantity >= 99 ? ' disabled' : '') + '>+</button>'
                        + '<button type="button" class="cs-result__remove" data-row-remove="' + index + '">Удалить</button></div></div><div class="cs-result__price">' + money(row.product.price * row.quantity) + '</div></div>';
                }).join('') + '</div><div class="cs-result__totals"><div class="cs-result__row"><span>Стоимость товаров</span><strong>' + money(subtotal) + '</strong></div>'
                + '<div class="cs-result__row cs-result__saving"><span>' + (unlocked ? 'Дымоход с оборудованием' : 'Самостоятельный подбор') + ' −' + effectiveDiscount + '%</span><strong>−' + money(saving) + '</strong></div>'
                + (!unlocked ? '<p class="cs-safety">Добавьте печь, камин или котёл — скидка на дымоход станет −' + discount + '%.</p>' : '')
                + '<div class="cs-result__row cs-result__row--total"><span>Итого</span><span>' + money(total) + '</span></div></div><div class="cs-result__actions">' + resultActions + '</div>'
                + '<div class="cs-cart-feedback" data-cart-feedback hidden aria-live="polite"></div>'
                + '<div class="cs-addon"><button type="button" class="cs-addon__toggle" data-addon-toggle>+ Добавить элемент «Теплов и Сухов»</button><div class="cs-addon__panel" data-addon-panel hidden>'
                + '<input type="search" class="cs-addon__search" data-addon-search placeholder="Умный поиск: теплообменник, бак, шибер…" autocomplete="off"><div class="cs-addon__results" data-addon-results></div></div></div>'
                + '<button type="button" class="tf-btn btn-white btn-stroke cs-share" data-share-kit>Скопировать ссылку на комплект</button>';

            if (liveStatus) { liveStatus.className = 'cs-live-status'; liveStatus.lastChild.textContent = 'Готово: подобрано ' + rows.length + ' позиций' + (missing ? ', ещё ' + missing + ' нужно проверить вручную.' : '.'); }

            result.querySelectorAll('[data-diagram-row]').forEach(function (diagramPart) {
                function highlight(active) {
                    var row = result.querySelector('#cs-kit-row-' + diagramPart.dataset.diagramRow);
                    if (row) row.classList.toggle('is-diagram-highlight', active);
                }
                diagramPart.addEventListener('mouseenter', function () { highlight(true); });
                diagramPart.addEventListener('mouseleave', function () { highlight(false); });
                diagramPart.addEventListener('focus', function () { highlight(true); });
                diagramPart.addEventListener('blur', function () { highlight(false); });
            });
            result.querySelector('[data-download-schematic]').addEventListener('click', function () {
                var source = result.querySelector('[data-chimney-schematic]');
                if (!source) return;
                var copy = source.cloneNode(true);
                copy.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                copy.querySelectorAll('a').forEach(function (link) { link.removeAttribute('href'); link.removeAttribute('data-diagram-row'); });
                var blob = new Blob(['<' + '?xml version="1.0" encoding="UTF-8"?>\n' + new XMLSerializer().serializeToString(copy)], {type:'image/svg+xml;charset=utf-8'});
                var downloadUrl = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'shema-dymohoda-' + settings.chimneyDiameter + 'mm.svg';
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
            });

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
            result.querySelector('[data-share-kit]').addEventListener('click', function () {
                var button = this;
                var url = shareUrl();
                window.history.replaceState({}, '', url);
                var copied = navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(url) : Promise.reject();
                copied.then(function () { button.textContent = 'Ссылка скопирована'; })
                    .catch(function () { window.prompt('Скопируйте ссылку на комплект', url); button.textContent = 'Ссылка готова'; });
            });

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
            var cartFeedback = result.querySelector('[data-cart-feedback]');
            if (add) add.addEventListener('click', function () {
                if (add.dataset.openCart === '1') {
                    window.location = @json(route('cart'));
                    return;
                }

                add.disabled = true;
                cartFeedback.hidden = true;
                cartFeedback.className = 'cs-cart-feedback';
                cartFeedback.innerHTML = '';
                var csrf = document.querySelector('meta[name="csrf-token"]').content;
                var additions = rows.slice();
                if (equipmentId) additions.unshift({product:{id:equipmentId, name:equipmentName || 'Выбранное оборудование'}, quantity:1});
                var added = [];
                var failed = [];
                var cartCount = null;

                additions.reduce(function (chain, row, index) {
                    return chain.then(function () {
                        add.textContent = 'Добавляем ' + (index + 1) + ' из ' + additions.length + '…';
                        return fetch(@json(route('cart.add')), {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify({product_id:row.product.id,quantity:row.quantity,chimney_selector_token:selectorToken})})
                            .then(function (response) {
                                return response.json().catch(function () { return {}; }).then(function (payload) {
                                    if (!response.ok) throw new Error(payload.message || 'Товар временно нельзя добавить');
                                    return payload;
                                });
                            })
                            .then(function (payload) {
                                added.push(row);
                                if (typeof payload.count === 'number') cartCount = payload.count;
                            })
                            .catch(function (error) {
                                failed.push({
                                    name: row.product.name || ('Товар #' + row.product.id),
                                    message: error.message || 'Товар временно нельзя добавить'
                                });
                            });
                    });
                }, Promise.resolve()).then(function () {
                    if (cartCount !== null && typeof window.updateCartCount === 'function') window.updateCartCount(cartCount);

                    if (!failed.length) {
                        add.textContent = 'Открываем корзину…';
                        window.location = @json(route('cart'));
                        return;
                    }

                    add.disabled = false;
                    add.dataset.openCart = '1';
                    add.textContent = 'Открыть корзину · ' + added.length + ' из ' + additions.length;
                    cartFeedback.className = 'cs-cart-feedback cs-cart-feedback--partial';
                    cartFeedback.hidden = false;
                    cartFeedback.innerHTML = '<strong>В корзину добавлено ' + added.length + ' из ' + additions.length + ' позиций.</strong>'
                        + '<span>Не удалось добавить:</span><ul>' + failed.map(function (failure) {
                            return '<li>' + esc(failure.name) + ' — ' + esc(failure.message) + '</li>';
                        }).join('') + '</ul><a href="' + @json(route('cart')) + '">Перейти в корзину и продолжить</a>';
                });
            });
        }
        renderBundle();
        if (calculateButton) calculateButton.textContent = 'Комплект рассчитан — обновить';
    });
    var recalculateTimer;
    function scheduleRecalculation() {
        if (!calculationActive) {
            clearTimeout(recalculateTimer);
            if (liveStatus) { liveStatus.className = 'cs-live-status'; liveStatus.lastChild.textContent = 'Параметры изменены. Нажмите «Рассчитать и показать комплект».'; }
            if (calculateButton) calculateButton.textContent = 'Рассчитать и показать комплект';
            return;
        }
        if (liveStatus) { liveStatus.className = 'cs-live-status is-updating'; liveStatus.lastChild.textContent = 'Обновляем комплект и схему…'; }
        if (calculateButton) calculateButton.textContent = 'Пересчитываем…';
        clearTimeout(recalculateTimer);
        recalculateTimer = setTimeout(function () {
            if (!form.checkValidity()) {
                if (liveStatus) { liveStatus.className = 'cs-live-status is-error'; liveStatus.lastChild.textContent = 'Проверьте подсвеченное значение — расчёт пока не обновлён.'; }
                if (calculateButton) calculateButton.textContent = 'Проверьте параметры';
                return;
            }
            form.requestSubmit();
        }, 180);
    }
    form.addEventListener('change', scheduleRecalculation);
    form.querySelectorAll('input[type="number"]').forEach(function (input) { input.addEventListener('input', scheduleRecalculation); });
    if (managerForm) managerForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!managerForm.checkValidity() || requestSubmit.disabled) {
            managerForm.reportValidity();
            return;
        }
        requestSubmit.disabled = true;
        requestSubmit.textContent = managerMode ? 'Сохраняем расчёт…' : 'Создаём заявку…';
        requestFeedback.hidden = true;
        requestFeedback.className = 'cs-check-modal__feedback';
        if (sourceUrlInput) sourceUrlInput.value = currentCalculationUrl.length <= 1000 ? currentCalculationUrl : @json(route('chimney-selector'));
        fetch(managerForm.action, {
            method:'POST',
            headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body:new FormData(managerForm)
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (payload) {
                if (!response.ok) {
                    var errors = payload.errors || {};
                    var firstError = Object.keys(errors).length ? [].concat(errors[Object.keys(errors)[0]])[0] : null;
                    throw new Error(firstError || payload.message || 'Не удалось создать заявку. Проверьте данные и попробуйте ещё раз.');
                }
                return payload;
            });
        }).then(function (payload) {
            if (managerMode && payload.admin_url) {
                window.top.location.href = payload.admin_url;
                return;
            }
            requestFeedback.className = 'cs-check-modal__feedback is-success';
            requestFeedback.textContent = payload.message || 'Заявка создана. Менеджер свяжется с вами.';
            requestFeedback.hidden = false;
            requestSubmit.textContent = 'Заявка создана';
            managerForm.querySelectorAll('input:not([type="hidden"])').forEach(function (input) { input.disabled = true; });
        }).catch(function (error) {
            requestFeedback.className = 'cs-check-modal__feedback is-error';
            requestFeedback.textContent = error.message;
            requestFeedback.hidden = false;
            requestSubmit.disabled = false;
            requestSubmit.textContent = managerMode ? @json(($managerCalculation ?? null) ? 'Сохранить изменения' : 'Создать расчёт') : 'Создать заявку на проверку';
        });
    });
    var managerModal = document.getElementById('chimneyCheckModal');
    if (managerModal) managerModal.addEventListener('shown.bs.modal', function () {
        var nameInput = managerForm && managerForm.querySelector('[name="customer_name"]');
        if (nameInput && !nameInput.disabled) nameInput.focus();
    });
    if (clearButton) clearButton.addEventListener('click', function () {
        clearTimeout(recalculateTimer);
        calculationActive = false;
        restoreSharedItems = false;
        sharedChimneyDiameter = null;
        form.reset();
        syncChimneyDiameters();
        syncConditionalFields();
        syncMaterialRecommendation();
        requestItems.innerHTML = '';
        requestSubmit.disabled = true;
        message.value = 'Нужна проверка комплекта дымохода';
        result.innerHTML = '<div class="cs-result__empty"><span class="icon icon-CheckCircle"></span><h2>Расчёт очищен</h2><p>Задайте параметры и нажмите «Рассчитать и показать комплект».</p></div>';
        if (calculateButton) calculateButton.textContent = 'Рассчитать и показать комплект';
        if (liveStatus) { liveStatus.className = 'cs-live-status'; liveStatus.lastChild.textContent = 'Расчёт очищен. Автопересчёт продолжит работать после изменения параметров.'; }
        var cleanUrl = new URL(window.location.href);
        var equipment = cleanUrl.searchParams.get('equipment');
        cleanUrl.search = '';
        if (equipment) cleanUrl.searchParams.set('equipment', equipment);
        window.history.replaceState({}, '', cleanUrl);
    });
    if (sharedParams.get('fresh') === '1' && clearButton) clearButton.click();
    else form.requestSubmit();
})();
</script>
@endpush
