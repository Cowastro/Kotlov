{{-- Два баннера акции ЭкоКамин внизу главной: каминные топки и камины с печами-каминами. --}}
@php
    $ecoIsFirebox = fn ($product) => \App\Support\Promotions\EcokaminPromotion::isFirebox($product);
    $ecoDelta = $ecokaminPromoHits->filter($ecoIsFirebox)->first();
    $ecoFireplaces = $ecokaminPromoHits->reject($ecoIsFirebox)->take(3);
    $ecoMaxFireplace = (int) $ecoFireplaces->max(fn ($product) => (int) $product->discount_percent);
    $ecoMaxFirebox = (int) $ecokaminPromoHits->filter($ecoIsFirebox)->max(fn ($product) => (int) $product->discount_percent);
    $ecoEndsAt = \App\Support\Promotions\EcokaminPromotion::endsAt();
    $ecoDuoArt = fn ($product): string => is_file(public_path('img/banners/ecokamin-sale/'.$product->slug.'.webp'))
        ? asset('img/banners/ecokamin-sale/'.$product->slug.'.webp') : $product->image_url;
    $ecoDuoHasArt = fn ($product): bool => is_file(public_path('img/banners/ecokamin-sale/'.$product->slug.'.webp'));
@endphp
<section class="container flat-spacing eco-promo-duo">
    @if($ecoDelta)
    <a href="{{ route('promotions.ecokamin') }}#topki" class="eco-promo-duo__card">
        <span class="eco-promo-duo__word" aria-hidden="true">−{{ max($ecoMaxFirebox, 20) }}%</span>
        <span class="eco-promo-duo__copy">
            <span class="eco-promo-duo__mast">ЭкоКамин<i></i>до {{ $ecoEndsAt->format('d.m') }}</span>
            <span class="eco-promo-duo__kicker">Каминные топки</span>
            <strong class="eco-promo-duo__title"><small>до</small>−{{ max($ecoMaxFirebox, 20) }}%</strong>
            <span class="eco-promo-duo__text">Дельта и Вега −20%, Альфа −25% с белым шамотом и −15% с чёрным, чугунная Primo 700.</span>
            @if($ecokaminPromoFrom['topki'] ?? null)
            <span class="eco-promo-duo__from">от <b>{{ number_format((float) $ecokaminPromoFrom['topki'], 0, '.', ' ') }} BYN</b></span>
            @endif
            <span class="tf-btn btn-white eco-promo-duo__btn">Выбрать топку</span>
        </span>
        <span class="eco-promo-duo__visual">
            <img loading="lazy" decoding="async" class="{{ $ecoDuoHasArt($ecoDelta) ? '' : 'is-photo' }}" src="{{ $ecoDuoArt($ecoDelta) }}" alt="{{ $ecoDelta->name }}">
        </span>
    </a>
    @endif
    @if($ecoFireplaces->isNotEmpty())
    <a href="{{ route('promotions.ecokamin') }}#kaminy" class="eco-promo-duo__card">
        <span class="eco-promo-duo__word" aria-hidden="true">−{{ max($ecoMaxFireplace, 15) }}%</span>
        <span class="eco-promo-duo__copy">
            <span class="eco-promo-duo__mast">ЭкоКамин<i></i>таких цен больше не будет</span>
            <span class="eco-promo-duo__kicker">Камины и печи-камины</span>
            <strong class="eco-promo-duo__title"><small>до</small>−{{ max($ecoMaxFireplace, 15) }}%</strong>
            <span class="eco-promo-duo__text">Угловые Прага −30%; Мадрид, Рио, Крит, Эклипс, Родос −20%; Мюнхен, Три стекла и печи-камины Бавария −15%.</span>
            @if($ecokaminPromoFrom['kaminy'] ?? null)
            <span class="eco-promo-duo__from">от <b>{{ number_format((float) $ecokaminPromoFrom['kaminy'], 0, '.', ' ') }} BYN</b></span>
            @endif
            <span class="tf-btn btn-white eco-promo-duo__btn">Смотреть камины</span>
        </span>
        <span class="eco-promo-duo__visual eco-promo-duo__visual--trio">
            @foreach($ecoFireplaces as $fireplace)
            <img loading="lazy" decoding="async" class="{{ $ecoDuoHasArt($fireplace) ? '' : 'is-photo' }}" src="{{ $ecoDuoArt($fireplace) }}" alt="{{ $fireplace->name }}">
            @endforeach
        </span>
    </a>
    @endif
</section>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&text=ABCDEFGHIJKLMNOPQRSTUVWXYZ%D0%90%D0%91%D0%92%D0%93%D0%94%D0%95%D0%81%D0%96%D0%97%D0%98%D0%99%D0%9A%D0%9B%D0%9C%D0%9D%D0%9E%D0%9F%D0%A0%D0%A1%D0%A2%D0%A3%D0%A4%D0%A5%D0%A6%D0%A7%D0%A8%D0%A9%D0%AA%D0%AB%D0%AC%D0%AD%D0%AE%D0%AF0123456789%25%E2%88%92-%E2%80%93.%2C&display=swap" rel="stylesheet">
<style>
    .eco-promo-duo{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .eco-promo-duo__card{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.9fr);min-height:360px;overflow:hidden;border-radius:24px;color:#f6efe7;text-decoration:none;isolation:isolate;background:radial-gradient(ellipse 55% 70% at 78% 78%,rgba(232,104,40,.32),rgba(232,104,40,0) 70%),linear-gradient(115deg,#0a0807 0%,#120d0b 45%,#1d140f 100%);box-shadow:0 22px 50px rgba(20,10,6,.2);transition:transform .3s ease,box-shadow .3s ease}
    .eco-promo-duo__card:after{position:absolute;inset:0;z-index:3;pointer-events:none;border-radius:inherit;box-shadow:inset 0 0 0 1px rgba(214,172,120,.2),inset 0 0 90px rgba(0,0,0,.5);content:""}
    .eco-promo-duo__card:hover{transform:translateY(-4px);box-shadow:0 30px 64px rgba(20,10,6,.3)}
    .eco-promo-duo__word{position:absolute;right:-2%;top:-10%;z-index:0;font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:clamp(130px,13vw,210px);line-height:1;color:transparent;-webkit-text-stroke:1px rgba(214,172,120,.14);white-space:nowrap;font-variant-numeric:lining-nums;font-feature-settings:"lnum" 1}
    .eco-promo-duo__copy{position:relative;z-index:1;display:flex;flex-direction:column;align-items:flex-start;padding:30px 0 30px 34px}
    .eco-promo-duo__mast{display:flex;align-items:center;gap:10px;margin-bottom:14px;color:#d6ac78;font-size:10px;font-weight:600;letter-spacing:.28em;text-transform:uppercase;white-space:nowrap}
    .eco-promo-duo__mast i{display:block;width:30px;height:1px;background:rgba(214,172,120,.6)}
    .eco-promo-duo__kicker{color:rgba(246,239,231,.72);font-size:12px;letter-spacing:.16em;text-transform:uppercase}
    .eco-promo-duo__title{display:flex;align-items:baseline;gap:10px;margin:0 0 8px;font-family:'Playfair Display',Georgia,serif;font-weight:700;font-size:clamp(56px,5vw,84px);line-height:1;background:linear-gradient(180deg,#fff6e8 0%,#f0cf9f 55%,#c98b4c 100%);-webkit-background-clip:text;background-clip:text;color:transparent;font-variant-numeric:lining-nums;font-feature-settings:"lnum" 1}
    .eco-promo-duo__title small{font-family:Manrope,Arial,sans-serif;font-size:.2em;font-weight:600;letter-spacing:.2em;text-transform:uppercase}
    .eco-promo-duo__text{max-width:300px;margin-bottom:14px;color:rgba(246,239,231,.72);font-size:13px;line-height:1.5}
    .eco-promo-duo__from{margin-bottom:16px;color:rgba(246,239,231,.7);font-size:13px}
    .eco-promo-duo__from b{color:#f0cf9f;font-family:'Playfair Display',Georgia,serif;font-size:24px;font-variant-numeric:lining-nums;font-feature-settings:"lnum" 1}
    .eco-promo-duo__btn{margin-top:auto;pointer-events:none}
    .eco-promo-duo__visual{position:relative;z-index:1;display:flex;align-items:flex-end;justify-content:center;padding:26px 24px 22px 0}
    .eco-promo-duo__visual img{max-width:100%;max-height:300px;object-fit:contain;filter:drop-shadow(0 24px 28px rgba(0,0,0,.55))}
    .eco-promo-duo__visual img.is-photo{padding:8px;border-radius:14px;background:#fff}
    .eco-promo-duo__visual--trio{gap:8px}
    .eco-promo-duo__visual--trio img{flex:none;width:calc((100% - 16px)/3);min-width:0;max-height:260px}
    @media(max-width:1199px){.eco-promo-duo{grid-template-columns:1fr}}
    @media(max-width:575px){.eco-promo-duo__card{grid-template-columns:1fr;min-height:0}.eco-promo-duo__copy{padding:24px 22px 10px}.eco-promo-duo__visual{padding:6px 18px 22px}.eco-promo-duo__visual img{max-height:220px}.eco-promo-duo__visual--trio img{max-height:180px}.eco-promo-duo__word{font-size:120px}.eco-promo-duo__mast{white-space:normal;flex-wrap:wrap;row-gap:4px}}
</style>
