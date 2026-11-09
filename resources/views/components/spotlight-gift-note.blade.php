@props(['product'])
@php($spotlightGift = \App\Support\Catalog\SpotlightProducts::gift($product->slug))
@if ($spotlightGift)
@once
    @push('styles')
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
        <style>
            .spotlight-gift{position:relative;display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:14px 18px 14px 14px;overflow:hidden;border:1px solid rgba(201,139,76,.55);border-radius:16px;background:radial-gradient(ellipse 60% 120% at 0% 50%,rgba(232,104,40,.28),transparent 70%),linear-gradient(115deg,#0d0a08,#1d140f);color:#f6efe7}
            .spotlight-gift__art{flex:0 0 92px;width:92px;height:92px;border-radius:12px;background:#fff center/cover no-repeat}
            .spotlight-gift__kicker{display:block;margin-bottom:2px;color:#d6ac78;font-size:11px;font-weight:600;letter-spacing:.22em;text-transform:uppercase}
            .spotlight-gift__title{display:block;margin-bottom:4px;font-family:'Playfair Display',Georgia,serif;font-size:22px;font-weight:700;line-height:1.15;background:linear-gradient(180deg,#fff6e8,#f0cf9f 55%,#c98b4c);-webkit-background-clip:text;background-clip:text;color:transparent}
            .spotlight-gift p{margin:0;color:rgba(246,239,231,.74);font-size:13px;line-height:1.45}
            @media(max-width:575px){.spotlight-gift{align-items:flex-start}.spotlight-gift__art{flex-basis:72px;width:72px;height:72px}.spotlight-gift__title{font-size:19px}}
        </style>
    @endpush
@endonce
<aside class="spotlight-gift" aria-label="Подарок к покупке">
    <span class="spotlight-gift__art" style="background-image:url('{{ asset('img/products/ecokamin/primo-700/primo-700-gift.webp') }}')" aria-hidden="true"></span>
    <div>
        <span class="spotlight-gift__kicker">Подарок к топке</span>
        <strong class="spotlight-gift__title">{{ $spotlightGift }}</strong>
        <p>Решётка для гриля и барбекю — уже в комплекте, без доплаты. Жарьте мясо, рыбу и овощи прямо над углями в топке.</p>
    </div>
</aside>
@endif
