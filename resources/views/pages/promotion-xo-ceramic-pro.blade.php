@extends('layouts.amerce')

@push('styles')
<style>
    .xo-page { --xo-red:#ef4b4b; --xo-red-dark:#cf3737; --xo-ink:#15171a; --xo-muted:#697078; --xo-warm:#f4f1eb; --xo-line:#e3dfd7; overflow:hidden; }
    .xo-page .xo-kicker { display:inline-flex; align-items:center; gap:10px; color:var(--xo-red); font-size:13px; font-weight:700; letter-spacing:.085em; text-transform:uppercase; }
    .xo-page .xo-kicker::before { width:28px; height:2px; background:currentColor; content:""; }
    .xo-page .xo-title { font-size:clamp(36px,4.5vw,64px); line-height:1.02; letter-spacing:-.045em; }
    .xo-page .xo-lead { color:var(--xo-muted); font-size:clamp(17px,1.35vw,21px); line-height:1.55; }
    .xo-hero { position:relative; padding:38px 0 88px; background:#15171a; color:#fff; }
    .xo-hero::before { position:absolute; inset:0; background:radial-gradient(circle at 75% 40%,rgba(239,75,75,.14),transparent 36%),linear-gradient(135deg,rgba(255,255,255,.035),transparent 45%); content:""; }
    .xo-hero .container { position:relative; }
    .xo-hero .breadcrumbs a,.xo-hero .breadcrumbs p,.xo-hero .breadcrumbs i { color:rgba(255,255,255,.56)!important; }
    .xo-hero__grid { display:grid; grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr); align-items:center; gap:50px; margin-top:52px; }
    .xo-hero__grid > * { min-width:0; }
    .xo-hero h1,.xo-hero p { color:#fff; }
    .xo-hero h1 { font-size:clamp(45px,5.5vw,78px); line-height:.96; letter-spacing:-.055em; }
    .xo-hero__discount { display:inline-flex; align-items:center; gap:9px; margin-bottom:22px; padding:9px 14px; border-radius:999px; background:var(--xo-red); font-size:13px; font-weight:700; }
    .xo-hero__lead { max-width:620px; color:rgba(255,255,255,.7)!important; font-size:19px; line-height:1.55; }
    .xo-price { display:flex; align-items:center; flex-wrap:wrap; gap:13px; margin:27px 0 28px; }
    .xo-price__new { font-size:37px; font-weight:700; }
    .xo-price__old { color:rgba(255,255,255,.45); font-size:18px; text-decoration:line-through; }
    .xo-price__save { color:#ff7770; font-size:14px; font-weight:700; }
    .xo-hero__actions { display:flex; flex-wrap:wrap; gap:11px; }
    .xo-hero__actions .tf-btn { min-width:205px; }
    .xo-hero__facts { display:flex; flex-wrap:wrap; gap:9px; margin-top:30px; }
    .xo-hero__fact { padding:9px 13px; border:1px solid rgba(255,255,255,.15); border-radius:999px; color:rgba(255,255,255,.72); font-size:13px; }
    .xo-hero__visual { position:relative; min-height:620px; }
    .xo-hero__product { position:absolute; inset:0; display:grid; place-items:center; overflow:hidden; border-radius:32px; background:radial-gradient(circle at 50% 45%,#35393e,#1d2024 68%); }
    .xo-hero__product img { width:88%; height:88%; object-fit:contain; filter:drop-shadow(0 28px 35px rgba(0,0,0,.38)); }
    .xo-hero__label { position:absolute; right:-15px; bottom:34px; width:240px; padding:18px 20px; border-radius:18px; background:#fff; color:var(--xo-ink); box-shadow:0 18px 50px rgba(0,0,0,.28); }
    .xo-hero__label small { display:block; color:#8a8f94; font-size:11px; font-weight:700; letter-spacing:.07em; }
    .xo-hero__label strong { display:block; margin-top:5px; font-size:18px; }
    .xo-benefits { padding:105px 0; background:#fff; }
    .xo-benefits__intro { max-width:830px; margin:0 auto 44px; text-align:center; }
    .xo-benefit { height:100%; min-height:255px; padding:29px; border:1px solid var(--xo-line); border-radius:22px; background:#fff; transition:.25s ease; }
    .xo-benefit:hover { transform:translateY(-4px); box-shadow:0 20px 48px rgba(22,25,28,.075); }
    .xo-benefit__number { display:inline-grid; place-items:center; width:46px; height:46px; margin-bottom:34px; border-radius:14px; background:#fff0ef; color:var(--xo-red); font-weight:700; }
    .xo-benefit h3 { margin-bottom:11px; font-size:23px; }
    .xo-benefit p { color:var(--xo-muted); line-height:1.6; }
    .xo-system { padding:108px 0; background:var(--xo-warm); }
    .xo-system__visual { position:relative; min-height:610px; overflow:hidden; border-radius:28px; background:#fff; }
    .xo-system__visual img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; padding:36px; }
    .xo-system__copy { padding-left:clamp(8px,4vw,62px); }
    .xo-system__list { display:grid; gap:10px; margin-top:28px; }
    .xo-system__item { display:flex; gap:13px; padding:16px 17px; border:1px solid #dfdbd3; border-radius:15px; background:rgba(255,255,255,.72); }
    .xo-system__item span { display:grid; place-items:center; flex:0 0 25px; width:25px; height:25px; border-radius:50%; background:var(--xo-red); color:#fff; font-size:12px; }
    .xo-automation { padding:108px 0; background:#171a1e; color:#fff; }
    .xo-automation h2,.xo-automation h3,.xo-automation p { color:#fff; }
    .xo-automation__lead { color:rgba(255,255,255,.65)!important; }
    .xo-flow { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:42px; }
    .xo-flow__item { position:relative; min-height:185px; padding:25px; border:1px solid rgba(255,255,255,.12); border-radius:20px; background:rgba(255,255,255,.045); }
    .xo-flow__item small { color:#ff7069; font-weight:700; letter-spacing:.08em; }
    .xo-flow__item h3 { margin-top:30px; font-size:20px; }
    .xo-flow__item p { color:rgba(255,255,255,.58); line-height:1.55; }
    .xo-specs { padding:105px 0; background:#fff; }
    .xo-specs__table { overflow:hidden; border:1px solid var(--xo-line); border-radius:22px; }
    .xo-specs__row { display:grid; grid-template-columns:1fr auto; gap:20px; padding:16px 21px; border-bottom:1px solid var(--xo-line); }
    .xo-specs__row:last-child { border-bottom:0; }
    .xo-specs__row:nth-child(even) { background:#faf9f7; }
    .xo-specs__row span { color:var(--xo-muted); }
    .xo-specs__note { margin-top:16px; color:var(--xo-muted); font-size:13px; line-height:1.55; }
    .xo-market { padding:105px 0; background:#171a1e; color:#fff; }
    .xo-market h2,.xo-market h3,.xo-market p { color:#fff; }
    .xo-market__grid { display:grid; grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr); gap:28px; align-items:stretch; margin-top:42px; }
    .xo-market__price { display:flex; flex-direction:column; justify-content:space-between; min-height:330px; padding:34px; border-radius:24px; background:linear-gradient(145deg,#ef4b4b,#c93636); }
    .xo-market__price small { font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .xo-market__price strong { display:block; margin:20px 0 6px; font-size:clamp(44px,5vw,70px); line-height:1; letter-spacing:-.05em; }
    .xo-market__price p { color:rgba(255,255,255,.78)!important; }
    .xo-market__compare { display:grid; gap:10px; }
    .xo-market__item { display:grid; grid-template-columns:28px 1fr; gap:13px; padding:19px 20px; border:1px solid rgba(255,255,255,.12); border-radius:17px; background:rgba(255,255,255,.045); }
    .xo-market__item span { color:#ff716a; font-weight:700; }
    .xo-market__item p { color:rgba(255,255,255,.66)!important; line-height:1.5; }
    .xo-market__note { margin-top:20px; color:rgba(255,255,255,.48)!important; font-size:12px; line-height:1.55; }
    .xo-proof { padding:105px 0; background:#fff; }
    .xo-proof__gallery { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .xo-proof__photo { position:relative; min-height:285px; overflow:hidden; border-radius:24px; background:#16191c; }
    .xo-proof__photo:first-child { grid-column:1/-1; min-height:390px; }
    .xo-proof__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .xo-proof__badge { position:absolute; right:20px; bottom:20px; left:20px; padding:18px 20px; border-radius:17px; background:rgba(255,255,255,.94); backdrop-filter:blur(10px); }
    .xo-proof__badge small { display:block; margin-bottom:4px; color:var(--xo-red); font-weight:700; letter-spacing:.08em; }
    .xo-proof__copy { padding-left:clamp(8px,4vw,62px); }
    .xo-proof__facts { display:grid; gap:10px; margin-top:28px; }
    .xo-proof__fact { padding:16px 18px; border:1px solid var(--xo-line); border-radius:15px; background:#faf9f7; }
    .xo-audience { padding:105px 0; background:#f5f6f3; }
    .xo-audience__grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-top:38px; }
    .xo-audience__item { min-height:190px; padding:25px; border-radius:20px; background:#fff; }
    .xo-audience__item strong { display:block; margin-bottom:18px; color:var(--xo-red); font-size:30px; }
    .xo-audience__item h3 { font-size:21px; }
    .xo-audience__item p { color:var(--xo-muted); line-height:1.55; }
    .xo-series { padding:105px 0; background:#fff; }
    .xo-request { padding:108px 0; background:#e9efef; }
    .xo-request__visual { position:relative; min-height:650px; overflow:hidden; border-radius:28px; background:#181b1f; }
    .xo-request__visual img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .xo-request__visual::after { position:absolute; inset:0; background:linear-gradient(0deg,rgba(10,12,14,.78),transparent 58%); content:""; }
    .xo-request__caption { position:absolute; z-index:1; right:22px; bottom:22px; left:22px; color:#fff; }
    .xo-request__caption small { color:#ff7069; font-weight:700; letter-spacing:.08em; }
    .xo-request__caption h3 { margin-top:8px; color:#fff; }
    .xo-request__form { padding:38px; border-radius:27px; background:#fff; box-shadow:0 22px 65px rgba(35,44,48,.1); }
    .xo-request__contacts { display:flex; flex-wrap:wrap; gap:16px; margin:19px 0 27px; }
    .xo-request__contacts a { color:#20262b!important; font-weight:600; }
    .xo-terms { margin-top:17px; padding:15px 17px; border-radius:14px; background:#f6f4f0; color:var(--xo-muted); font-size:13px; line-height:1.55; }
    .xo-faq { padding:105px 0; background:#fff; }
    .xo-faq__intro { position:sticky; top:120px; }
    @media(max-width:1199px){.xo-hero__visual{min-height:540px}.xo-flow,.xo-audience__grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:991px){.xo-hero__grid,.xo-market__grid{grid-template-columns:1fr}.xo-hero__visual{min-height:570px}.xo-system__copy,.xo-proof__copy{padding:42px 0 0}.xo-request__form{margin-top:30px}.xo-faq__intro{position:static;margin-bottom:32px}}
    @media(max-width:767px){.xo-page{width:100%;max-width:100%;overflow-x:hidden}.xo-page .container{width:100vw!important;max-width:100vw!important;min-width:0!important;margin-right:0;margin-left:0;padding-right:16px;padding-left:16px;box-sizing:border-box}.xo-hero{padding:28px 0 62px}.xo-hero .breadcrumbs{display:none}.xo-hero__grid{display:block;width:calc(100vw - 32px)!important;max-width:calc(100vw - 32px)!important;margin-top:20px}.xo-hero__grid>*{width:calc(100vw - 32px)!important;max-width:calc(100vw - 32px)!important;min-width:0!important}.xo-hero h1{width:100%!important;max-width:100%!important;margin-right:0;font-size:40px;white-space:normal!important;overflow-wrap:break-word}.xo-hero__lead{width:100%!important;max-width:100%!important;white-space:normal!important;overflow-wrap:break-word}.xo-price{width:100%;max-width:100%}.xo-price__save{flex-basis:100%}.xo-hero__actions{display:grid;width:100%;margin-bottom:30px}.xo-hero__actions .tf-btn{width:100%;min-width:0;box-sizing:border-box}.xo-hero__facts{display:none}.xo-hero__visual{min-height:410px}.xo-hero__product{border-radius:22px}.xo-hero__label{right:12px;bottom:12px;left:12px;width:auto}.xo-benefits,.xo-system,.xo-automation,.xo-specs,.xo-market,.xo-proof,.xo-audience,.xo-series,.xo-request,.xo-faq{padding:72px 0}.xo-flow,.xo-audience__grid,.xo-proof__gallery{grid-template-columns:1fr}.xo-proof__photo:first-child{grid-column:auto}.xo-system__visual,.xo-request__visual,.xo-proof__photo,.xo-proof__photo:first-child{min-height:420px}.xo-request__form,.xo-market__price{padding:25px 20px}.xo-specs__row{grid-template-columns:1fr;gap:4px}.xo-specs__row strong{text-align:left}}
</style>
@endpush

@section('content')
<main id="wrapper" class="xo-page">
    <section class="xo-hero"><div class="container">
        <div class="breadcrumbs"><a href="/" class="text-caption-01 link">Главная</a><i class="icon icon-CaretRightThin"></i><a href="/akcii" class="text-caption-01 link">Акции</a><i class="icon icon-CaretRightThin"></i><p class="text-caption-01">KOTLOV XO Ceramic PRO</p></div>
        <div class="xo-hero__grid"><div>
            <span class="xo-hero__discount">−{{ $discountPercent }}% · ТОВАР В НАЛИЧИИ</span>
            <h1 class="mb-24">100 кВт.<br>Wi‑Fi уже внутри.</h1>
            <p class="xo-hero__lead mb-0">KOTLOV XO Ceramic PRO превращает совместимый водогрейный котёл в автоматизированную пеллетную систему. Интернет‑управление входит в акционную комплектацию, а не добавляется отдельно.</p>
            <div class="xo-price"><span class="xo-price__new">{{ number_format($product->price, 0, '.', ' ') }} BYN</span>@if($product->price_old > $product->price)<span class="xo-price__old">{{ number_format($product->price_old, 0, '.', ' ') }} BYN</span>@endif @if($saving > 0)<span class="xo-price__save">Экономия {{ number_format($saving, 0, '.', ' ') }} BYN</span>@endif</div>
            <div class="xo-hero__actions"><a href="#xo-request" class="tf-btn animate-btn" data-analytics-event="pellet_burner_promo_lead_click">Получить расчёт</a><a href="/{{ $product->category->slug }}/{{ $product->slug }}" class="tf-btn btn-white">Смотреть товар</a></div>
            <div class="xo-hero__facts"><span class="xo-hero__fact">30–100 кВт</span><span class="xo-hero__fact">Встроенный Wi‑Fi</span><span class="xo-hero__fact">Съёмная топка</span><span class="xo-hero__fact">Автоматическая очистка</span><span class="xo-hero__fact">Гарантия 24 месяца</span></div>
        </div><div class="xo-hero__visual"><a href="/{{ $product->category->slug }}/{{ $product->slug }}" class="xo-hero__product"><img loading="eager" src="{{ $product->image_url }}" alt="Пеллетная горелка KOTLOV XO Ceramic PRO 100 кВт"></a><div class="xo-hero__label"><small>АКЦИОННЫЙ ОСТАТОК</small><strong>Скидка 10% до продажи остатка</strong></div></div></div>
    </div></section>

    <section class="xo-benefits"><div class="container"><div class="xo-benefits__intro"><span class="xo-kicker">Почему Ceramic PRO</span><h2 class="xo-title mt-16 mb-18">Для котельной, которая должна работать</h2><p class="xo-lead mb-0">Не просто горелка, а комплект для автоматизации подачи топлива, розжига, модуляции и очистки.</p></div><div class="row g-16">
        @foreach ([
            ['01','Подвижные колосники','Жаропрочная сталь AISI 310S и каскадная очистка уменьшают объём ручного обслуживания.'],
            ['02','Шамотированная камера','Футеровка помогает поддерживать стабильную температуру в зоне горения при высокой нагрузке.'],
            ['03','Два розжигателя','Керамические элементы обеспечивают автоматический запуск; заявленный ресурс — до 10 000 розжигов.'],
            ['04','Съёмная топка','Современный сменный узел: после многолетней работы топку можно заменить целиком, не меняя всю горелку.'],
        ] as [$n,$heading,$text])<div class="col-md-6 col-xl-3"><article class="xo-benefit"><span class="xo-benefit__number">{{ $n }}</span><h3>{{ $heading }}</h3><p class="mb-0">{{ $text }}</p></article></div>@endforeach
    </div></div></section>

    <section class="xo-system"><div class="container"><div class="row align-items-center g-32"><div class="col-lg-6"><div class="xo-system__visual"><img loading="lazy" src="{{ $product->imageUrl(1) }}" alt="Конструкция KOTLOV XO Ceramic PRO 100 кВт"></div></div><div class="col-lg-6"><div class="xo-system__copy"><span class="xo-kicker">Модернизация котельной</span><h2 class="xo-title mt-16 mb-20">Сначала совместимость.<br>Потом монтаж.</h2><p class="xo-lead">Горелку можно применять в новых водогрейных котлах и при переоборудовании существующей котельной, но только после проверки всей системы.</p><div class="xo-system__list">
        @foreach(['Мощность, объём и противодавление топки','Размер дверцы и изготовление переходного фланца','Дымоход, тяга и температура дымовых газов','Место для бункера, шнека и сервисного доступа','Электропитание, насосы, защита и автоматика'] as $item)<div class="xo-system__item"><span>✓</span><strong>{{ $item }}</strong></div>@endforeach
    </div><p class="mt-24 mb-0"><a href="/blog/pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro" class="fw-semibold text-decoration-underline link">Прочитать технический разбор →</a></p></div></div></div></div></section>

    <section class="xo-automation"><div class="container"><span class="xo-kicker">Автоматический цикл</span><h2 class="xo-title mt-16 mb-18" style="max-width:850px">От подачи пеллет до безопасного затухания</h2><p class="xo-lead xo-automation__lead mb-0" style="max-width:760px">Контроллер управляет последовательностью работы и поддерживает температуру теплоносителя. Встроенный Wi‑Fi‑блок позволяет контролировать систему через интернет без покупки отдельного модуля.</p><div class="xo-flow">
        @foreach([['01','Подача','Шнек дозирует пеллеты из бункера.'],['02','Розжиг','Керамические элементы запускают горение.'],['03','Модуляция','Подача топлива и воздуха меняется по нагрузке.'],['04','Очистка','Подвижные колосники очищают рабочую поверхность.']] as [$n,$heading,$text])<div class="xo-flow__item"><small>ЭТАП {{ $n }}</small><h3>{{ $heading }}</h3><p class="mb-0">{{ $text }}</p></div>@endforeach
    </div></div></section>

    <section class="xo-specs"><div class="container"><div class="row g-40 align-items-start"><div class="col-lg-5"><span class="xo-kicker">Ключевые параметры</span><h2 class="xo-title mt-16 mb-20">XO Ceramic PRO 100</h2><p class="xo-lead">Характеристики сверены с каталогом KOTLOV XO 2025. Итоговая схема и настройка зависят от котла и объекта.</p><a href="/{{ $product->category->slug }}/{{ $product->slug }}" class="tf-btn animate-btn mt-20">Полная карточка товара</a></div><div class="col-lg-7"><div class="xo-specs__table">
        @foreach([
            ['Диапазон мощности','30–100 кВт'],['Ориентировочная площадь','800–1500 м²'],['Средняя электрическая мощность','75 Вт'],['Мощность при розжиге','475 Вт'],['Габариты (Д × В × Ш)','877 × 532 × 430 мм'],['Размер топочной части (В × Ш)','268 × 288 мм'],['Расход дымовых газов при 200 °C','до 335 м³/ч'],['Масса горелки','71 кг'],['Топливо','древесные пеллеты Ø 6–8 мм'],['Гарантия','24 месяца'],
        ] as [$label,$value])<div class="xo-specs__row"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>@endforeach
    </div><p class="xo-specs__note">Площадь указана как ориентир из каталога. Для коммерческого объекта оборудование подбирают по расчётной тепловой нагрузке. КПД сгорания до 98% не равен сезонному КПД всей котельной: результат зависит от котла, топлива, дымохода и настройки.</p></div></div></div></section>

    <section class="xo-market"><div class="container"><span class="xo-kicker">Сравнение по комплекту</span><h2 class="xo-title mt-16 mb-18" style="max-width:920px">Цена 100 кВт — ещё не вся картина</h2><p class="xo-lead xo-automation__lead mb-0" style="max-width:850px">Сравнивать нужно не только мощность, но и то, что уже включено: автоматику, очистку, камеру сгорания, розжиг и дистанционное управление.</p><div class="xo-market__grid"><div class="xo-market__price"><div><small>АКЦИОННАЯ ЦЕНА KOTLOV</small><strong>12 960 BYN</strong><p class="mb-0">На 2 040 BYN ниже открытой цены 15 000 BYN на сопоставимую самоочищающуюся горелку 100 кВт.</p></div><p class="mb-0"><strong style="font-size:20px;letter-spacing:0">И Wi‑Fi уже в комплекте.</strong></p></div><div><div class="xo-market__compare">
        @foreach([['✓','Встроенное Wi‑Fi‑управление — не отдельная опция.'],['✓','Съёмная топка: в будущем меняется весь теплонагруженный узел.'],['✓','Каскадная самоочистка с подвижными колосниками AISI 310S.'],['✓','Шамотированная камера и два керамических розжигателя.'],['✓','Платформа Ceramic PRO с более чем 10‑летней историей развития.']] as [$mark,$text])<div class="xo-market__item"><span>{{ $mark }}</span><p class="mb-0">{{ $text }}</p></div>@endforeach
    </div><p class="xo-market__note mb-0">Сравнение выполнено 11.09.2026 по открытой карточке VENMA Comfort R100: 15 000 BYN, интернет‑модуль указан как дополнительная опция. Цены и комплектации других продавцов могут меняться. Среди проверенных открытых предложений это единственная горелка, в которой мы одновременно подтвердили съёмную топку, встроенный Wi‑Fi, каскадную самоочистку и два керамических розжигателя.</p></div></div></div></section>

    <section class="xo-proof"><div class="container"><div class="row align-items-center g-40"><div class="col-lg-6"><div class="xo-proof__gallery"><figure class="xo-proof__photo mb-0"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-commercial-boiler-latvia.jpg') }}" alt="Пеллетная горелка платформы Ceramic PRO в коммерческой котельной в Латвии"><figcaption class="xo-proof__badge"><small>РЕАЛЬНАЯ КОТЕЛЬНАЯ</small><strong>Латвия · коммерческий объект · AGB</strong></figcaption></figure><figure class="xo-proof__photo mb-0"><img loading="lazy" src="{{ asset('img/promotions/oxi-ceramic-100-kazakhstan-2018.jpg') }}" alt="Две пеллетные горелки OXI Ceramic 100 кВт в котлах Kronas в Казахстане"><figcaption class="xo-proof__badge"><small>РЕАЛЬНАЯ КОТЕЛЬНАЯ · 2018</small><strong>Казахстан · 2 × OXI Ceramic 100 кВт</strong></figcaption></figure><figure class="xo-proof__photo mb-0"><img loading="lazy" src="{{ asset('img/promotions/hotta-oxi-ceramic-100-minsk-2018.jpg') }}" alt="Пеллетная горелка HOTTA OXI Ceramic 100 кВт с котлом Маяк в Минске"><figcaption class="xo-proof__badge"><small>БЕЛАРУСЬ · 2018</small><strong>Минск · HOTTA / OXI Ceramic 100 кВт · котёл «Маяк»</strong></figcaption></figure></div></div><div class="col-lg-6"><div class="xo-proof__copy"><span class="xo-kicker">Технология в работе</span><h2 class="xo-title mt-16 mb-20">Не макет.<br>Годы реальной работы.</h2><p class="xo-lead">Эти объекты показывают преемственность одной технологической линии: AGB в коммерческой котельной в Латвии, две OXI Ceramic 100 кВт в котлах Kronas в Казахстане и OXI Ceramic 100 кВт, поставлявшаяся в Беларуси под брендом HOTTA. Два объекта 2018 года дополняют историю текущего поколения.</p><div class="xo-proof__facts"><div class="xo-proof__fact"><strong>Промышленный формат</strong><p class="cl-text-2 mb-0 mt-4">Работа с большими водогрейными котлами и внешней системой подачи пеллет.</p></div><div class="xo-proof__fact"><strong>Проверенная платформа</strong><p class="cl-text-2 mb-0 mt-4">Технология Ceramic имеет многолетнюю историю эксплуатации, а текущее поколение получило новую автоматику и Wi‑Fi.</p></div></div><p class="mt-20 mb-0 cl-text-2" style="font-size:13px">Эти объекты не заявлены как монтажи KOTLOV; фото показывают ранние версии родственной платформы.</p></div></div></div></div></section>

    <section class="xo-audience"><div class="container"><div class="text-center mx-auto" style="max-width:800px"><span class="xo-kicker">Практическое применение</span><h2 class="xo-title mt-16 mb-18">Где нужна мощность 100 кВт</h2><p class="xo-lead mb-0">Подходит для объектов с понятным графиком потребления тепла и возможностью организовать хранение пеллет.</p></div><div class="xo-audience__grid">
        @foreach([['01','Производство','Цеха и мастерские с продолжительным отопительным сезоном.'],['02','Склады и логистика','Большие объёмы воздуха и регулярная тепловая нагрузка.'],['03','СТО и сервисы','Рабочие помещения, где важна автоматизация котельной.'],['04','Теплицы и фермы','Объекты с высокой ценой остановки отопления.']] as [$n,$heading,$text])<article class="xo-audience__item"><strong>{{ $n }}</strong><h3>{{ $heading }}</h3><p class="mb-0">{{ $text }}</p></article>@endforeach
    </div></div></section>

    @if($seriesProducts->isNotEmpty())<section class="xo-series"><div class="container"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-20 mb-40"><div><span class="xo-kicker">Другие мощности</span><h2 class="xo-title mt-16 mb-0">Линейка Ceramic PRO</h2></div><a href="/pelletnye-gorelki" class="tf-btn btn-white btn-stroke">Все пеллетные горелки</a></div><div class="tf-grid-layout sm-col-2 lg-col-3 xl-col-3">@foreach($seriesProducts as $seriesProduct)@include('partials.product-card',['product'=>$seriesProduct])@endforeach</div></div></section>@endif

    <section class="xo-request" id="xo-request"><div class="container"><div class="row align-items-center g-40"><div class="col-lg-5"><div class="xo-request__visual"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-ceramic-pro-banner.png') }}" alt="Серия пеллетных горелок KOTLOV XO"><div class="xo-request__caption"><small>ИНЖЕНЕРНЫЙ ПОДБОР KOTLOV</small><h3 class="mb-0">Проверим котёл, дымоход и комплектацию до заказа</h3></div></div></div><div class="col-lg-7"><form method="POST" action="{{ route('install-requests.store') }}" class="xo-request__form kv-form" data-analytics-form="pellet_burner_promo" novalidate>@csrf<x-form-protection/><input type="hidden" name="specialization" value="engineering"><input type="hidden" name="source" value="pellet_burner_promo"><input type="hidden" name="product_id" value="{{ $product->id }}"><span class="xo-kicker">Расчёт для вашего объекта</span><h2 class="mt-14 mb-10">Получить предложение со скидкой</h2><p class="cl-text-2 mb-0">Оставьте контакты и данные котельной. Инженер уточнит совместимость, состав комплекта и монтаж.</p><div class="xo-request__contacts"><a href="tel:+375293544041">+375 (29) 354-40-41</a><a href="mailto:info@kotlov.by">info@kotlov.by</a></div>
        @if(session('success'))<div class="alert alert-success mb-20" role="status">{{ session('success') }}</div>@endif @if($errors->any())<div class="kv-summary kv-summary--visible mb-20" role="alert">Пожалуйста, проверьте обязательные поля.</div>@endif
        <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="xo-name">Имя *</label><input id="xo-name" name="customer_name" type="text" value="{{ old('customer_name') }}" placeholder="Ваше имя" data-required="1" data-label="Имя"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="xo-phone">Телефон *</label><input id="xo-phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" placeholder="+375 (XX) XXX-XX-XX" data-required="1" data-label="Телефон"></fieldset></div>
        <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="xo-email">Email</label><input id="xo-email" name="customer_email" type="email" value="{{ old('customer_email') }}" placeholder="mail@example.com"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="xo-city">Город</label><input id="xo-city" name="city" type="text" value="{{ old('city') }}" placeholder="Город или район"></fieldset></div>
        <fieldset class="tf-field mb-20"><label class="tf-lable fw-medium" for="xo-description">Что известно о котельной</label><textarea id="xo-description" name="description" rows="5" placeholder="Котёл, площадь, топливо, дымоход, требуемая мощность...">{{ old('description') }}</textarea></fieldset><button type="submit" class="tf-btn animate-btn w-100">Отправить заявку инженеру</button><div class="xo-terms">Скидка 10% действует на KOTLOV XO Ceramic PRO 100 кВт до продажи выделенного акционного остатка. Количество ограничено. Наличие, комплектация, монтаж и окончательные условия заказа уточняются менеджером.</div>
    </form></div></div></div></section>

    <section class="xo-faq"><div class="container"><div class="row g-40"><div class="col-lg-5"><div class="xo-faq__intro"><span class="xo-kicker">Перед заказом</span><h2 class="xo-title mt-16 mb-16">Частые вопросы</h2><p class="xo-lead">Коротко о совместимости, расходе и условиях предложения.</p></div></div><div class="col-lg-7"><div id="accordion-xo-promo">@foreach($faq as $question=>$answer)<div class="accordion-item_v2"><div class="accordion-action {{ $loop->first?'':'collapsed' }} lh-24 fw-medium" data-bs-target="#xo-faq-{{ $loop->index }}" data-bs-toggle="collapse" aria-expanded="{{ $loop->first?'true':'false' }}" role="button"><span>{{ $question }}</span><span class="icon ic-accordion-custom cl-2"></span></div><div id="xo-faq-{{ $loop->index }}" class="collapse {{ $loop->first?'show':'' }}" data-bs-parent="#accordion-xo-promo"><p class="faq-content cl-text-2">{{ $answer }}</p></div></div>@endforeach</div></div></div></div></section>
</main>
@endsection
