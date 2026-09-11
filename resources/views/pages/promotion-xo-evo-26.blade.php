@extends('layouts.amerce')

@push('styles')
<style>
    .evo-page { --evo-red:#ef4b4b; --evo-ink:#17191d; --evo-muted:#6d737a; --evo-warm:#f3f1ec; --evo-line:#e4e0d8; width:100%; overflow:hidden; }
    .evo-page .evo-kicker { display:inline-flex; align-items:center; gap:10px; color:var(--evo-red); font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .evo-page .evo-kicker::before { width:28px; height:2px; background:currentColor; content:""; }
    .evo-title { font-size:clamp(36px,4.5vw,64px); line-height:1.02; letter-spacing:-.045em; }
    .evo-lead { color:var(--evo-muted); font-size:clamp(17px,1.35vw,21px); line-height:1.58; }
    .evo-hero { position:relative; padding:38px 0 92px; background:#17191d; color:#fff; }
    .evo-hero::before { position:absolute; inset:0; background:radial-gradient(circle at 18% 78%,rgba(239,75,75,.18),transparent 34%),linear-gradient(135deg,rgba(255,255,255,.04),transparent 52%); content:""; }
    .evo-hero .container { position:relative; }
    .evo-hero .breadcrumbs a,.evo-hero .breadcrumbs p,.evo-hero .breadcrumbs i { color:rgba(255,255,255,.55)!important; }
    .evo-hero__grid { display:grid; grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr); align-items:center; gap:52px; margin-top:52px; }
    .evo-hero__grid > * { min-width:0; }
    .evo-hero h1,.evo-hero p { color:#fff; }
    .evo-hero h1 { max-width:650px; font-size:clamp(44px,5.4vw,76px); line-height:.97; letter-spacing:-.055em; }
    .evo-badge { display:inline-flex; margin-bottom:22px; padding:9px 14px; border-radius:999px; background:var(--evo-red); font-size:13px; font-weight:700; }
    .evo-hero__lead { max-width:610px; color:rgba(255,255,255,.69)!important; font-size:19px; line-height:1.58; }
    .evo-price { display:flex; align-items:baseline; flex-wrap:wrap; gap:13px; margin:25px 0 27px; }
    .evo-price__new { font-size:38px; font-weight:700; }
    .evo-price__old { color:rgba(255,255,255,.43); font-size:18px; text-decoration:line-through; }
    .evo-price__save { color:#ff7770; font-size:14px; font-weight:700; }
    .evo-actions { display:flex; flex-wrap:wrap; gap:11px; }
    .evo-actions .tf-btn { min-width:205px; }
    .evo-facts { display:flex; flex-wrap:wrap; gap:9px; margin-top:29px; }
    .evo-fact { padding:9px 13px; border:1px solid rgba(255,255,255,.15); border-radius:999px; color:rgba(255,255,255,.73); font-size:13px; }
    .evo-hero__photo { position:relative; min-height:610px; overflow:hidden; border-radius:32px; background:#272a2e; }
    .evo-hero__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .evo-hero__photo::after { position:absolute; inset:0; background:linear-gradient(0deg,rgba(10,12,14,.55),transparent 42%); content:""; }
    .evo-stock-card { position:absolute; z-index:1; right:22px; bottom:22px; left:22px; display:flex; align-items:end; justify-content:space-between; gap:18px; padding:20px 22px; border-radius:18px; background:rgba(255,255,255,.94); color:var(--evo-ink); backdrop-filter:blur(12px); }
    .evo-stock-card small { display:block; color:var(--evo-red); font-weight:700; letter-spacing:.08em; }
    .evo-stock-card strong { font-size:22px; }
    .evo-stock-card span { color:#747980; font-size:13px; }
    .evo-benefits,.evo-specs,.evo-video,.evo-request,.evo-faq { padding:104px 0; }
    .evo-benefits { background:#fff; }
    .evo-benefits__intro { max-width:830px; margin:0 auto 42px; text-align:center; }
    .evo-benefit { height:100%; min-height:245px; padding:28px; border:1px solid var(--evo-line); border-radius:22px; background:#fff; }
    .evo-benefit__num { display:grid; place-items:center; width:46px; height:46px; margin-bottom:32px; border-radius:14px; background:#fff0ef; color:var(--evo-red); font-weight:700; }
    .evo-benefit h3 { margin-bottom:11px; font-size:22px; }
    .evo-benefit p { color:var(--evo-muted); line-height:1.58; }
    .evo-gallery { padding:104px 0; background:var(--evo-warm); }
    .evo-gallery__grid { display:grid; grid-template-columns:1.15fr .85fr; grid-template-rows:repeat(2,330px); gap:16px; margin-top:42px; }
    .evo-gallery figure { position:relative; overflow:hidden; border-radius:24px; background:#202328; }
    .evo-gallery figure:first-child { grid-row:1/3; }
    .evo-gallery img { width:100%; height:100%; object-fit:cover; }
    .evo-gallery figcaption { position:absolute; right:16px; bottom:16px; left:16px; padding:13px 15px; border-radius:13px; background:rgba(255,255,255,.93); color:#202327; font-size:13px; font-weight:600; backdrop-filter:blur(8px); }
    .evo-system { padding:104px 0; background:#181b1f; color:#fff; }
    .evo-system h2,.evo-system h3,.evo-system p { color:#fff; }
    .evo-system .evo-lead { color:rgba(255,255,255,.64)!important; }
    .evo-system__list { display:grid; gap:10px; margin-top:30px; }
    .evo-system__item { display:grid; grid-template-columns:28px 1fr; gap:13px; padding:17px 18px; border:1px solid rgba(255,255,255,.12); border-radius:15px; background:rgba(255,255,255,.045); }
    .evo-system__item span { color:#ff716a; font-weight:700; }
    .evo-system__visual { min-height:600px; overflow:hidden; border-radius:28px; background:#0f1114; }
    .evo-system__visual img { width:100%; height:600px; object-fit:cover; }
    .evo-specs { background:#fff; }
    .evo-specs__table { overflow:hidden; border:1px solid var(--evo-line); border-radius:22px; }
    .evo-specs__row { display:grid; grid-template-columns:1fr auto; gap:20px; padding:16px 21px; border-bottom:1px solid var(--evo-line); }
    .evo-specs__row:last-child { border-bottom:0; }
    .evo-specs__row:nth-child(even) { background:#faf9f7; }
    .evo-specs__row span { color:var(--evo-muted); }
    .evo-note { margin-top:16px; color:var(--evo-muted); font-size:13px; line-height:1.55; }
    .evo-video { background:var(--evo-warm); }
    .evo-video__wrap { position:relative; margin-top:38px; padding-top:56.25%; overflow:hidden; border-radius:26px; background:#111; box-shadow:0 26px 65px rgba(25,28,31,.16); }
    .evo-video__wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }
    .evo-request { background:#e9efef; }
    .evo-request__photo { position:relative; min-height:650px; overflow:hidden; border-radius:28px; }
    .evo-request__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .evo-request__photo::after { position:absolute; inset:0; background:linear-gradient(0deg,rgba(9,11,13,.76),transparent 58%); content:""; }
    .evo-request__caption { position:absolute; z-index:1; right:22px; bottom:22px; left:22px; color:#fff; }
    .evo-request__caption small { color:#ff716a; font-weight:700; letter-spacing:.08em; }
    .evo-request__caption h3 { margin-top:8px; color:#fff; }
    .evo-request__form { padding:38px; border-radius:27px; background:#fff; box-shadow:0 22px 65px rgba(35,44,48,.1); }
    .evo-request__contacts { display:flex; flex-wrap:wrap; gap:16px; margin:19px 0 27px; }
    .evo-request__contacts a { color:#20262b!important; font-weight:600; }
    .evo-terms { margin-top:17px; padding:15px 17px; border-radius:14px; background:#f6f4f0; color:var(--evo-muted); font-size:13px; line-height:1.55; }
    .evo-faq { background:#fff; }
    .evo-faq__intro { position:sticky; top:120px; }
    @media(max-width:991px){.evo-hero__grid{grid-template-columns:1fr}.evo-hero__photo{min-height:570px}.evo-gallery__grid{grid-template-columns:1fr;grid-template-rows:auto}.evo-gallery figure:first-child{grid-row:auto}.evo-gallery figure{min-height:480px}.evo-system__visual{margin-top:36px}.evo-request__form{margin-top:30px}.evo-faq__intro{position:static;margin-bottom:32px}}
    @media(max-width:767px){.evo-page{width:100%;max-width:100%;overflow-x:hidden}.evo-page .container{box-sizing:border-box;width:100vw!important;max-width:100vw!important;min-width:0!important;margin-right:0;margin-left:0;padding-right:16px;padding-left:16px}.evo-hero{padding:28px 0 62px}.evo-hero .breadcrumbs{display:none}.evo-hero__grid{display:block;width:calc(100vw - 32px)!important;max-width:calc(100vw - 32px)!important;margin-top:18px}.evo-hero__grid>*{width:calc(100vw - 32px)!important;max-width:calc(100vw - 32px)!important;min-width:0!important}.evo-hero h1{width:100%!important;max-width:100%!important;margin-right:0;font-size:40px;white-space:normal!important;overflow-wrap:break-word}.evo-hero__lead{width:100%!important;max-width:100%!important;white-space:normal!important;overflow-wrap:break-word}.evo-price{width:100%;max-width:100%}.evo-price__save{flex-basis:100%}.evo-actions{display:grid;width:100%;margin-bottom:30px}.evo-actions .tf-btn{box-sizing:border-box;width:100%;min-width:0}.evo-facts{display:none}.evo-hero__photo,.evo-gallery figure,.evo-system__visual,.evo-system__visual img,.evo-request__photo{min-height:420px;height:420px}.evo-benefits,.evo-gallery,.evo-system,.evo-specs,.evo-video,.evo-request,.evo-faq{padding:72px 0}.evo-request__form{padding:25px 20px}.evo-specs__row{grid-template-columns:1fr;gap:4px}.evo-stock-card{right:12px;bottom:12px;left:12px;align-items:flex-start;flex-direction:column}.evo-gallery__grid{gap:12px}}
</style>
@endpush

@section('content')
<main id="wrapper" class="evo-page">
    <section class="evo-hero"><div class="container">
        <div class="breadcrumbs"><a href="/" class="text-caption-01 link">Главная</a><i class="icon icon-CaretRightThin"></i><a href="/akcii" class="text-caption-01 link">Акции</a><i class="icon icon-CaretRightThin"></i><p class="text-caption-01">KOTLOV XO EVO 26 кВт</p></div>
        <div class="evo-hero__grid"><div>
            <span class="evo-badge">−{{ $discountPercent }}% · РАСПРОДАЖА 2 ШТУК</span>
            <h1 class="mb-24">EVO 26 кВт.<br>Две на складе.</h1>
            <p class="evo-hero__lead mb-0">Автоматическая пеллетная горелка со сменной топкой AISI 310S, самоочищающимся колосником и контроллером XO-1.0S со встроенным Wi‑Fi. Обе акционные горелки физически находятся на складе KOTLOV.</p>
            <div class="evo-price"><span class="evo-price__new">{{ number_format($product->price, 0, '.', ' ') }} BYN</span>@if($product->price_old > $product->price)<span class="evo-price__old">{{ number_format($product->price_old, 0, '.', ' ') }} BYN</span>@endif @if($saving > 0)<span class="evo-price__save">Экономия {{ number_format($saving, 0, '.', ' ') }} BYN</span>@endif</div>
            <div class="evo-actions"><a href="#evo-request" class="tf-btn animate-btn" data-analytics-event="pellet_burner_evo_promo_lead_click">Проверить совместимость</a><a href="/{{ $product->category->slug }}/{{ $product->slug }}" class="tf-btn btn-white">Купить горелку</a></div>
            <div class="evo-facts"><span class="evo-fact">8–26 кВт</span><span class="evo-fact">2 штуки в наличии</span><span class="evo-fact">Сменная топка</span><span class="evo-fact">Самоочистка</span><span class="evo-fact">Гарантия 24 месяца</span></div>
        </div><div class="evo-hero__photo"><img loading="eager" src="{{ asset('img/promotions/kotlov-xo-evo-26-stock-cover.jpg') }}" alt="KOTLOV XO EVO 26 кВт в наличии на складе"><div class="evo-stock-card"><div><small>ФАКТИЧЕСКИЙ ОСТАТОК</small><strong>{{ $stockCount }} штуки в наличии</strong></div><span>Не поставка под заказ</span></div></div></div>
    </div></section>

    <section class="evo-benefits"><div class="container"><div class="evo-benefits__intro"><span class="evo-kicker">Что получает владелец</span><h2 class="evo-title mt-16 mb-18">Компактная автоматика для котла</h2><p class="evo-lead mb-0">EVO берёт на себя подачу пеллет, розжиг, модуляцию мощности и очистку рабочей зоны.</p></div><div class="row g-16">
        @foreach([['01','Сменная топка','Камера из AISI 310S меняется отдельно, без покупки новой горелки целиком.'],['02','Самоочистка','Подпружиненный колосник очищает рабочую поверхность и сохраняет проход воздуха.'],['03','Керамический розжиг','Заявленный ресурс запальника — до 10 000 автоматических запусков.'],['04','Точная подача воздуха','Первичный и вторичный воздух настраиваются под топливо и режим мощности.']] as [$n,$heading,$text])<div class="col-md-6 col-xl-3"><article class="evo-benefit"><span class="evo-benefit__num">{{ $n }}</span><h3>{{ $heading }}</h3><p class="mb-0">{{ $text }}</p></article></div>@endforeach
    </div></div></section>

    <section class="evo-gallery"><div class="container"><span class="evo-kicker">Реальные фотографии</span><h2 class="evo-title mt-16 mb-18">Именно эти горелки участвуют в акции</h2><p class="evo-lead mb-0" style="max-width:820px">Без рендеров и каталожных подмен: корпус, вентилятор, топка и колосниковый блок складской EVO 26 кВт.</p><div class="evo-gallery__grid">
        <figure class="mb-0"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-evo-26-stock-side.jpg') }}" alt="Вид сбоку KOTLOV XO EVO 26 кВт"><figcaption>Корпус EVO и сменная стальная топочная часть</figcaption></figure>
        <figure class="mb-0"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-evo-26-firebox.jpg') }}" alt="Топка и колосник KOTLOV XO EVO 26 кВт"><figcaption>Колосниковый блок внутри камеры сгорания</figcaption></figure>
        <figure class="mb-0"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-evo-26-fan.jpg') }}" alt="Вентилятор KOTLOV XO EVO 26 кВт"><figcaption>Центробежный вентилятор и привод подачи воздуха</figcaption></figure>
    </div></div></section>

    <section class="evo-system"><div class="container"><div class="row align-items-center g-40"><div class="col-lg-6"><span class="evo-kicker">Монтаж в существующий котёл</span><h2 class="evo-title mt-16 mb-20">Проверяем совместимость, а не наличие</h2><p class="evo-lead">Обе горелки уже на складе. До оформления нужно убедиться, что EVO правильно встанет в конкретный котёл и сможет работать без перегрева или противодавления.</p><div class="evo-system__list">
        @foreach(['Теплопотери здания и рабочий диапазон 8–26 кВт','Объём топки и расстояние до теплообменника','Размер дверцы и конструкция переходного фланца','Разрежение, сечение и состояние дымохода','Место для бункера, шнека и обслуживания'] as $item)<div class="evo-system__item"><span>✓</span><strong>{{ $item }}</strong></div>@endforeach
    </div><p class="mt-24 mb-0"><a href="/blog/pelletnaya-gorelka-kotlov-xo-evo-26-kvt" class="fw-semibold text-decoration-underline link" style="color:#fff">Прочитать техническую статью →</a></p></div><div class="col-lg-6"><div class="evo-system__visual"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-evo-26-stock-cover.jpg') }}" alt="Пеллетная горелка EVO 26 кВт на складе KOTLOV"></div></div></div></div></section>

    <section class="evo-specs"><div class="container"><div class="row g-40 align-items-start"><div class="col-lg-5"><span class="evo-kicker">Параметры EB140</span><h2 class="evo-title mt-16 mb-20">KOTLOV XO EVO 26</h2><p class="evo-lead">Акционная версия со стальной сменной топкой. Подбор по площади остаётся предварительным — ориентируемся на расчётную нагрузку.</p><a href="/{{ $product->category->slug }}/{{ $product->slug }}" class="tf-btn animate-btn mt-20">Карточка товара</a></div><div class="col-lg-7"><div class="evo-specs__table">
        @foreach([['Диапазон мощности','8–26 кВт'],['Ориентировочная площадь','100–300 м²'],['Электропотребление','48 Вт'],['При розжиге','407 Вт'],['Габариты (Д × В × Ш)','601 × 341 × 273 мм'],['Сечение топочной части','139 × 142 мм'],['Минимальное разрежение','20 Па'],['Масса','18 кг'],['Пеллеты','Ø 6–8 мм'],['Гарантия','24 месяца']] as [$label,$value])<div class="evo-specs__row"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>@endforeach
    </div><p class="evo-note">Производитель заявляет КПД сгорания до 98% в настроенном режиме; в карточке товара указано 92%. Это не сезонный КПД всей котельной: итог зависит от котла, топлива, дымохода и настройки.</p></div></div></div></section>

    <section class="evo-video"><div class="container"><div class="text-center mx-auto" style="max-width:820px"><span class="evo-kicker">Видеообзор</span><h2 class="evo-title mt-16 mb-18">EVO в деталях</h2><p class="evo-lead mb-0">Конструкция и принцип работы серии KOTLOV XO EVO. Видео начинается с технической части.</p></div><div class="evo-video__wrap"><iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/v7n7WoDPUTA?start=67" title="KOTLOV XO EVO — пеллетная горелка нового поколения" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div></div></section>

    @include('partials.xo-controller-feature')
    <section class="evo-request" id="evo-request"><div class="container"><div class="row align-items-center g-40"><div class="col-lg-5"><div class="evo-request__photo"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-evo-26-fan.jpg') }}" alt="KOTLOV XO EVO 26 кВт — реальная горелка"><div class="evo-request__caption"><small>ИНЖЕНЕРНЫЙ ПОДБОР KOTLOV</small><h3 class="mb-0">Проверим ваш котёл до оформления заказа</h3></div></div></div><div class="col-lg-7"><form method="POST" action="{{ route('install-requests.store') }}" class="evo-request__form kv-form" data-analytics-form="pellet_burner_evo_promo" novalidate>@csrf<x-form-protection/><input type="hidden" name="specialization" value="engineering"><input type="hidden" name="source" value="pellet_burner_evo_promo"><input type="hidden" name="product_id" value="{{ $product->id }}"><span class="evo-kicker">Расчёт для вашего котла</span><h2 class="mt-14 mb-10">Получить предложение со скидкой</h2><p class="cl-text-2 mb-0">Пришлите данные котла и объекта. Инженер проверит монтаж, фланец, дымоход и состав комплекта.</p><div class="evo-request__contacts"><a href="tel:+375293544041">+375 (29) 354-40-41</a><a href="mailto:info@kotlov.by">info@kotlov.by</a></div>
        @if(session('success'))<div class="alert alert-success mb-20" role="status">{{ session('success') }}</div>@endif @if($errors->any())<div class="kv-summary kv-summary--visible mb-20" role="alert">Пожалуйста, проверьте обязательные поля.</div>@endif
        <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="evo-name">Имя *</label><input id="evo-name" name="customer_name" type="text" value="{{ old('customer_name') }}" placeholder="Ваше имя" data-required="1" data-label="Имя"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="evo-phone">Телефон *</label><input id="evo-phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" placeholder="+375 (XX) XXX-XX-XX" data-required="1" data-label="Телефон"></fieldset></div>
        <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="evo-email">Email</label><input id="evo-email" name="customer_email" type="email" value="{{ old('customer_email') }}" placeholder="mail@example.com"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="evo-city">Город</label><input id="evo-city" name="city" type="text" value="{{ old('city') }}" placeholder="Город или район"></fieldset></div>
        <fieldset class="tf-field mb-20"><label class="tf-lable fw-medium" for="evo-description">Котёл и объект</label><textarea id="evo-description" name="description" rows="5" placeholder="Модель котла, площадь, размеры топки, дымоход...">{{ old('description') }}</textarea></fieldset><button type="submit" class="tf-btn animate-btn w-100">Отправить заявку инженеру</button><div class="evo-terms">На складе находятся две KOTLOV XO EVO 26 кВт EB140. Скидка 20% действует на эти две единицы и завершается после их продажи. Инженер уточнит совместимость, комплектацию и монтаж.</div>
    </form></div></div></div></section>

    <section class="evo-faq"><div class="container"><div class="row g-40"><div class="col-lg-5"><div class="evo-faq__intro"><span class="evo-kicker">Перед заказом</span><h2 class="evo-title mt-16 mb-16">Частые вопросы</h2><p class="evo-lead">Наличие уже известно. Разбираемся с технической совместимостью.</p></div></div><div class="col-lg-7"><div id="accordion-evo-promo">@foreach($faq as $question=>$answer)<div class="accordion-item_v2"><div class="accordion-action {{ $loop->first?'':'collapsed' }} lh-24 fw-medium" data-bs-target="#evo-faq-{{ $loop->index }}" data-bs-toggle="collapse" aria-expanded="{{ $loop->first?'true':'false' }}" role="button"><span>{{ $question }}</span><span class="icon ic-accordion-custom cl-2"></span></div><div id="evo-faq-{{ $loop->index }}" class="collapse {{ $loop->first?'show':'' }}" data-bs-parent="#accordion-evo-promo"><p class="faq-content cl-text-2">{{ $answer }}</p></div></div>@endforeach</div></div></div></div></section>
</main>
@endsection
