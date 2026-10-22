{{-- Два баннера акции ЭкоКамин внизу главной: топки Дельта и камины. --}}
@php
    $ecoIsDelta = fn ($product) => str_contains(mb_strtolower($product->name), 'дельта');
    $ecoDelta = $ecokaminPromoHits->filter($ecoIsDelta)->first();
    $ecoFireplaces = $ecokaminPromoHits->reject($ecoIsDelta)->take(3);
    $ecoMaxFireplace = (int) $ecoFireplaces->max(fn ($product) => (int) $product->discount_percent);
@endphp
<section class="container flat-spacing eco-promo-duo">
    @if($ecoDelta)
    <a href="{{ route('promotions.ecokamin') }}#delta" class="eco-promo-duo__card eco-promo-duo__card--delta">
        <span class="eco-promo-duo__copy">
            <span class="eco-promo-duo__eyebrow">Акция ЭкоКамин · до 31.10</span>
            <strong class="eco-promo-duo__title">Все топки<br>Дельта <em>−20%</em></strong>
            <span class="eco-promo-duo__text">Фронтальные, угловые и трёхсторонние, с подовым горением и вертикальной ламелью.</span>
            @if($ecokaminPromoFrom['delta'] ?? null)
            <span class="eco-promo-duo__from">от <b>{{ number_format((float) $ecokaminPromoFrom['delta'], 0, '.', ' ') }} BYN</b></span>
            @endif
            <span class="tf-btn btn-white eco-promo-duo__btn">Выбрать топку</span>
        </span>
        <span class="eco-promo-duo__visual">
            <img loading="lazy" decoding="async" src="{{ $ecoDelta->imageUrl(0) }}" alt="{{ $ecoDelta->name }}">
        </span>
    </a>
    @endif
    @if($ecoFireplaces->isNotEmpty())
    <a href="{{ route('promotions.ecokamin') }}#kaminy" class="eco-promo-duo__card eco-promo-duo__card--kaminy">
        <span class="eco-promo-duo__copy">
            <span class="eco-promo-duo__eyebrow">Таких цен больше не будет</span>
            <strong class="eco-promo-duo__title">Камины<br>до <em>−{{ max($ecoMaxFireplace, $ecokaminPromoMaxDiscount) }}%</em></strong>
            <span class="eco-promo-duo__text">Мадрид, Рио, Крит, Эклипс, Родос, Прага, Мюнхен и Три стекла — скидки от 15 до {{ max($ecoMaxFireplace, $ecokaminPromoMaxDiscount) }}%.</span>
            @if($ecokaminPromoFrom['kaminy'] ?? null)
            <span class="eco-promo-duo__from">от <b>{{ number_format((float) $ecokaminPromoFrom['kaminy'], 0, '.', ' ') }} BYN</b></span>
            @endif
            <span class="tf-btn btn-white eco-promo-duo__btn">Смотреть камины</span>
        </span>
        <span class="eco-promo-duo__visual eco-promo-duo__visual--trio">
            @foreach($ecoFireplaces as $fireplace)
            <img loading="lazy" decoding="async" src="{{ $fireplace->image_url }}" alt="{{ $fireplace->name }}">
            @endforeach
        </span>
    </a>
    @endif
</section>
<style>
    .eco-promo-duo{display:grid;grid-template-columns:1fr 1fr;gap:20px}
    .eco-promo-duo__card{position:relative;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);min-height:330px;overflow:hidden;border-radius:24px;color:#fff;isolation:isolate;box-shadow:0 22px 50px rgba(20,10,6,.18);transition:transform .3s ease,box-shadow .3s ease}
    .eco-promo-duo__card:hover{transform:translateY(-4px);box-shadow:0 30px 64px rgba(20,10,6,.26)}
    .eco-promo-duo__card--delta{background:radial-gradient(circle at 85% 75%,rgba(255,122,41,.45),transparent 45%),linear-gradient(135deg,#3a1a10,#140b08)}
    .eco-promo-duo__card--kaminy{background:radial-gradient(circle at 80% 30%,rgba(255,90,54,.4),transparent 45%),linear-gradient(135deg,#c2391f,#5e1a0e 70%,#2b0f09)}
    .eco-promo-duo__copy{display:flex;flex-direction:column;align-items:flex-start;padding:30px 0 30px 32px}
    .eco-promo-duo__eyebrow{margin-bottom:14px;padding:6px 11px;border:1px solid rgba(255,255,255,.22);border-radius:999px;background:rgba(255,255,255,.08);font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
    .eco-promo-duo__title{display:block;margin-bottom:10px;font-size:clamp(30px,2.6vw,42px);font-weight:800;line-height:1;letter-spacing:-.035em}
    .eco-promo-duo__title em{color:#ffb36b;font-style:normal}
    .eco-promo-duo__card--kaminy .eco-promo-duo__title em{color:#fff;text-shadow:0 0 24px rgba(255,200,150,.55)}
    .eco-promo-duo__text{max-width:300px;margin-bottom:16px;color:rgba(255,255,255,.76);font-size:14px;line-height:1.45}
    .eco-promo-duo__from{margin-bottom:16px;color:rgba(255,255,255,.8);font-size:14px}
    .eco-promo-duo__from b{color:#fff;font-size:22px}
    .eco-promo-duo__btn{margin-top:auto;pointer-events:none}
    .eco-promo-duo__visual{display:flex;align-items:center;justify-content:center;padding:22px 22px 22px 0}
    .eco-promo-duo__visual img{width:100%;max-height:280px;object-fit:contain;border-radius:18px;background:#fff;padding:10px;box-shadow:0 18px 40px rgba(0,0,0,.32)}
    .eco-promo-duo__visual--trio{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:10px}
    .eco-promo-duo__visual--trio img{width:100%;height:100%;max-height:none;min-height:0}
    .eco-promo-duo__visual--trio img:first-child{grid-row:1/3}
    @media(max-width:1199px){.eco-promo-duo{grid-template-columns:1fr}}
    @media(max-width:575px){.eco-promo-duo__card{grid-template-columns:1fr;min-height:0}.eco-promo-duo__copy{padding:24px 22px 14px}.eco-promo-duo__visual{padding:6px 22px 24px}.eco-promo-duo__visual img{max-height:210px}.eco-promo-duo__visual--trio{grid-template-columns:repeat(3,1fr);grid-template-rows:170px}.eco-promo-duo__visual--trio img:first-child{grid-row:auto}.eco-promo-duo__btn{margin-top:4px}}
</style>
