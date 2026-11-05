@once
@push('styles')
<style>
.regional-stores{padding:clamp(24px,4vw,48px);border:1px solid #e9e6e2;border-radius:24px;background:#f7f5f2}.regional-stores__heading{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:24px}.regional-stores__eyebrow{color:#de3c31;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px}.regional-stores__heading h2{font-size:clamp(26px,3vw,38px);margin-bottom:10px}.regional-stores__intro{max-width:700px;color:#62676b;line-height:1.6}.regional-stores__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.regional-store{overflow:hidden;min-width:0;border:1px solid #e5e2de;border-radius:16px;background:#fff}.regional-store__image{display:block;width:100%;height:210px;object-fit:cover;object-position:center 42%}.regional-store__body{padding:22px}.regional-store h3{font-size:26px;margin-bottom:8px}.regional-store__name{font-size:14px;font-weight:600;margin-bottom:10px}.regional-store__address,.regional-store__hours{color:#62676b;font-size:14px;line-height:1.55;margin-bottom:8px}.regional-store__link{display:inline-flex;align-items:center;gap:8px;margin-top:8px;font-weight:600;color:#c83339}.regional-stores__join{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-top:24px;padding-top:24px;border-top:1px solid #dfdcd7}.regional-stores__join strong{display:block;font-size:21px;margin-bottom:6px}.regional-stores__join p{max-width:660px;color:#62676b;line-height:1.5}.regional-stores__actions{display:flex;flex-wrap:wrap;gap:10px;flex-shrink:0}.regional-stores__actions .tf-btn{justify-content:center;padding:13px 20px}.regional-stores__news{display:inline-block;margin-top:18px;color:#5f656a;text-decoration:underline;text-underline-offset:4px}
@media(max-width:991px){.regional-stores__join{align-items:start;flex-direction:column}.regional-stores__heading{align-items:start;flex-direction:column}}
@media(max-width:575px){.regional-stores{padding:22px 16px;border-radius:18px}.regional-stores__grid{grid-template-columns:1fr}.regional-store__image{height:185px}.regional-store__body{padding:18px}.regional-stores__actions{width:100%;flex-direction:column}.regional-stores__actions .tf-btn{width:100%}}
</style>
@endpush
@endonce

<section class="container flat-spacing pt-0" aria-labelledby="regional-stores-title">
    <div class="regional-stores">
        <div class="regional-stores__heading">
            <div>
                <p class="regional-stores__eyebrow">Развиваем партнёрскую сеть</p>
                <h2 id="regional-stores-title">KOTLOV в регионах</h2>
                <p class="regional-stores__intro">Общий каталог — магазины рядом с вами. Выбирайте оборудование онлайн, знакомьтесь с образцами и уточняйте условия покупки у партнёра в своём городе.</p>
            </div>
            @unless($fullDirectory ?? false)<a href="{{ route('regions') }}" class="tf-btn btn-white btn-stroke">Все города <span aria-hidden="true">→</span></a>@endunless
        </div>
        @if($regionalStores->isNotEmpty())
        <div class="regional-stores__grid">
            @foreach(($fullDirectory ?? false) ? $regionalStores : $regionalStores->take(4) as $store)
            <article class="regional-store">
                @if($store['image'])<a href="{{ $store['url'] }}" aria-label="Магазин KOTLOV — {{ $store['city'] }}"><img class="regional-store__image" src="{{ $store['image'] }}" alt="Магазин KOTLOV — {{ $store['city'] }}" loading="lazy" width="640" height="360"></a>@endif
                <div class="regional-store__body">
                    <h3><a href="{{ $store['url'] }}">{{ $store['city'] }}</a></h3>
                    <p class="regional-store__name">{{ $store['name'] }}</p>
                    <p class="regional-store__address">{{ $store['address'] }}</p>
                    @if($store['hours'])<p class="regional-store__hours">{{ $store['hours'] }}</p>@endif
                    <a class="regional-store__link" href="{{ $store['url'] }}">Каталог и контакты магазина <span aria-hidden="true">→</span></a>
                </div>
            </article>
            @endforeach
        </div>
        @endif
        <div class="regional-stores__join">
            <div><strong>Ваш город может стать следующим</strong><p>У вас магазин, монтажная компания или сервисный центр? Подключитесь к KOTLOV: представьте компанию в своём городе и получите доступ к B2B-каталогу на согласованных партнёрских условиях.</p></div>
            <div class="regional-stores__actions">
                <a class="tf-btn" href="{{ route('partners') }}#company-connect">Стать партнёром в городе</a>
                <a class="tf-btn btn-white btn-stroke" href="{{ route('partners') }}#company-connect">Стать B2B-партнёром</a>
            </div>
        </div>
        @if($regionalNetworkNews ?? false)<a class="regional-stores__news" href="{{ url('/blog/kotlov-v-regionah-borisov-lida') }}">Новости сети: KOTLOV становится ближе — Борисов и Лида →</a>@endif
    </div>
</section>
