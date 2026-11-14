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
    // A sale product shown as a cut-out with fire when the banner art has one (public/img/banners/ecokamin-sale/{slug}.webp).
    $ecoArt = fn ($product): ?string => is_file(public_path('img/banners/ecokamin-sale/'.$product->slug.'.webp'))
        ? asset('img/banners/ecokamin-sale/'.$product->slug.'.webp') : null;
@endphp
<div class="slider-wrap slideshow-wrap rounded-20 overflow-hidden eco-promo-slide">
    <span class="eco-promo-slide__word" aria-hidden="true">−{{ $ecokaminPromoMaxDiscount }}%</span>
    <div class="eco-promo-slide__layout">
        <div class="eco-promo-slide__copy">
            <div class="eco-promo-slide__mast"><span>ЭкоКамин</span><i></i><span>Акция до {{ $ecoEnds->format('d.m') }}</span></div>
            @if($ecoDelta)
            <span class="eco-promo-slide__kicker">Каминные топки</span>
            @elseif($ecoFireplaces)
            <span class="eco-promo-slide__kicker">Камины и печи-камины</span>
            @else
            <span class="eco-promo-slide__kicker">Камины и топки</span>
            @endif
            <h2 class="eco-promo-slide__title"><small>до</small>−{{ $ecokaminPromoMaxDiscount }}%</h2>
            @if($ecoDelta)
            <p>Дельта и Вега −20%, Альфа −25% с белым шамотом и −15% с чёрным, чугунная Primo 700 — по акционным ценам производителя.</p>
            @elseif($ecoFireplaces)
            <p>Угловые камины Прага −30%; Мадрид, Рио, Крит, Эклипс, Родос −20%; Мюнхен, Три стекла и печи-камины Бавария −15%.</p>
            @else
            <p>Угловые камины Прага −30%, топки Дельта, Вега и Альфа, камины Мадрид, Рио, Родос, Мюнхен и печи-камины Бавария по ценам производителя.</p>
            @endif
            <div class="eco-promo-slide__timer" data-eco-countdown="{{ $ecoEnds->toIso8601String() }}" aria-label="До конца акции">
                <span><b data-unit="d">–</b><small>дней</small></span>
                <span><b data-unit="h">–</b><small>часов</small></span>
                <span><b data-unit="m">–</b><small>минут</small></span>
            </div>
            <div class="eco-promo-slide__actions">
                @if($ecoDelta)
                <a href="{{ route('promotions.ecokamin') }}#topki" class="tf-btn btn-white">Все топки по акции</a>
                <a href="{{ route('promotions.ecokamin') }}#kaminy" class="eco-promo-slide__more">Камины по акции</a>
                @elseif($ecoFireplaces)
                <a href="{{ route('promotions.ecokamin') }}#kaminy" class="tf-btn btn-white">Все камины по акции</a>
                <a href="{{ route('promotions.ecokamin') }}#bavaria" class="eco-promo-slide__more">Бавария −15%</a>
                @else
                <a href="{{ route('promotions.ecokamin') }}" class="tf-btn btn-white">Все товары акции</a>
                <a href="{{ route('promotions.ecokamin') }}#topki" class="eco-promo-slide__more">Топки до −25%</a>
                @endif
            </div>
        </div>
        <div class="eco-promo-slide__products" aria-label="{{ $ecoDelta ? 'Каминные топки по акции' : ($ecoFireplaces ? 'Камины по акции' : 'Хиты акции ЭкоКамин') }}">
            @foreach($ecokaminPromoHits->take(3) as $hit)
            @php($ecoHitArt = $ecoArt($hit))
            <a href="/{{ $hit->category->slug }}/{{ $hit->slug }}" class="eco-promo-slide__product{{ $ecoHitArt ? '' : ' eco-promo-slide__product--photo' }}">
                <span class="eco-promo-slide__media"><img loading="lazy" decoding="async" src="{{ $ecoHitArt ?: $hit->image_url }}" alt="{{ $hit->name }}"><span class="eco-promo-slide__offer">−{{ $hit->discount_percent }}%</span></span>
                <span class="eco-promo-slide__name">{{ \Illuminate\Support\Str::limit($ecoShort($hit), 30) }}</span>
                <span class="eco-promo-slide__price"><del>{{ number_format((float) $hit->price_old, 0, '.', ' ') }}</del>{{ number_format((float) $hit->price, 0, '.', ' ') }} BYN</span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@once
@push('styles')
<style>
    .eco-promo-slide{position:relative;width:100%;height:560px;color:#f6efe7;isolation:isolate;background:radial-gradient(ellipse 50% 60% at 74% 72%,rgba(232,104,40,.30),rgba(232,104,40,0) 70%),radial-gradient(ellipse 80% 90% at 70% 100%,rgba(90,40,20,.35),transparent 70%),linear-gradient(115deg,#0a0807 0%,#120d0b 42%,#1b130f 100%)}
    .eco-promo-slide:after{position:absolute;inset:0;z-index:3;pointer-events:none;box-shadow:inset 0 0 0 1px rgba(214,172,120,.18),inset 0 0 120px rgba(0,0,0,.55);border-radius:inherit;content:""}
    .eco-promo-slide__word{position:absolute;right:-1%;top:-6%;z-index:0;font-family:'Playfair Display',Georgia,'Times New Roman',serif;font-weight:700;font-size:clamp(170px,21vw,320px);line-height:1;color:transparent;-webkit-text-stroke:1px rgba(214,172,120,.14);user-select:none;white-space:nowrap}
    .eco-promo-slide__layout{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,.82fr) minmax(0,1.18fr);gap:clamp(16px,2vw,36px);align-items:center;height:100%;padding:34px clamp(28px,3.4vw,62px) 26px clamp(32px,4.6vw,80px);box-sizing:border-box}
    .eco-promo-slide__layout>*{min-width:0}
    .eco-promo-slide__word,.eco-promo-slide__title,.eco-promo-slide__timer b,.eco-promo-slide__offer,.eco-promo-slide__price{font-variant-numeric:lining-nums tabular-nums;font-feature-settings:"lnum" 1}
    .eco-promo-slide__mast{display:flex;align-items:center;gap:12px;margin-bottom:18px;color:#d6ac78;font-size:11px;font-weight:600;letter-spacing:.32em;text-transform:uppercase;white-space:nowrap}
    .eco-promo-slide__mast i{display:block;width:44px;height:1px;background:rgba(214,172,120,.6)}
    .eco-promo-slide__kicker{display:block;margin-bottom:2px;color:rgba(246,239,231,.72);font-size:13px;letter-spacing:.18em;text-transform:uppercase}
    .eco-promo-slide__title{display:flex;align-items:baseline;gap:12px;margin:0 0 10px;font-family:'Playfair Display',Georgia,'Times New Roman',serif;font-weight:700;font-size:clamp(70px,8vw,128px);line-height:.95;letter-spacing:.01em;background:linear-gradient(180deg,#fff6e8 0%,#f0cf9f 55%,#c98b4c 100%);-webkit-background-clip:text;background-clip:text;color:transparent}
    .eco-promo-slide__title small{font-family:Manrope,Arial,sans-serif;font-size:.2em;font-weight:600;letter-spacing:.2em;text-transform:uppercase}
    .eco-promo-slide__copy p{max-width:470px;margin:0 0 18px;color:rgba(246,239,231,.74);font-size:14px;line-height:1.55}
    .eco-promo-slide__timer{display:flex;align-items:stretch;width:max-content;margin-bottom:22px;border:1px solid rgba(214,172,120,.3);border-radius:14px}
    .eco-promo-slide__timer span{display:flex;flex-direction:column;align-items:center;min-width:70px;padding:8px 10px 6px}
    .eco-promo-slide__timer span+span{border-left:1px solid rgba(214,172,120,.22)}
    .eco-promo-slide__timer b{font-family:'Playfair Display',Georgia,serif;font-size:28px;line-height:1;color:#f0cf9f;font-variant-numeric:tabular-nums}
    .eco-promo-slide__timer small{margin-top:4px;color:rgba(246,239,231,.55);font-size:10px;text-transform:uppercase;letter-spacing:.12em}
    .eco-promo-slide__actions{display:flex;flex-wrap:wrap;align-items:center;gap:6px 18px}
    .eco-promo-slide__more{color:#ecd9c2;font-size:14px;text-decoration:underline;text-underline-offset:4px}
    .eco-promo-slide__more:hover{color:#f0b67a}
    .eco-promo-slide__products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:end;gap:clamp(10px,1.6vw,26px);height:100%}
    .eco-promo-slide__product{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;min-width:0;color:#f6efe7;text-decoration:none;text-align:center}
    .eco-promo-slide__media{position:relative;display:flex;align-items:flex-end;justify-content:center;width:100%;height:340px;margin-bottom:14px}
    .eco-promo-slide__media img{max-width:100%;max-height:100%;object-fit:contain;filter:drop-shadow(0 26px 30px rgba(0,0,0,.55));transition:transform .35s ease}
    .eco-promo-slide__product:hover .eco-promo-slide__media img{transform:translateY(-6px)}
    .eco-promo-slide__product--photo .eco-promo-slide__media img{padding:10px;border-radius:16px;background:#fff}
    .eco-promo-slide__offer{position:absolute;top:0;right:4%;display:flex;align-items:center;justify-content:center;width:60px;height:60px;border:1px solid rgba(240,207,159,.7);border-radius:50%;background:rgba(14,10,8,.72);color:#f0cf9f;font-family:'Playfair Display',Georgia,serif;font-size:17px;font-weight:700;backdrop-filter:blur(6px)}
    .eco-promo-slide__name{display:block;max-width:100%;margin-bottom:4px;overflow:hidden;font-size:12px;font-weight:600;line-height:1.3;letter-spacing:.06em;text-overflow:ellipsis;text-transform:uppercase;white-space:nowrap}
    .eco-promo-slide__price{display:flex;align-items:baseline;justify-content:center;gap:8px;color:#f0cf9f;font-family:'Playfair Display',Georgia,serif;font-size:19px;font-weight:700;white-space:nowrap}
    .eco-promo-slide__price del{color:rgba(246,239,231,.45);font-family:Manrope,Arial,sans-serif;font-size:12px;font-weight:500}
    @media(max-width:1439px){.eco-promo-slide{height:520px}.eco-promo-slide__media{height:300px}}
    @media(max-width:1199px){.eco-promo-slide__copy p{display:none}.eco-promo-slide__layout{grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr)}}
    @media(max-width:991px){.eco-promo-slide{height:500px}.eco-promo-slide__layout{grid-template-columns:minmax(0,1fr) minmax(0,1fr);padding:30px}.eco-promo-slide__products{grid-template-columns:repeat(2,minmax(0,1fr))}.eco-promo-slide__product:nth-child(3){display:none}.eco-promo-slide__media{height:270px}}
    @media(max-width:767px){.eco-promo-slide{height:640px}.eco-promo-slide__word{display:none}.eco-promo-slide__layout{display:flex;flex-direction:column;align-items:stretch;gap:12px;padding:28px 20px 40px}.eco-promo-slide__mast{margin-bottom:12px;font-size:10px}.eco-promo-slide__mast i{width:24px}.eco-promo-slide__title{font-size:78px;margin-bottom:10px}.eco-promo-slide__timer{margin-bottom:14px}.eco-promo-slide__timer span{min-width:58px;padding:6px 8px 5px}.eco-promo-slide__timer b{font-size:22px}.eco-promo-slide__products{flex:1;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;min-height:0}.eco-promo-slide__product:nth-child(3){display:flex}.eco-promo-slide__media{flex:1;height:auto;min-height:0;margin-bottom:6px}.eco-promo-slide__offer{top:auto;bottom:4px;right:-2px;width:40px;height:40px;font-size:12px}.eco-promo-slide__name{font-size:10px;letter-spacing:.03em}.eco-promo-slide__price{flex-wrap:wrap;gap:0 4px;font-size:16px;white-space:normal}.eco-promo-slide__price del{font-size:9px}.eco-promo-slide__actions{gap:4px 14px}}
    @media(max-width:400px){.eco-promo-slide__title{font-size:66px}}
    @media(max-width:767px) and (max-height:760px){.eco-promo-slide__layout{gap:8px;padding:22px 18px 36px}.eco-promo-slide__mast{margin-bottom:8px}.eco-promo-slide__title{font-size:62px;margin-bottom:6px}.eco-promo-slide__timer{margin-bottom:10px}.eco-promo-slide__timer span{padding:4px 8px 3px}.eco-promo-slide__timer b{font-size:19px}.eco-promo-slide__actions .tf-btn{padding-block:10px}}
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
