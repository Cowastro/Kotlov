{{-- The first home slide: the cast-iron ЭкоКамин PRIMO 700 with the grill grate as a gift, a magazine cover like ПРАЙМ / ШАМАН. --}}
@php
    $primo = \Illuminate\Support\Facades\Cache::remember('home:spotlight-primo-700', 600, fn () => \App\Models\Product::query()
        ->with('category:id,slug')
        ->where('slug', \App\Support\Catalog\SpotlightProducts::PRIMO_700)
        ->where('is_active', true)
        ->first(['id', 'slug', 'name', 'category_id', 'price', 'price_old', 'is_sale', 'sale_campaign', 'sale_ends_at']));
    $primoUrl = $primo ? '/'.($primo->category->slug ?? 'topki').'/'.$primo->slug : null;
    $primoOld = $primo && $primo->isPublicSale() && (float) $primo->price_old > (float) $primo->price ? (float) $primo->price_old : null;
@endphp
@if ($primo && (float) $primo->price > 0)
<div class="swiper-slide">
    <div class="slider-wrap slideshow-wrap rounded-20 overflow-hidden prime-cover primo-cover">
        <span class="prime-cover__word" aria-hidden="true">PRIMO</span>
        <img class="prime-cover__art" width="877" height="937" loading="eager" decoding="async" src="{{ asset('img/banners/ecokamin-primo/primo-cover.webp') }}" alt="Чугунная каминная топка ЭкоКамин PRIMO 700 с огнём и решёткой-гриль">
        <div class="prime-cover__copy">
            <div class="prime-cover__mast"><span>ЭкоКамин</span><i></i><span>Хит сезона</span></div>
            <span class="prime-cover__kicker">Чугунная каминная топка</span>
            <h2 class="prime-cover__title">PRIMO 700</h2>
            <div class="primo-cover__gift"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 7h-2.2A3 3 0 0 0 12 4.1 3 3 0 0 0 6.2 7H4a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h1v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8h1a1 1 0 0 0 1-1V8a1 1 0 0 0-1-1Zm-5-1a1 1 0 1 1 0 2h-2V7a1 1 0 0 1 2-1ZM9 6a1 1 0 0 1 2 1v1H9a1 1 0 0 1 0-2ZM5 9h6v1H5V9Zm2 3h4v7H7v-7Zm10 7h-4v-7h4v7Zm2-9h-6V9h6v1Z"/></svg>Решётка-гриль в подарок</div>
            <p>В духе знаменитой французской Invicta Philos: двойные чугунные стенки, вторичный дожиг, «чистое стекло» и режим гриль-барбекю прямо в топке.</p>
            <div class="prime-cover__facts"><span>12 кВт</span><span>КПД 76%</span><span>Встроенный шибер</span><span>Поленья до 50 см</span></div>
            <div class="primo-cover__buy">
                <span class="primo-cover__price">@if ($primoOld)<del>{{ number_format($primoOld, 0, '.', ' ') }}</del>@endif{{ number_format((float) $primo->price, 0, '.', ' ') }} <small>BYN</small></span>
                <a href="{{ $primoUrl }}" class="tf-btn btn-white">Купить PRIMO 700</a>
                <a href="/blog/ekokamin-primo-700-reshetka-gril-v-podarok" class="prime-cover__more">О топке</a>
            </div>
        </div>
    </div>
</div>
@once
@push('styles')
<style>
    .primo-cover{background:radial-gradient(ellipse 40% 62% at 74% 60%,rgba(232,104,40,.36),rgba(232,104,40,0) 70%),radial-gradient(ellipse 80% 90% at 70% 100%,rgba(90,40,20,.35),transparent 70%),linear-gradient(115deg,#0a0807 0%,#120d0b 42%,#1b130f 100%)}
    .primo-cover .prime-cover__art{right:4%;height:90%;max-width:50%;bottom:2%}
    .primo-cover .prime-cover__word{font-size:clamp(140px,17vw,270px);top:auto;bottom:4%;right:-1%}
    .primo-cover .prime-cover__title{white-space:nowrap;font-size:clamp(60px,6.6vw,108px);font-variant-numeric:lining-nums;font-feature-settings:"lnum" 1}
    .primo-cover__gift{display:inline-flex;align-items:center;gap:9px;margin:2px 0 16px;padding:8px 16px 8px 12px;border:1px solid rgba(240,207,159,.7);border-radius:999px;background:linear-gradient(135deg,rgba(201,139,76,.28),rgba(201,139,76,.08));color:#f6e3c6;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 0 24px rgba(232,104,40,.25)}
    .primo-cover__gift svg{color:#f0cf9f}
    .primo-cover__buy{display:flex;flex-wrap:wrap;align-items:center;gap:10px 22px}
    .primo-cover__price{display:flex;align-items:baseline;gap:10px;color:#f0cf9f;font-family:'Playfair Display',Georgia,serif;font-size:34px;font-weight:700;line-height:1;font-variant-numeric:lining-nums;font-feature-settings:"lnum" 1;white-space:nowrap}
    .primo-cover__price del{color:rgba(246,239,231,.45);font-family:Manrope,Arial,sans-serif;font-size:15px;font-weight:500}
    .primo-cover__price small{font-family:Manrope,Arial,sans-serif;font-size:13px;font-weight:600;letter-spacing:.12em}
    @media(max-width:1199px){.primo-cover .prime-cover__art{max-width:48%;height:82%}}
    @media(max-width:991px){.primo-cover .prime-cover__art{max-width:46%;height:72%}.primo-cover .prime-cover__facts{display:none}}
    @media(max-width:767px){.primo-cover{background:radial-gradient(ellipse 70% 40% at 50% 84%,rgba(232,104,40,.34),transparent 72%),linear-gradient(180deg,#0a0807 0%,#140e0b 55%,#1d140f 100%)}.primo-cover .prime-cover__word{display:none}.primo-cover .prime-cover__copy{padding:24px 20px 0}.primo-cover .prime-cover__title{font-size:54px}.primo-cover .prime-cover__copy p{display:none}.primo-cover__gift{margin-bottom:12px;padding:6px 12px 6px 10px;font-size:11px}.primo-cover__price{font-size:26px}.primo-cover__buy{gap:8px 14px}.primo-cover .prime-cover__art{right:50%;left:auto;transform:translateX(50%);width:auto;height:44%;max-width:86%;bottom:2%}}
    @media(max-width:400px){.primo-cover .prime-cover__title{font-size:46px}}
</style>
@endpush
@endonce
@endif
