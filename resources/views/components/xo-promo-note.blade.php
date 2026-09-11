@once
    @push('styles')
        <style>
            .xo-promo-note { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:15px 17px; border:1px solid #f2c8c5; border-radius:14px; background:#fff5f4; color:#26282b; }
            .xo-promo-note__badge { display:inline-block; margin-bottom:4px; color:#d73d37; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
            .xo-promo-note p { margin:0; color:#696f75; font-size:13px; line-height:1.45; }
            .xo-promo-note a { flex:0 0 auto; color:#17191c; font-size:13px; font-weight:700; text-decoration:underline; text-underline-offset:3px; }
            @media(max-width:575px){.xo-promo-note{align-items:flex-start;flex-direction:column}.xo-promo-note a{width:100%}}
        </style>
    @endpush
@endonce

<aside class="xo-promo-note mb-16" aria-label="Акция на KOTLOV XO Ceramic PRO">
    <div><span class="xo-promo-note__badge">Акция −10%</span><p>Модель 100 кВт в наличии. Wi‑Fi встроен, экономия 1 440 BYN.</p></div>
    <a href="{{ route('promotions.xo-ceramic-pro') }}">Условия и расчёт →</a>
</aside>
