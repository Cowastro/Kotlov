@extends('layouts.amerce')

@if ($category->slug === 'pelletnye-gorelki')
    @push('styles')
        <style>
            .pellet-category-intro{margin-top:28px}.pellet-category-intro__panel{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(290px,.65fr);gap:36px;align-items:center;padding:34px;border:1px solid #e7e7e7;border-radius:24px;background:linear-gradient(135deg,#111 0%,#202020 72%,#351412 100%);color:#fff;box-shadow:0 18px 50px rgba(0,0,0,.08)}.pellet-category-intro__eyebrow{display:inline-flex;margin-bottom:10px;color:#ff6b5f;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.pellet-category-intro h2{margin:0 0 12px;color:#fff;font-size:clamp(27px,3vw,42px);line-height:1.08}.pellet-category-intro p{max-width:780px;margin:0;color:rgba(255,255,255,.74);font-size:15px;line-height:1.55}.pellet-category-intro__points{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.pellet-category-intro__points span{padding:7px 11px;border:1px solid rgba(255,255,255,.14);border-radius:999px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.85);font-size:12px}.pellet-category-intro__actions{display:flex;flex-direction:column;gap:10px}.pellet-category-intro__actions .tf-btn{justify-content:center;width:100%;min-height:48px}.pellet-category-intro__actions .btn-white{border-color:rgba(255,255,255,.3);background:#fff;color:#111}.pellet-power-filter{display:flex;align-items:center;gap:9px;margin-top:18px;overflow-x:auto;padding-bottom:3px}.pellet-power-filter__label{flex:0 0 auto;margin-right:3px;font-weight:650}.pellet-power-filter__item{display:inline-flex;flex:0 0 auto;align-items:center;gap:7px;padding:9px 13px;border:1px solid #dedede;border-radius:999px;background:#fff;color:#111;transition:.2s}.pellet-power-filter__item:hover,.pellet-power-filter__item.is-active{border-color:#111;background:#111;color:#fff}.pellet-power-filter__count{display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:#f1f1f1;color:#666;font-size:10px}.pellet-power-filter__item.is-active .pellet-power-filter__count{background:rgba(255,255,255,.16);color:#fff}.pellet-category-links{display:flex;flex-wrap:wrap;gap:10px 22px;margin-top:17px}.pellet-category-links a{color:#111;text-decoration:underline;text-underline-offset:4px}.pellet-comparison{margin-top:12px}.pellet-comparison__wrap{overflow-x:auto;border:1px solid #e7e7e7;border-radius:20px;background:#fff}.pellet-comparison table{width:100%;min-width:760px;border-collapse:collapse}.pellet-comparison th,.pellet-comparison td{padding:17px 20px;border-bottom:1px solid #ededed;text-align:left;vertical-align:middle}.pellet-comparison th{background:#f7f7f7;font-size:12px;text-transform:uppercase;letter-spacing:.04em}.pellet-comparison tbody tr:last-child td{border-bottom:0}.pellet-comparison__model{display:flex;align-items:center;gap:13px;min-width:260px}.pellet-comparison__model img{width:64px;height:64px;border-radius:10px;background:#f5f5f5;object-fit:contain}.pellet-comparison__model a{color:#111;font-weight:650}.pellet-comparison__price{font-weight:700;white-space:nowrap}.pellet-category-faq{margin-top:52px;scroll-margin-top:100px}.pellet-category-faq__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.pellet-category-faq details{padding:20px 22px;border:1px solid #e7e7e7;border-radius:16px;background:#fff}.pellet-category-faq summary{cursor:pointer;font-weight:650;list-style:none}.pellet-category-faq summary::-webkit-details-marker{display:none}.pellet-category-faq p{margin:12px 0 0;color:#6f6f6f;line-height:1.55}@media(max-width:767px){.pellet-category-intro__panel{grid-template-columns:1fr;gap:20px;padding:24px}.pellet-category-intro__points{display:none}.pellet-power-filter{margin-right:-15px;padding-right:15px}.pellet-category-faq__grid{grid-template-columns:1fr}.section-page-title.flat-spacing-2{padding-top:44px;padding-bottom:10px}}
        </style>
    @endpush
@endif

@if (in_array($category->slug, ['dymohody', 'truby-i-fitingi', 'bani-i-sauny', 'schetchiki-gaza'], true))
    @push('styles')
        <style>
            .catalog-brand-note{margin-top:22px}.catalog-brand-note__inner{display:flex;align-items:center;justify-content:space-between;gap:28px;padding:22px 26px;border:1px solid var(--line);border-radius:18px;background:var(--bg)}.catalog-brand-note__eyebrow{display:block;margin-bottom:5px;color:var(--text-2);font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}.catalog-brand-note h2{margin:0 0 5px;font-size:22px;line-height:1.2}.catalog-brand-note p{max-width:790px;margin:0;color:var(--text-2);font-size:13px;line-height:1.5}.catalog-brand-note__actions{display:flex;flex:0 0 auto;gap:10px}.catalog-brand-note .tf-btn{flex:0 0 auto;min-width:190px;justify-content:center}@media(max-width:767px){.catalog-brand-note{margin-top:16px}.catalog-brand-note__inner{align-items:stretch;flex-direction:column;gap:16px;padding:19px}.catalog-brand-note h2{font-size:20px}.catalog-brand-note__actions{flex-direction:column}.catalog-brand-note .tf-btn{box-sizing:border-box;width:100%;min-width:0}}
        </style>
    @endpush
@endif

@if (in_array($category->slug, ['schetchiki-gaza', 'teplonositeli'], true))
    @push('styles')
        <style>
            .gas-meter-selector-cta{margin-top:24px}.gas-meter-selector-cta__inner{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:30px;align-items:center;padding:28px 32px;border-radius:22px;background:linear-gradient(135deg,#151515 0%,#292929 100%);color:#fff;box-shadow:0 18px 45px rgba(0,0,0,.12)}.gas-meter-selector-cta__eyebrow{display:block;margin-bottom:6px;color:#ff696d;font-size:12px;font-weight:750;letter-spacing:.06em;text-transform:uppercase}.gas-meter-selector-cta h2{margin:0 0 8px;color:#fff;font-size:clamp(24px,3vw,34px);line-height:1.12}.gas-meter-selector-cta p{max-width:760px;margin:0;color:rgba(255,255,255,.72);font-size:14px;line-height:1.55}.gas-meter-selector-cta__facts{display:flex;flex-wrap:wrap;gap:7px;margin-top:15px}.gas-meter-selector-cta__facts span{padding:6px 10px;border:1px solid rgba(255,255,255,.14);border-radius:999px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.9);font-size:12px}.gas-meter-selector-cta__actions{display:flex;min-width:270px;flex-direction:column;gap:9px}.gas-meter-selector-cta__button{display:flex;align-items:center;justify-content:center;min-height:52px;padding:12px 22px;border-radius:999px;background:#df4145;color:#fff;font-weight:750;line-height:1.2;text-align:center}.gas-meter-selector-cta__button:hover{background:#c93438;color:#fff}.gas-meter-selector-cta__all{color:rgba(255,255,255,.78);font-size:13px;text-align:center;text-decoration:underline;text-underline-offset:4px}.gas-meter-selector-cta__all:hover{color:#fff}
            .gas-meter-guide{padding-top:12px;padding-bottom:72px}.gas-meter-guide__lead{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(300px,.8fr);gap:28px;padding:32px;border:1px solid var(--line);border-radius:22px;background:var(--bg)}.gas-meter-guide__lead h2{margin:0 0 14px;font-size:clamp(27px,3vw,40px);line-height:1.12}.gas-meter-guide__lead p{margin:0;color:var(--text-2);line-height:1.65}.gas-meter-guide__checklist{display:grid;gap:10px;margin:0;padding:0;list-style:none}.gas-meter-guide__checklist li{padding:13px 15px;border-radius:12px;background:#fff;font-size:14px}.gas-meter-guide__checklist strong{display:block;margin-bottom:2px}.gas-meter-guide__types{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:24px}.gas-meter-guide__type{padding:22px;border:1px solid var(--line);border-radius:16px;background:#fff}.gas-meter-guide__type h3{margin:0 0 8px;font-size:19px}.gas-meter-guide__type p{margin:0;color:var(--text-2);font-size:14px;line-height:1.55}.gas-meter-guide__type a{display:inline-block;margin-top:13px;color:#111;font-weight:650;text-decoration:underline;text-underline-offset:4px}.gas-meter-guide__faq{margin-top:44px}.gas-meter-guide__faq h2{margin-bottom:18px}.gas-meter-guide__faq-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.gas-meter-guide__faq details{padding:19px 21px;border:1px solid var(--line);border-radius:14px;background:#fff}.gas-meter-guide__faq summary{cursor:pointer;font-weight:650;list-style:none}.gas-meter-guide__faq summary::-webkit-details-marker{display:none}.gas-meter-guide__faq p{margin:11px 0 0;color:var(--text-2);line-height:1.55}.gas-meter-guide__note{margin-top:20px;padding:16px 18px;border-left:3px solid #111;background:#f7f7f7;color:var(--text-2);font-size:13px;line-height:1.55}@media(max-width:767px){.gas-meter-guide{padding-bottom:48px}.gas-meter-guide__lead{grid-template-columns:1fr;padding:23px}.gas-meter-guide__types,.gas-meter-guide__faq-grid{grid-template-columns:1fr}}
            @media(max-width:767px){.gas-meter-selector-cta{margin-top:16px}.gas-meter-selector-cta__inner{grid-template-columns:1fr;gap:20px;padding:23px 20px}.gas-meter-selector-cta__facts{display:none}.gas-meter-selector-cta__actions{min-width:0}.gas-meter-selector-cta__button{width:100%}}
        </style>
    @endpush
@endif

@section('content')
<main id="wrapper">

    {{-- Заголовок страницы --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @if ($category->parent_id && $category->parent)
                        <a href="/{{ $category->parent->slug }}" class="text-caption-01 cl-text-3 link">
                            {{ $category->parent->name }}
                        </a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @endif
                    @if ($specificationLanding !== null)
                        <a href="{{ url('/'.$category->slug) }}" class="text-caption-01 cl-text-3 link">{{ $categorySchemaName }}</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <p class="text-caption-01">{{ $specificationLanding['short'] }}</p>
                    @else
                        <p class="text-caption-01">{{ $heaterName ?? $category->name }}</p>
                    @endif
                </div>
                <h1>{{ match (true) {
                    $fireplaceType !== null => (match ($fireplaceType) {
                        'insert' => 'Электроочаги',
                        'kit' => 'Каминокомплекты',
                        'portal' => 'Порталы для электрокаминов',
                    }).' '.$cityIn,
                    $isMinskFireplaceCatalog => 'Печи-камины для дома и дачи в Минске',
                    $isElectricFireplaceCatalog => 'Электрокамины '.$cityIn,
                    $isBoilerHub && $selectedSubcategory !== null => $selectedSubcategory->h1 ?: $selectedSubcategory->name,
                    $specificationHeading !== null => $specificationHeading,
                    default => $category->h1 ?: $category->name,
                } }}</h1>
                @if ($catalogIntro ?? $category->content)
                    <p class="text-body-1 cl-text-2">
                        {{ $catalogIntro ?? Str::limit(strip_tags(html_entity_decode($category->content, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 200) }}
                    </p>
                @endif
                @if ($isMinskFireplaceCatalog)
                    @php
                        $inStock = request('in_stock') === '1';
                        $stockFilter = request()->except('in_stock', 'page');
                        if (! $inStock) {
                            $stockFilter['in_stock'] = '1';
                        }
                    @endphp
                    <div class="d-flex flex-wrap justify-content-center align-items-center gap-3 mt-3">
                        <a href="{{ url()->current() }}{{ $stockFilter ? '?'.http_build_query($stockFilter) : '' }}#catalog-products"
                            class="tf-btn small {{ $inStock ? 'btn-stroke' : '' }}">
                            {{ $inStock ? 'Показать все товары' : 'В наличии' }}
                        </a>
                        <div class="text-caption-01 cl-text-2">
                            <div>Офис KOTLOV в Минске: <a class="link" href="{{ route('contacts') }}">{{ $minskFireplaceOfficeAddress }}</a></div>
                            <div>Самовывоз со склада KOTLOV в Минске: <a class="link" href="{{ url('/dostavka') }}">{{ config('shop.delivery_methods.pickup.address') }}</a> · по согласованию</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    @include('partials.catalog-city-storefront')

    @if ($isBoilerHub)
        @include('partials.boiler-catalog-navigation')
    @elseif ($isWaterHeaterHub)
        @include('partials.water-heater-navigation')
    @elseif ($showElectricFireplaceNavigation)
        @include('partials.electric-fireplace-navigation')
    @elseif ($subcategories->isNotEmpty() && ! request()->filled('subcategory'))
        @include('partials.catalog-subcategory-navigation', [
            'showSubcategoryStockToggle' => ! $isMinskFireplaceCatalog && ($specificationHeading === null || $category->slug === 'radiatory'),
        ])
    @endif

    @if ($category->slug === 'schetchiki-gaza')
        <section class="gas-meter-selector-cta" aria-labelledby="gas-meter-selector-cta-title">
            <div class="container">
                <div class="gas-meter-selector-cta__inner">
                    <div>
                        <span class="gas-meter-selector-cta__eyebrow">Быстрый подбор БелОМО</span>
                        <h2 id="gas-meter-selector-cta-title">Не знаете, какой счётчик выбрать?</h2>
                        <p>Ответьте на несколько простых вопросов. Подборщик учтёт расход, направление газа, расстояние между патрубками и место установки, затем покажет подходящую модель, цену и наличие.</p>
                        <div class="gas-meter-selector-cta__facts" aria-hidden="true">
                            <span>Плита, колонка или котёл</span>
                            <span>Левый или правый</span>
                            <span>110, 200 или 250 мм</span>
                        </div>
                    </div>
                    <div class="gas-meter-selector-cta__actions">
                        <a class="gas-meter-selector-cta__button" href="{{ \App\Support\Catalog\GasMeterSelectionGuide::PAGE_URL }}">Подобрать счётчик за 1 минуту</a>
                        <a class="gas-meter-selector-cta__all" href="#catalog-products">Или посмотреть все счётчики</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($isChimneySection ?? false)
        @include('partials.chimney-selector-cta')
    @endif

    @if (in_array($category->slug, ['dymohody', 'dymohody-mono', 'dymohody-sendvich'], true))
        <section class="catalog-brand-note" aria-labelledby="tis-standard-title">
            <div class="container">
                <div class="catalog-brand-note__inner">
                    <div>
                        <span class="catalog-brand-note__eyebrow">Новая система дымоходов</span>
                        <h2 id="tis-standard-title">ТиС «Стандарт 30», «Стандарт 50» и «Феррит 1 мм»</h2>
                        <p>Удобные прайсы по элементам и диаметрам: «Стандарт» из AISI 304 и банный «Феррит 1 мм» из AISI 430, быстрый переход между типоразмерами. Поставка под заказ за 5–7 рабочих дней.</p>
                    </div>
                    <div class="catalog-brand-note__actions">
                        <a href="{{ route('chimneys.tis-standard-30') }}" class="tf-btn btn-white btn-stroke">Прайс Стандарт 30</a>
                        <a href="{{ route('chimneys.tis-standard-50') }}" class="tf-btn btn-white btn-stroke">Прайс Стандарт 50</a>
                        <a href="{{ route('chimneys.tis-ferrit-1mm') }}" class="tf-btn btn-white btn-stroke">Прайс Феррит 1 мм</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($category->slug === 'pelletnye-gorelki')
        @include('partials.pellet-burner-category-intro')
    @endif

    @if ($category->slug === 'teplovyie-nasosyi')
        @include('partials.heat-pump-category-intro')
    @endif

    @if ($saunaPickerSlug !== null)
        @include('partials.sauna-room-volume-filter')
    @endif

    @if (in_array($category->slug, ['drovyanye-pechi-dlya-bani', 'drovianye-peci-bannye'], true))
        @include('partials.sauna-stove-quick-filters', ['hideMaximumVolume' => $isSaunaStoveCatalog])
    @endif

    @if ($category->slug === 'elektrokamenki')
        @include('partials.electric-sauna-heater-quick-filters')
    @endif

    @include('partials.catalog-specification-search')

    @if (! $isMinskFireplaceCatalog && ! $isStoveHeatingCatalog)
        @include('partials.catalog-ecokamin-promo')
    @endif

    @if (in_array($category->slug, ['pechki', 'pechi-kaminy', 'pechi', 'peci-drovianye-otopitelnye', 'burzhuiki-pechi', 'dlya-dachi', 'kaminy', 'topki', 'elektrokamini'], true))
        @include('partials.stove-fireplace-quick-filters')
    @endif

    @if (($chimneyDiameterGroups ?? collect())->isNotEmpty())
        @include('partials.chimney-diameter-quick-filters', ['isMonoQuickFilter' => false])
    @endif

    @if (($monoChimneyDiameterGroups ?? collect())->isNotEmpty())
        @include('partials.chimney-diameter-quick-filters', ['isMonoQuickFilter' => true, 'chimneyDiameterGroups' => $monoChimneyDiameterGroups])
    @endif

    @if (($chimneySteelGroups ?? collect())->isNotEmpty())
        @include('partials.chimney-steel-quick-filters')
    @endif

    @if ($catalogSpotlight)
        <section class="catalog-brand-note" aria-labelledby="catalog-brand-title">
            <div class="container">
                <div class="catalog-brand-note__inner">
                    <div>
                        <span class="catalog-brand-note__eyebrow">{{ $catalogSpotlight['eyebrow'] }}</span>
                        <h2 id="catalog-brand-title">{{ $catalogSpotlight['title'] }}</h2>
                        <p>{{ $catalogSpotlight['text'] }}</p>
                    </div>
                    <a href="{{ $catalogSpotlight['url'] }}" class="tf-btn btn-white btn-stroke">
                        {{ $catalogSpotlight['button'] }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- Каталог --}}
    @if ($category->slug === \App\Services\HeatPumpProductPresenter::ROOT && $selectedSubcategory === null)
        <div class="container d-flex flex-wrap align-items-center gap-3 mt-3">
            <a href="{{ route('heat-pumps.installation') }}#heat-pump-request" class="fw-semibold link" data-analytics-event="heat_pump_lead_click">Нужен расчёт по теплопотерям?</a>
            @if ($subcategories->contains('slug', \App\Services\HeatPumpProductPresenter::PARTS))
                <a href="{{ url('/'.\App\Services\HeatPumpProductPresenter::PARTS) }}" class="link">Комплектующие для тепловых насосов →</a>
            @endif
        </div>
    @endif

    <section class="flat-spacing{{ $isMinskFireplaceCatalog || $isStoveHeatingCatalog || $isElectricFireplaceCatalog || $specificationHeading !== null ? ' pt-3' : '' }}" id="catalog-products">
        <div class="container">
            <div class="row">

                {{-- Сайдбар с фильтрами --}}
                <div class="col-xl-3">
                    <div class="canvas-sidebar sidebar-filter canvas-filter left">
                        <div class="canvas-wrapper">
                            <div class="canvas-header">
                                <h4 class="title d-none d-xl-block">Фильтры</h4>
                                <h5 class="title d-xl-none">Фильтры</h5>
                                <span class="icon-X2 fs-24 close-filter d-xl-none"></span>
                            </div>
                            <div class="canvas-body">

                                {{-- Подкатегории --}}
                                @if (! $isBoilerHub && ! $isWaterHeaterHub && $subcategories->isNotEmpty())
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#subcategories" role="button"
                                            data-bs-toggle="collapse" aria-expanded="true">
                                            <h6>Подкатегории</h6>
                                            <span class="icon icon-CaretDown"></span>
                                        </div>
                                        <div id="subcategories" class="collapse show">
                                            <ul class="collapse-body filter-group-check group-category">
                                                <li class="list-item">
                                                    <a href="?{{ http_build_query(array_merge(request()->except('subcategory'), [])) }}"
                                                        class="label link {{ !request('subcategory') ? 'fw-semibold' : '' }}">
                                                        <span class="cate-text">Все</span>
                                                        <span class="count">({{ $allProductsCount }})</span>
                                                    </a>
                                                </li>
                                                @foreach ($subcategories as $sub)
                                                    <li class="list-item">
                                                        <a href="?{{ http_build_query(array_merge(request()->except('subcategory'), ['subcategory' => $sub->slug])) }}"
                                                            class="label link {{ request('subcategory') == $sub->slug ? 'fw-semibold' : '' }}">
                                                            <span class="cate-text">{{ $sub->name }}</span>
                                                            <span class="count">({{ $sub->products_count }})</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="br-line"></div>
                                @endif

                                {{-- Фильтр по цене --}}
                                <div class="widget-facet">
                                    <div class="facet-title" data-bs-target="#price" role="button"
                                        data-bs-toggle="collapse" aria-expanded="true">
                                        <h6>Цена (BYN)</h6>
                                        <span class="icon icon-CaretDown"></span>
                                    </div>
                                    <div id="price" class="collapse show">
                                        <div class="collapse-body widget-price filter-price">
                                            <div class="price-val-range" id="price-value-range"
                                                data-min="{{ $priceMin }}"
                                                data-max="{{ $priceMax }}"
                                                data-current-min="{{ request('price_min', $priceMin) }}"
                                                data-current-max="{{ request('price_max', $priceMax) }}">
                                            </div>
                                            <div class="price-box tf-grid-layout tf-col-2">
                                                <div class="box-wrap">
                                                    <div class="price-val_wrap">
                                                        <input class="price-val" id="price-min-value" type="number"
                                                            inputmode="numeric" min="{{ $priceMin }}" max="{{ $priceMax }}"
                                                            value="{{ request('price_min') }}"
                                                            placeholder="От"
                                                            style="width:100%;border:0;background:transparent;outline:none;">
                                                    </div>
                                                </div>
                                                <div class="box-wrap">
                                                    <div class="price-val_wrap">
                                                        <input class="price-val" id="price-max-value" type="number"
                                                            inputmode="numeric" min="{{ $priceMin }}" max="{{ $priceMax }}"
                                                            value="{{ request('price_max') }}"
                                                            placeholder="До"
                                                            style="width:100%;border:0;background:transparent;outline:none;">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="tf-btn btn-white btn-stroke w-100 mt-12"
                                                onclick="applyPriceFilter()">
                                                Применить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="br-line"></div>

                                {{-- Наличие --}}
                                <div class="widget-facet">
                                    <div class="facet-title" data-bs-target="#availability" role="button"
                                        data-bs-toggle="collapse" aria-expanded="true">
                                        <h6>Наличие</h6>
                                        <span class="icon icon-CaretDown"></span>
                                    </div>
                                    <div id="availability" class="collapse show">
                                        <ul class="collapse-body filter-group-check">
                                            <li class="list-item">
                                                <input type="radio" name="in_stock" class="tf-check style-2"
                                                    id="inStock" value="1"
                                                    {{ request('in_stock') == '1' ? 'checked' : '' }}
                                                    onchange="applyFilter('in_stock', '1')">
                                                <label for="inStock" class="label">
                                                    <span class="cate-text">В наличии</span>
                                                </label>
                                            </li>
                                            <li class="list-item">
                                                <input type="radio" name="in_stock" class="tf-check style-2"
                                                    id="allStock" value=""
                                                    {{ !request('in_stock') ? 'checked' : '' }}
                                                    onchange="applyFilter('in_stock', '')">
                                                <label for="allStock" class="label">
                                                    <span class="cate-text">Все товары</span>
                                                </label>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="br-line"></div>

                                {{-- Производитель --}}
                                @include('partials.catalog-brand-filter', ['brands' => $brands])

                                @if ($hasNicheWidths || $nicheWidthMax !== null)
                                    <div class="widget-facet">
                                        <form action="{{ url()->current() }}#catalog-products" method="get">
                                            @foreach (\Illuminate\Support\Arr::dot(request()->except('page', 'niche_width_max')) as $key => $value)
                                                <input type="hidden" name="{{ preg_replace('/\.([^.]*)/', '[$1]', $key) }}" value="{{ $value }}">
                                            @endforeach
                                            <h6 class="mb-2"><label for="niche-width-max">Ширина ниши до, мм</label></h6>
                                            <div class="d-flex align-items-center gap-2">
                                                <input id="niche-width-max" class="w-50" name="niche_width_max" type="number" inputmode="numeric"
                                                    min="1" max="10000" step="1" placeholder="Например, 740" value="{{ $nicheWidthMax }}">
                                                <button type="submit" class="tf-btn btn-outline">Подобрать</button>
                                            </div>
                                            <p class="text-caption-01 cl-text-2 mt-2">Сверьте также высоту и глубину проёма.</p>
                                        </form>
                                    </div>
                                    <div class="br-line"></div>
                                @endif

                                @if ($isElectricFireplaceCatalog)
                                    <div class="widget-facet">
                                        <h6 class="mb-2">Режим и эффект пламени</h6>
                                        <ul class="filter-group-check">
                                            @foreach ($electricFireplaceLinks as $link)
                                                @continue($link['label'] === 'В наличии')
                                                <li class="list-item">
                                                    <a href="{{ $link['url'] }}#catalog-products"
                                                       class="link {{ $link['active'] ? 'fw-bold' : '' }}"
                                                       @if ($link['active']) aria-current="true" @endif>
                                                        {{ $link['label'] }} <span class="count">({{ $link['count'] }})</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="br-line"></div>
                                @endif

                                @if ($stoveAreaLinks !== [])
                                    <div class="widget-facet">
                                        <h6 class="mb-2">Площадь обогрева</h6>
                                        <ul class="filter-group-check">
                                            @foreach ($stoveAreaLinks as $link)
                                                <li class="list-item">
                                                    <a href="{{ $link['url'] }}#catalog-products"
                                                        class="link {{ $link['active'] ? 'fw-bold' : '' }}"
                                                        @if ($link['active']) aria-label="Сбросить фильтр площади {{ $link['label'] }}" @endif>
                                                        {{ $link['label'] }} <span class="count">({{ $link['count'] }})</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="br-line"></div>
                                @endif

                                {{-- Атрибуты категории (select-тип, in_filter=true) --}}
                                @foreach ($specificationFacets as $key => $facet)
                                    <details class="widget-facet" @if ($loop->first || collect($facet['options'])->contains('active', true)) open @endif>
                                        <summary class="fw-semibold mb-2">{{ $facet['label'] }}</summary>
                                        <ul class="list-unstyled d-flex flex-column gap-2">
                                            @foreach ($facet['options'] as $option)
                                                <li>
                                                    <a href="{{ $option['url'] }}" rel="nofollow"
                                                       class="d-flex justify-content-between align-items-center gap-2 link {{ $option['active'] ? 'fw-semibold' : '' }}"
                                                       @if ($option['active']) aria-current="true" @endif>
                                                        <span>{{ $option['label'] }}</span>
                                                        <span class="count">{{ $option['count'] }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                    <div class="br-line"></div>
                                @endforeach
                                @foreach ($specificationFilterAttributes as $attr)
                                    @continue(in_array($attr->id, $stoveAreaAttributeIds, true))
                                    @continue($isElectricFireplaceCatalog && mb_strtolower(trim($attr->name)) === 'обогрев')
                                    @continue($isBoilerHub && $selectedSubcategory === null && !request()->filled('attr.'.$attr->id))
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#attr-{{ $attr->id }}"
                                            role="button" data-bs-toggle="collapse" aria-expanded="false">
                                            <h6>{{ $attr->name }}
                                                @if ($attr->suffix)
                                                    <span class="cl-text-2 fw-normal">({{ $attr->suffix }})</span>
                                                @endif
                                            </h6>
                                            <span class="icon icon-CaretDown"></span>
                                        </div>
                                        <div id="attr-{{ $attr->id }}" class="collapse">
                                            <ul class="collapse-body filter-group-check">
                                                @foreach ($attr->options as $option)
                                                    <li class="list-item">
                                                        <input type="checkbox"
                                                            class="tf-check style-2"
                                                            id="opt-{{ $option->id }}"
                                                            name="attr[{{ $attr->id }}][]"
                                                            value="{{ $option->id }}"
                                                            {{ in_array($option->id, (array)request('attr.' . $attr->id, [])) ? 'checked' : '' }}
                                                            onchange="applyAttrFilter({{ $attr->id }}, {{ $option->id }}, this.checked)">
                                                        <label for="opt-{{ $option->id }}" class="label">
                                                            <span>{{ $option->name }}</span>
                                                            <span class="count">({{ $option->products_count }})</span>
                                                        </label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="br-line"></div>
                                @endforeach

                                {{-- Кнопка сброса --}}
                                @php
                                    $hasActiveCatalogFilters = count(request()->except('sort', 'page', 'brand_sort', 'fireplace_type')) > 0;
                                    $resetCatalogParameters = array_filter([
                                        'fireplace_type' => $fireplaceType,
                                        'brand_sort' => request('brand_sort') === 'alpha' ? 'alpha' : null,
                                    ]);
                                    $resetCatalogUrl = url()->current().($resetCatalogParameters ? '?'.http_build_query($resetCatalogParameters) : '');
                                    $resetCatalogUrl .= '#catalog-products';
                                @endphp
                                @if ($hasActiveCatalogFilters)
                                    <a href="{{ $resetCatalogUrl }}" class="tf-btn btn-outline w-100 mt-8">
                                        <i class="icon icon-X2"></i> Сбросить фильтры
                                    </a>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Список товаров --}}
                <div class="col-xl-9">

                    <div class="tf-shop-control">
                        {{-- Кнопка открытия фильтров на мобильных --}}
                        <button type="button" id="filterShop" class="tf-btn-filter d-xl-none">
                            <span class="icon icon-filter"></span>
                            <span class="text">Фильтры</span>
                        </button>

                        {{-- Сортировка --}}
                        <div class="tf-control-sorting">
                            <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                                <div class="btn-select">
                                    <span class="text-sort-value">
                                        @switch(request('sort'))
                                            @case('price_asc') Цена: по возрастанию @break
                                            @case('price_desc') Цена: по убыванию @break
                                            @case('name_asc') По названию А-Я @break
                                            @case('rating') По рейтингу @break
                                            @case('new') Сначала новые @break
                                            @default Популярные
                                        @endswitch
                                    </span>
                                    <span class="icon icon-CaretDown"></span>
                                </div>
                                <div class="dropdown-menu">
                                    <div class="select-item {{ !request('sort') ? 'active' : '' }}"
                                        onclick="applyFilter('sort', '')">
                                        <span class="text-value-item">Популярные</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'new' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'new')">
                                        <span class="text-value-item">Сначала новые</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'price_asc' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'price_asc')">
                                        <span class="text-value-item">Цена: по возрастанию</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'price_desc' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'price_desc')">
                                        <span class="text-value-item">Цена: по убыванию</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'rating' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'rating')">
                                        <span class="text-value-item">По рейтингу</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'name_asc' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'name_asc')">
                                        <span class="text-value-item">По названию А-Я</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Переключение вида --}}
                        <ul class="tf-control-layout">
                            <li class="tf-view-layout-switch sw-layout-list list-layout" data-value-layout="list">
                                <i class="icon-List"></i>
                            </li>
                            <li class="tf-view-layout-switch sw-layout-2" data-value-layout="tf-col-2">
                                <i class="icon-grid-2"></i>
                            </li>
                            <li class="tf-view-layout-switch sw-layout-3 d-none d-md-flex"
                                data-value-layout="tf-col-3">
                                <i class="icon-grid-3"></i>
                            </li>
                            <li class="tf-view-layout-switch sw-layout-4 d-none d-lg-flex"
                                data-value-layout="tf-col-4">
                                <i class="icon-grid-4"></i>
                            </li>
                        </ul>
                    </div>

                    {{-- Мета: количество и активные фильтры --}}
                    <div class="wrapper-control-shop gridLayout-wrapper">
                        <div class="meta-filter-shop">
                            <div id="product-count-grid" class="count-text text-caption-01">
                                Показано {{ $products->firstItem() }}–{{ $products->lastItem() }}
                                из {{ $products->total() }} товаров
                            </div>
                            <div id="product-count-list" class="count-text text-caption-01" style="display:none">
                                Показано {{ $products->firstItem() }}–{{ $products->lastItem() }}
                                из {{ $products->total() }} товаров
                            </div>
                            @php
                                $hasActiveCatalogFilters = $hasActiveCatalogFilters ?? count(request()->except('sort', 'page', 'brand', 'brand_sort')) > 0;
                                $resetCatalogParameters = array_filter([
                                    'brand' => request('brand') ? (int) request('brand') : null,
                                    'brand_sort' => request('brand_sort') === 'alpha' ? 'alpha' : null,
                                ]);
                                $resetCatalogUrl = $resetCatalogUrl ?? (url()->current() . ($resetCatalogParameters ? '?' . http_build_query($resetCatalogParameters) : ''));
                            @endphp
                        </div>

                        @if ($hasActiveCatalogFilters)
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-16" aria-label="Выбранные фильтры">
                                @foreach ($electricFilterChips as $chip)
                                    <a href="{{ $chip['url'] }}"
                                       class="d-inline-flex align-items-center gap-2 border rounded-pill px-2 py-1 text-caption-01"
                                       aria-label="Убрать фильтр {{ $chip['label'] }}">
                                        {{ $chip['label'] }} <i class="icon icon-X2" aria-hidden="true"></i>
                                    </a>
                                @endforeach
                                <a href="{{ $resetCatalogUrl }}" class="link fw-semibold text-caption-01">Сбросить все фильтры</a>
                            </div>
                        @endif

                        {{-- List вид товаров --}}
                        <div class="tf-list-layout wrapper-shop" id="listLayout" style="display: none;">
                            @forelse ($products as $product)
                                @include('partials.product-card-list', ['product' => $product, 'fireplaceCardSpecs' => $fireplaceSpecsByProduct[$product->id] ?? []])
                            @empty
                                <div class="text-center py-48 wd-full">
                                    <p class="h5 cl-text-2">Товары не найдены</p>
                                </div>
                            @endforelse

                            {{-- Пагинация list --}}
                            @if ($products->hasPages())
                                <div class="wd-full justify-content-center">
                                    <div class="tf-page-pagination">
                                        @if ($products->onFirstPage())
                                            <span class="pag-item disabled"><i class="icon icon-CaretLeftThin"></i></span>
                                        @else
                                            <a href="{{ $products->previousPageUrl() }}" class="pag-item"><i class="icon icon-CaretLeftThin"></i></a>
                                        @endif
                                        @foreach ($products->getUrlRange(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page => $url)
                                            @if ($page == $products->currentPage())
                                                <p class="pag-item active">{{ $page }}</p>
                                            @else
                                                <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                                            @endif
                                        @endforeach
                                        @if ($products->hasMorePages())
                                            <a href="{{ $products->nextPageUrl() }}" class="pag-item"><i class="icon icon-CaretRightThin"></i></a>
                                        @else
                                            <span class="pag-item disabled"><i class="icon icon-CaretRightThin"></i></span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Сетка товаров --}}
                        <div class="wrapper-shop tf-grid-layout tf-col-2 md-col-3" id="gridLayout">
                            @forelse ($products as $product)
                                @include('partials.product-card', ['product' => $product, 'fireplaceCardSpecs' => $fireplaceSpecsByProduct[$product->id] ?? []])
                            @empty
                                <div class="wd-full text-center py-48">
                                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                                    <p class="h5 cl-text-2">Товары не найдены</p>
                                    <p class="text-body-1 cl-text-3 mt-8">Попробуйте изменить параметры фильтра</p>
                                    <a href="{{ $resetCatalogUrl ?? url()->current() }}" class="tf-btn btn-primary mt-16">
                                        Сбросить фильтры
                                    </a>
                                </div>
                            @endforelse

                            {{-- Пагинация --}}
                            @if ($products->hasPages())
                                <div class="wd-full">
                                    <div class="tf-page-pagination">
                                        @if ($products->onFirstPage())
                                            <span class="pag-item disabled">
                                                <i class="icon icon-CaretLeftThin"></i>
                                            </span>
                                        @else
                                            <a href="{{ $products->previousPageUrl() }}" class="pag-item">
                                                <i class="icon icon-CaretLeftThin"></i>
                                            </a>
                                        @endif
                                        @foreach ($products->getUrlRange(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page => $url)
                                            @if ($page == $products->currentPage())
                                                <p class="pag-item active">{{ $page }}</p>
                                            @else
                                                <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                                            @endif
                                        @endforeach
                                        @if ($products->hasMorePages())
                                            <a href="{{ $products->nextPageUrl() }}" class="pag-item">
                                                <i class="icon icon-CaretRightThin"></i>
                                            </a>
                                        @else
                                            <span class="pag-item disabled">
                                                <i class="icon icon-CaretRightThin"></i>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    @if ($category->slug === 'tverdotoplivnye')
        @include('partials.solid-fuel-boiler-quick-filters', ['boilerUnified' => true])
    @endif

    @if ($category->slug === 'gazovye')
        @include('partials.gas-boiler-quick-filters', ['boilerUnified' => true])
    @endif

    @if ($category->slug === 'elektricheskie')
        @include('partials.electric-boiler-quick-filters', ['boilerUnified' => true])
    @endif

    @if ($category->slug === 'kotly-na-pelletah')
        @include('partials.pellet-boiler-quick-filters', ['boilerUnified' => true])
    @endif

    @if ($isMinskFireplaceCatalog || $isStoveHeatingCatalog)
        @include('partials.catalog-ecokamin-promo')
    @endif

    @if ($category->slug === 'teplovyie-nasosyi' && !request()->hasAny(['page', 'price_min', 'price_max', 'in_stock', 'brand', 'attr', 'sort']))
        @include('partials.heat-pump-category-content', ['articles' => $heatPumpArticles ?? collect()])
    @endif

    @if ($category->slug === 'pelletnye-gorelki' && !request()->hasAny(['page', 'price_min', 'price_max', 'in_stock', 'brand', 'attr', 'sort', 'power']))
        @include('partials.pellet-burner-comparison')
        @include('partials.pellet-burner-category-faq')
    @endif

    @if ($category->slug === 'schetchiki-gaza' && !request()->hasAny(['page', 'price_min', 'price_max', 'in_stock', 'brand', 'attr', 'sort']))
        @include('partials.gas-meter-category-content', ['faq' => $gasMeterFaq ?? collect()])
    @endif

    @if ($category->slug === 'teplonositeli' && !request()->hasAny(['page', 'price_min', 'price_max', 'in_stock', 'brand', 'attr', 'sort']))
        @include('partials.heat-transfer-fluid-category-content', ['faq' => $heatFluidFaq ?? collect()])
    @endif

    {{-- Local text for this city, written in the admin: Каталог → SEO городов. --}}
    @if (filled($citySeoContent ?? null) && !request()->hasAny(['page', 'price_min', 'price_max', 'in_stock', 'brand', 'attr', 'sort']))
        <section class="city-category-guide" aria-label="{{ $category->h1 ?: $category->name }}">
            <div class="container">{!! $citySeoContent !!}</div>
        </section>
        <style>
        .city-category-guide{padding:48px 0 8px}.city-category-guide h2{margin:0 0 12px;font-size:clamp(24px,3vw,34px)}
        .city-category-guide h3{margin:22px 0 8px;font-size:18px}.city-category-guide p{max-width:860px;color:#555;line-height:1.6}
        .city-category-guide a{text-decoration:underline;text-underline-offset:3px}.city-category-guide ul{padding-left:18px;color:#555;line-height:1.7}
        </style>
    @endif

    {{-- Secondary partner CTA belongs after products and useful category content. --}}
    @if ($installerRecruitment)
        @include('partials.installer-recruitment-note', $installerRecruitment)
    @endif

</main>

<script>
// ===== Бренды: показать все / скрыть =====
function toggleBrands(btn) {
    const more = document.getElementById('brands-more');
    const expanded = btn.dataset.expanded === '1';
    const total = btn.dataset.total;

    if (expanded) {
        more.style.display = 'none';
        btn.dataset.expanded = '0';
        btn.innerHTML = 'Показать все (' + total + ') <i class="icon icon-CaretDown fs-12 ms-4"></i>';
    } else {
        more.style.display = '';
        btn.dataset.expanded = '1';
        btn.innerHTML = 'Скрыть <i class="icon icon-CaretUp fs-12 ms-4"></i>';
    }
}

// ===== Фильтры =====
function applyFilter(key, value) {
    const url = new URL(window.location.href);
    if (value === '' || value === null) {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function applyPriceFilter() {
    const url = new URL(window.location.href);
    const minInput = document.getElementById('price-min-value');
    const maxInput = document.getElementById('price-max-value');
    const range = document.getElementById('price-value-range');

    const defaultMin = range ? parseInt(range.dataset.min, 10) : 0;
    const defaultMax = range ? parseInt(range.dataset.max, 10) : 0;
    const minValue = minInput ? parseInt(minInput.value, 10) : defaultMin;
    const maxValue = maxInput ? parseInt(maxInput.value, 10) : defaultMax;

    if (!Number.isNaN(minValue) && minValue > defaultMin) {
        url.searchParams.set('price_min', minValue);
    } else {
        url.searchParams.delete('price_min');
    }

    if (!Number.isNaN(maxValue) && maxValue < defaultMax) {
        url.searchParams.set('price_max', maxValue);
    } else {
        url.searchParams.delete('price_max');
    }

    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function applyAttrFilter(attrId, optionId, checked) {
    const url = new URL(window.location.href);
    const group = `attr[${attrId}]`;
    const selectedKeys = [...new Set(url.searchParams.keys())]
        .filter(key => key === group || key.startsWith(`${group}[`));

    let current = selectedKeys.flatMap(key => url.searchParams.getAll(key));

    if (checked) {
        if (!current.includes(String(optionId))) {
            current.push(String(optionId));
        }
    } else {
        current = current.filter(v => v !== String(optionId));
    }

    selectedKeys.forEach(key => url.searchParams.delete(key));
    current.forEach(value => url.searchParams.append(`${group}[]`, value));
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// ===== Переключение вида grid/list =====
document.addEventListener('DOMContentLoaded', function () {
    const accessoriesNav = document.querySelector('[data-accessories-nav]');
    if (accessoriesNav) {
        const searchInput = accessoriesNav.querySelector('#accessories-nav-search');
        const groups = Array.from(accessoriesNav.querySelectorAll('[data-accessories-group]'));
        const emptyState = accessoriesNav.querySelector('[data-accessories-empty]');
        const normalize = value => String(value || '').toLocaleLowerCase('ru-RU').replace(/ё/g, 'е').trim();

        if (window.matchMedia('(max-width: 767px)').matches) {
            groups.forEach(function (group) {
                group.addEventListener('toggle', function () {
                    if (!group.open || searchInput.value.trim()) return;
                    groups.forEach(other => { if (other !== group) other.open = false; });
                });
            });
        }

        searchInput?.addEventListener('input', function () {
            const tokens = normalize(this.value).split(/\s+/).filter(Boolean);
            let visibleTotal = 0;

            groups.forEach(function (group) {
                let visibleInGroup = 0;
                group.querySelectorAll('[data-accessories-item]').forEach(function (item) {
                    const haystack = normalize(item.dataset.search);
                    const matches = tokens.length === 0 || tokens.every(function (token) {
                        const stem = token.length > 3 ? token.slice(0, 3) : token;
                        return haystack.includes(token) || haystack.includes(stem);
                    });

                    item.hidden = !matches;
                    if (matches) visibleInGroup++;
                });

                group.hidden = visibleInGroup === 0;
                if (tokens.length && visibleInGroup) group.open = true;
                visibleTotal += visibleInGroup;
            });

            if (!tokens.length) {
                groups.forEach(group => { group.open = groups.length === 1; });
            }

            if (emptyState) emptyState.hidden = visibleTotal > 0;
        });
    }

    const switches = document.querySelectorAll('.tf-view-layout-switch');
    const gridLayout = document.getElementById('gridLayout');
    const listLayout = document.getElementById('listLayout');
    const countGrid  = document.getElementById('product-count-grid');
    const countList  = document.getElementById('product-count-list');

    // Восстанавливаем сохранённый вид (по умолчанию — 3 колонки)
    const savedLayout = localStorage.getItem('catalog_layout') || 'tf-col-3';
    applyLayout(savedLayout);

    switches.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const layout = this.dataset.valueLayout;
            localStorage.setItem('catalog_layout', layout);
            applyLayout(layout);
        });
    });

    function applyLayout(layout) {
        // Активная кнопка
        switches.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.valueLayout === layout);
        });

        if (layout === 'list') {
            // List вид
            gridLayout.style.display = 'none';
            listLayout.style.display = 'flex';
            listLayout.style.flexDirection = 'column';
            if (countGrid) countGrid.style.display = 'none';
            if (countList) countList.style.display = 'block';
        } else {
            // Grid вид — меняем количество колонок
            listLayout.style.display = 'none';
            gridLayout.style.display = '';
            if (countGrid) countGrid.style.display = 'block';
            if (countList) countList.style.display = 'none';

            // Сбрасываем и применяем нужный класс колонок
            gridLayout.classList.remove('tf-col-2', 'tf-col-3', 'tf-col-4', 'md-col-3');
            gridLayout.classList.add(layout);
        }
    }
});
</script>
@endsection
