@extends('layouts.amerce')

@push('styles')
<style>
    .city-store{background:#f6f6f4}.city-store__hero{padding:54px 0}.city-store__hero-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(380px,.86fr);min-height:480px;overflow:hidden;border-radius:28px;background:#151b22;color:#fff}.city-store__copy{display:flex;flex-direction:column;justify-content:center;padding:58px}.city-store__eyebrow{margin-bottom:12px;color:#ff6b70;font-size:12px;font-weight:750;letter-spacing:.08em;text-transform:uppercase}.city-store h1{max-width:740px;margin:0 0 18px;color:#fff;font-size:clamp(38px,5vw,66px);line-height:1.02;letter-spacing:-.04em}.city-store__lead{max-width:680px;margin:0 0 26px;color:rgba(255,255,255,.72);font-size:17px;line-height:1.55}.city-store__actions{display:flex;flex-wrap:wrap;gap:10px}.city-store__actions a{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 20px;border-radius:999px;background:#e4474b;color:#fff;font-weight:700}.city-store__actions a+ a{border:1px solid rgba(255,255,255,.3);background:transparent}.city-store__visual{min-height:420px;background:linear-gradient(145deg,#2d333b,#161b21)}.city-store__visual img{width:100%;height:100%;object-fit:cover}.city-store__placeholder{display:grid;height:100%;place-items:center;padding:40px;color:rgba(255,255,255,.52);text-align:center}.city-store__sections,.city-store__location,.city-store__gallery{padding:24px 0 58px}.city-store__head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.city-store__head h2{margin:0;font-size:34px}.city-store__head p{max-width:650px;margin:0;color:#707070}.city-store__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.city-store__card{display:flex;min-height:154px;flex-direction:column;justify-content:space-between;padding:24px;border:1px solid #e5e5e5;border-radius:18px;background:#fff;color:#111;transition:.2s}.city-store__card:hover{border-color:#bbb;transform:translateY(-2px);box-shadow:0 14px 35px rgba(0,0,0,.07)}.city-store__card strong{font-size:20px}.city-store__card span{color:#777;font-size:13px}.city-store__location-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.7fr);gap:18px}.city-store__branch,.city-store__map{padding:30px;border:1px solid #e4e4e4;border-radius:22px;background:#fff}.city-store__branch h2{margin:0 0 12px;font-size:29px}.city-store__branch dl{display:grid;grid-template-columns:120px 1fr;gap:10px 18px;margin:20px 0}.city-store__branch dt{color:#777}.city-store__branch dd{margin:0;font-weight:600}.city-store__promo{margin-top:20px;padding:18px;border-left:3px solid #e4474b;background:#f8f4f2;line-height:1.55}.city-store__map{display:flex;min-height:260px;flex-direction:column;justify-content:center}.city-store__map iframe{width:100%;min-height:300px;border:0;border-radius:14px}.city-store__gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.city-store__gallery-grid img{width:100%;aspect-ratio:4/3;border-radius:16px;object-fit:cover}@media(max-width:991px){.city-store__hero-grid{grid-template-columns:1fr}.city-store__copy{padding:42px}.city-store__visual{min-height:340px}.city-store__grid{grid-template-columns:repeat(2,minmax(0,1fr))}.city-store__location-grid{grid-template-columns:1fr}}@media(max-width:575px){.city-store__hero{padding:20px 0}.city-store__hero-grid{border-radius:20px}.city-store__copy{padding:30px 22px}.city-store h1{font-size:38px}.city-store__lead{font-size:15px}.city-store__visual{min-height:260px}.city-store__sections,.city-store__location,.city-store__gallery{padding:18px 0 40px}.city-store__head{align-items:flex-start;flex-direction:column}.city-store__head h2{font-size:28px}.city-store__grid{grid-template-columns:1fr}.city-store__card{min-height:120px}.city-store__branch,.city-store__map{padding:22px}.city-store__branch dl{grid-template-columns:1fr;gap:4px}.city-store__branch dd{margin-bottom:8px}.city-store__gallery-grid{grid-template-columns:1fr 1fr}}
</style>
@endpush

@section('content')
@php($primaryMapLink = $primaryBranch->mapLink())
<main id="wrapper" class="city-store">
    <section class="city-store__hero">
        <div class="container">
            <div class="city-store__hero-grid">
                <div class="city-store__copy">
                    <span class="city-store__eyebrow">Филиал KOTLOV · {{ $city->name }}</span>
                    <h1>{{ $primaryBranch->storefront_title ?: 'Отопление и инженерное оборудование в '.$city->name_title }}</h1>
                    <p class="city-store__lead">{{ $primaryBranch->storefront_intro ?: 'Выбирайте товары из общего каталога KOTLOV и получайте их через местный филиал. Поможем с подбором, комплектацией и сроком поставки.' }}</p>
                    <div class="city-store__actions">
                        <a href="/catalog">Перейти в каталог</a>
                        @if($primaryMapLink)<a href="{{ $primaryMapLink }}" target="_blank" rel="noopener">Построить маршрут</a>@endif
                    </div>
                </div>
                <div class="city-store__visual">
                    @if($primaryBranch->hero_image)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($primaryBranch->hero_image) }}" alt="{{ $primaryBranch->name }} в {{ $city->name_title }}">
                    @else
                        <div class="city-store__placeholder"><span>Здесь будет фотография магазина.<br>Страница уже готова к загрузке снимков.</span></div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="city-store__sections" id="catalog-city">
        <div class="container">
            <div class="city-store__head"><h2>Каталог в {{ $city->name_title }}</h2><p>Цены и карточки товаров едины для KOTLOV. Филиал подтверждает локальное наличие и срок получения.</p></div>
            <div class="city-store__grid">
                @foreach($categories as $category)
                    <a class="city-store__card" href="/{{ $category->slug }}">
                        <strong>{{ $category->storefront_name }}</strong>
                        <span>Смотреть товары и цены →</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="city-store__location">
        <div class="container">
            <div class="city-store__location-grid">
                <article class="city-store__branch">
                    <span class="city-store__eyebrow">Магазин и выдача заказов</span>
                    <h2>{{ $primaryBranch->name }}</h2>
                    <dl>
                        @if($primaryBranch->address)<dt>Адрес</dt><dd>{{ $primaryBranch->address }}</dd>@endif
                        @if($primaryBranch->working_hours)<dt>Режим работы</dt><dd>{{ $primaryBranch->working_hours }}</dd>@endif
                        @if($primaryBranch->email)<dt>E-mail</dt><dd><a href="mailto:{{ $primaryBranch->email }}">{{ $primaryBranch->email }}</a></dd>@endif
                        @if($primaryBranch->phones)<dt>Телефон</dt><dd>@foreach($primaryBranch->phoneLinks() as $phone)<a href="tel:{{ $phone['href'] }}">{{ $phone['display'] }}</a>@if(!$loop->last)<br>@endif @endforeach</dd>@endif
                    </dl>
                    @if($primaryBranch->storefront_promo)<div class="city-store__promo">{{ $primaryBranch->storefront_promo }}</div>@endif
                </article>
                <div class="city-store__map">
                    @if($primaryBranch->map_embed_url)
                        <iframe src="{{ $primaryBranch->map_embed_url }}" loading="lazy" title="{{ $primaryBranch->name }} на карте"></iframe>
                    @else
                        <h2>Как нас найти</h2><p>{{ $primaryBranch->address }}</p>
                        @if($primaryMapLink)<div class="city-store__actions"><a href="{{ $primaryMapLink }}" target="_blank" rel="noopener">Открыть карту</a></div>@endif
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if($primaryBranch->gallery)
        <section class="city-store__gallery"><div class="container"><div class="city-store__head"><h2>Фотографии магазина</h2></div><div class="city-store__gallery-grid">
            @foreach($primaryBranch->gallery as $image)<img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt="{{ $primaryBranch->name }} — фото {{ $loop->iteration }}" loading="lazy">@endforeach
        </div></div></section>
    @endif
</main>
@endsection
