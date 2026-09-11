@once
    @push('styles')
        <style>
            .evo-promo-note { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:15px 17px; border:1px solid #f2c8c5; border-radius:14px; background:#fff5f4; color:#26282b; }
            .evo-promo-note__badge { display:inline-block; margin-bottom:4px; color:#d73d37; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
            .evo-promo-note p { margin:0; color:#696f75; font-size:13px; line-height:1.45; }
            .evo-promo-note a { flex:0 0 auto; color:#17191c; font-size:13px; font-weight:700; text-decoration:underline; text-underline-offset:3px; }
            @media(max-width:575px){.evo-promo-note{align-items:flex-start;flex-direction:column}.evo-promo-note a{width:100%}}
        </style>
    @endpush
@endonce

<aside class="evo-promo-note mb-16" aria-label="Распродажа KOTLOV XO EVO 26 кВт">
    <div><span class="evo-promo-note__badge">Распродажа −20%</span><p>В наличии ровно 2 горелки. Цена 5 120 BYN, экономия 1 280 BYN.</p></div>
    <a href="{{ route('promotions.xo-evo-26') }}">Условия и расчёт →</a>
</aside>
