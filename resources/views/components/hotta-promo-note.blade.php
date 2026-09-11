@once
    @push('styles')
        <style>
            .hotta-promo-note{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 17px;border:1px solid #f2c8c5;border-radius:14px;background:#fff5f4;color:#26282b}.hotta-promo-note__badge{display:inline-block;margin-bottom:4px;color:#d73d37;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.hotta-promo-note p{margin:0;color:#696f75;font-size:13px;line-height:1.45}.hotta-promo-note a{flex:0 0 auto;color:#17191c;font-size:13px;font-weight:700;text-decoration:underline;text-underline-offset:3px}@media(max-width:575px){.hotta-promo-note{align-items:flex-start;flex-direction:column}.hotta-promo-note a{width:100%}}
        </style>
    @endpush
@endonce

<aside class="hotta-promo-note mb-16" aria-label="Распродажа HOTTA Ceramik">
    <div><span class="hotta-promo-note__badge">Складская распродажа</span><p>Современный контроллер XO со встроенным Wi‑Fi уже входит в комплект.</p></div>
    <a href="{{ route('promotions.hotta-ceramik') }}">Условия и расчёт →</a>
</aside>
