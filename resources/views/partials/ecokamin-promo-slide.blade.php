{{-- Первый слайд главной на время акции ЭкоКамин: хиты серий с ценами и таймер до конца акции. --}}
@php
    // «Камин МЮНХЕН ПРИСТЕННО-УГЛОВОЙ черный» → «Мюнхен пристенно-угловой».
    $ecoShort = function ($product): string {
        $name = preg_replace('/^(Каминная топка|Камин|Печь-камин)\s+((ЭкоКамин|Экокамин|Ecokamin)\s+)?/iu', '', $product->name) ?? $product->name;
        $name = preg_replace('/[,\s]+(черн|графит|слонов|шамот|принтинг).*$/iu', '', $name) ?? $name;
        $name = preg_replace_callback('/(?<!\p{L})\p{Lu}{3,}(?:-\p{Lu}{3,})*(?!\p{L})/u', fn ($m) => mb_strtolower($m[0]), $name) ?? $name;

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    };
    $ecoEnds = \Carbon\CarbonImmutable::parse(\App\Console\Commands\ApplyEcokaminOctoberSaleCommand::ENDS_AT, 'Europe/Minsk');
    $ecoDelta = ($ecokaminPromoVariant ?? 'all') === 'delta';
    $ecoFireplaces = ($ecokaminPromoVariant ?? 'all') === 'fireplaces';
@endphp
<div class="slider-wrap slideshow-wrap rounded-20 overflow-hidden eco-promo-slide">
    <div class="eco-promo-slide__glow" aria-hidden="true"></div>
    <div class="eco-promo-slide__layout">
        <div class="eco-promo-slide__copy">
            <span class="eco-promo-slide__eyebrow">Акция производителя ЭкоКамин</span>
            @if($ecoDelta)
            <h2>Топки Дельта<br><span>−{{ $ecokaminPromoMaxDiscount }}%</span></h2>
            <p>Все каминные топки ЭкоКамин Дельта — фронтальные, угловые и трёхсторонние, с подовым горением и вертикальной ламелью — по акционным ценам производителя.</p>
            @elseif($ecoFireplaces)
            <h2>Камины ЭкоКамин<br><span>до −{{ $ecokaminPromoMaxDiscount }}%</span></h2>
            <p>Мадрид, Рио, Крит, Эклипс, Родос, Прага, Мюнхен и Три стекла — по акционным ценам производителя. Таких цен больше не будет.</p>
            @else
            <h2>Камины и топки<br><span>до −{{ $ecokaminPromoMaxDiscount }}%</span></h2>
            <p>Топки Дельта, камины Мадрид, Рио, Крит, Мюнхен, Прага и Эклипс по акционным ценам производителя. Таких цен больше не будет.</p>
            @endif
            <div class="eco-promo-slide__timer" data-eco-countdown="{{ $ecoEnds->toIso8601String() }}" aria-label="До конца акции">
                <span><b data-unit="d">–</b><small>дней</small></span>
                <span><b data-unit="h">–</b><small>часов</small></span>
                <span><b data-unit="m">–</b><small>минут</small></span>
                <em>до {{ $ecoEnds->format('d.m') }}</em>
            </div>
            <div class="eco-promo-slide__actions">
                @if($ecoDelta)
                <a href="{{ route('promotions.ecokamin') }}#delta" class="tf-btn animate-btn eco-promo-slide__cta">Все топки Дельта</a>
                <a href="{{ route('promotions.ecokamin') }}#kaminy" class="eco-promo-slide__more">Камины по акции</a>
                @elseif($ecoFireplaces)
                <a href="{{ route('promotions.ecokamin') }}#kaminy" class="tf-btn animate-btn eco-promo-slide__cta">Все камины по акции</a>
                <a href="{{ route('promotions.ecokamin') }}#delta" class="eco-promo-slide__more">Топки Дельта −20%</a>
                @else
                <a href="{{ route('promotions.ecokamin') }}" class="tf-btn animate-btn eco-promo-slide__cta">Все товары акции</a>
                <a href="{{ route('promotions.ecokamin') }}#delta" class="eco-promo-slide__more">Топки Дельта −20%</a>
                @endif
            </div>
        </div>
        <div class="eco-promo-slide__products" aria-label="{{ $ecoDelta ? 'Топки Дельта по акции' : ($ecoFireplaces ? 'Камины по акции' : 'Хиты акции ЭкоКамин') }}">
            @foreach($ecokaminPromoHits->take(3) as $hit)
            <a href="/{{ $hit->category->slug }}/{{ $hit->slug }}" class="eco-promo-slide__product">
                <span class="eco-promo-slide__offer">−{{ $hit->discount_percent }}%</span>
                <span class="eco-promo-slide__media"><img loading="eager" decoding="async" src="{{ $hit->image_url }}" alt="{{ $hit->name }}"></span>
                <span class="eco-promo-slide__name">
                    <strong>{{ \Illuminate\Support\Str::limit($ecoShort($hit), 32) }}</strong>
                    <span class="eco-promo-slide__price"><del>{{ number_format((float) $hit->price_old, 0, '.', ' ') }}</del>{{ number_format((float) $hit->price, 0, '.', ' ') }} BYN</span>
                </span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@once
@push('styles')
<style>
    .eco-promo-slide{position:relative;width:100%;height:560px;background:radial-gradient(circle at 74% 62%,#5a2412 0%,#2a120b 34%,#140b08 62%,#0d0807 100%);color:#fff;isolation:isolate}
    .eco-promo-slide__glow{position:absolute;inset:0;z-index:-1;background:radial-gradient(ellipse at 72% 100%,rgba(255,122,41,.42),transparent 46%),radial-gradient(circle at 92% 8%,rgba(232,70,47,.22),transparent 32%),linear-gradient(90deg,rgba(13,8,7,.9),rgba(13,8,7,.2) 55%,transparent);animation:ecoGlow 6s ease-in-out infinite alternate}
    .eco-promo-slide__glow:after{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:44px 44px;mask-image:linear-gradient(90deg,#000,transparent 70%);content:""}
    @keyframes ecoGlow{from{opacity:.82}to{opacity:1}}
    .eco-promo-slide__layout{position:relative;display:grid;grid-template-columns:minmax(0,.86fr) minmax(600px,1.14fr);gap:clamp(24px,2.4vw,42px);align-items:center;height:100%;padding:42px clamp(38px,3.5vw,62px);box-sizing:border-box}
    .eco-promo-slide__layout>*{min-width:0}
    .eco-promo-slide__eyebrow{display:inline-flex;align-items:center;gap:9px;margin-bottom:16px;padding:7px 12px;border:1px solid rgba(255,140,90,.4);border-radius:999px;background:rgba(232,70,47,.14);color:#ffa078;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase}
    .eco-promo-slide__eyebrow:before{width:18px;height:2px;background:currentColor;content:""}
    .eco-promo-slide__copy h2{margin:0 0 14px;color:#fff;font-size:clamp(42px,3.5vw,62px);line-height:1;letter-spacing:-.045em;text-shadow:0 12px 36px rgba(0,0,0,.3)}
    .eco-promo-slide__copy h2 span{background:linear-gradient(90deg,#ffb36b,#ff5a36);-webkit-background-clip:text;background-clip:text;color:transparent}
    .eco-promo-slide__copy p{max-width:560px;margin:0 0 20px;color:rgba(255,255,255,.74);font-size:16px;line-height:1.48}
    .eco-promo-slide__timer{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:24px}
    .eco-promo-slide__timer span{display:flex;flex-direction:column;align-items:center;min-width:62px;padding:9px 8px 7px;border:1px solid rgba(255,255,255,.14);border-radius:14px;background:rgba(255,255,255,.06);backdrop-filter:blur(8px)}
    .eco-promo-slide__timer b{font-size:24px;line-height:1;font-variant-numeric:tabular-nums}
    .eco-promo-slide__timer small{margin-top:4px;color:rgba(255,255,255,.6);font-size:10px;text-transform:uppercase;letter-spacing:.06em}
    .eco-promo-slide__timer em{margin-left:6px;color:#ffa078;font-size:13px;font-style:normal;font-weight:600}
    .eco-promo-slide__actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
    .eco-promo-slide__cta{background:linear-gradient(135deg,#ff6a3d,#e8462f)!important;border-color:transparent!important;color:#fff!important;box-shadow:0 14px 34px rgba(232,70,47,.35)}
    .eco-promo-slide__more{padding:12px 16px;color:#fff;text-decoration:underline;text-underline-offset:4px}
    .eco-promo-slide__products{position:relative;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;height:380px;padding:19px;border:1px solid rgba(255,255,255,.12);border-radius:30px;background:linear-gradient(145deg,rgba(255,255,255,.09),rgba(255,255,255,.025));box-shadow:inset 0 1px 0 rgba(255,255,255,.08),0 32px 80px rgba(0,0,0,.32);box-sizing:border-box;backdrop-filter:blur(14px)}
    .eco-promo-slide__products:before{position:absolute;z-index:-1;right:6%;bottom:0;width:80%;height:70%;border-radius:50%;background:rgba(255,106,61,.3);filter:blur(80px);content:""}
    .eco-promo-slide__product{position:relative;display:flex;height:342px;padding:44px 10px 68px;overflow:hidden;border-radius:20px;background:linear-gradient(160deg,#fff 0%,#f3efeb 100%);box-shadow:0 18px 38px rgba(0,0,0,.3);box-sizing:border-box;transition:transform .25s ease,box-shadow .25s ease}
    .eco-promo-slide__product:hover{transform:translateY(-6px);box-shadow:0 26px 52px rgba(0,0,0,.4)}
    .eco-promo-slide__media{display:flex;align-items:center;justify-content:center;width:100%;height:100%;overflow:hidden;border-radius:12px;background:#fff}
    .eco-promo-slide__media img{width:100%;height:100%;object-fit:contain}
    .eco-promo-slide__offer{position:absolute;top:13px;left:13px;z-index:2;padding:7px 11px;border-radius:999px;background:linear-gradient(135deg,#ff6a3d,#e8462f);color:#fff;font-size:12px;font-weight:800;box-shadow:0 8px 20px rgba(232,70,47,.32)}
    .eco-promo-slide__name{position:absolute;right:8px;bottom:11px;left:8px;color:#17191d;font-size:12px;line-height:1.18;text-align:center}
    .eco-promo-slide__name strong{display:block;margin-bottom:5px;font-weight:700}
    .eco-promo-slide__price{display:flex;align-items:baseline;justify-content:center;gap:6px;color:#e8462f;font-size:14px;font-weight:800;white-space:nowrap}
    .eco-promo-slide__price del{color:#9a9ea4;font-size:11px;font-weight:500}
    @media(max-width:1439px){.eco-promo-slide{height:510px}.eco-promo-slide__layout{grid-template-columns:minmax(0,.9fr) minmax(500px,1.1fr);padding:34px 38px}.eco-promo-slide__copy h2{font-size:48px}.eco-promo-slide__copy p{font-size:14px}.eco-promo-slide__products{height:330px;padding:14px;gap:10px}.eco-promo-slide__product{height:302px;padding:40px 8px 62px}}
    @media(max-width:991px){.eco-promo-slide{height:500px}.eco-promo-slide__layout{grid-template-columns:minmax(0,1fr) minmax(340px,.95fr);gap:20px;padding:30px}.eco-promo-slide__copy h2{font-size:40px}.eco-promo-slide__copy p{display:none}.eco-promo-slide__products{grid-template-columns:repeat(2,minmax(0,1fr));height:260px;padding:10px;gap:8px;border-radius:22px}.eco-promo-slide__product{height:240px;padding:34px 6px 54px;border-radius:15px}.eco-promo-slide__product:nth-child(3){display:none}}
    @media(max-width:767px){.eco-promo-slide{height:550px}.eco-promo-slide__layout{display:flex;flex-direction:column;align-items:stretch;gap:14px;padding:26px 20px 24px;overflow:hidden}.eco-promo-slide__copy h2{font-size:36px}.eco-promo-slide__timer{margin-bottom:14px}.eco-promo-slide__timer span{min-width:52px;padding:7px 6px 5px}.eco-promo-slide__timer b{font-size:19px}.eco-promo-slide__products{flex:1;grid-template-columns:repeat(3,minmax(0,1fr));height:auto;min-height:0;padding:8px;gap:6px;border-radius:18px}.eco-promo-slide__product,.eco-promo-slide__product:nth-child(3){display:flex;flex-direction:column;height:100%;min-height:150px;padding:28px 5px 8px;border-radius:13px}.eco-promo-slide__product:hover{transform:none}.eco-promo-slide__media{flex:1;min-height:0}.eco-promo-slide__offer{top:5px;left:5px;padding:3px 7px;font-size:10px}.eco-promo-slide__name{position:static;margin-top:6px;font-size:10px}.eco-promo-slide__name strong{display:-webkit-box;min-height:2.36em;margin-bottom:3px;overflow:hidden;-webkit-line-clamp:2;-webkit-box-orient:vertical}.eco-promo-slide__price{flex-direction:column;align-items:center;gap:0;font-size:11px}.eco-promo-slide__price del{font-size:9px}}
    @media(max-width:575px){.eco-promo-slide{height:500px}.eco-promo-slide__layout{gap:10px;padding:18px 14px 18px}.eco-promo-slide__eyebrow{margin-bottom:8px;padding:5px 9px;font-size:9px}.eco-promo-slide__copy h2{margin-bottom:10px;font-size:30px}.eco-promo-slide__timer em{display:none}.eco-promo-slide__cta{min-height:38px;padding:8px 14px;font-size:12px}.eco-promo-slide__more{padding:8px 6px;font-size:12px}}
</style>
@endpush
@push('scripts')
<script>
    (function () {
        function tick() {
            document.querySelectorAll('[data-eco-countdown]').forEach(function (box) {
                var left = Math.max(0, new Date(box.getAttribute('data-eco-countdown')).getTime() - Date.now());
                var values = {d: Math.floor(left / 864e5), h: Math.floor(left / 36e5) % 24, m: Math.floor(left / 6e4) % 60};
                Object.keys(values).forEach(function (unit) {
                    var el = box.querySelector('[data-unit="' + unit + '"]');
                    if (el) el.textContent = String(values[unit]).padStart(2, '0');
                });
            });
        }
        tick();
        setInterval(tick, 30000);
    })();
</script>
@endpush
@endonce
