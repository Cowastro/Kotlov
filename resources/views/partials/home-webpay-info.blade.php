@php
    $homeSeller = config('legal.seller', []);
    $homeSellerName = $homeSeller['short_name'] ?? 'ООО СБГ бай';
    $homeSellerUnp = $homeSeller['unp'] ?? '193265863';
    $homeSellerAddress = $homeSeller['address'] ?? 'Республика Беларусь, г. Минск, ул. Селицкого, 39Б, каб. 23';
    $homeSellerEmail = $homeSeller['email'] ?? 'info@kotlov.by';
@endphp

<section class="home-webpay flat-spacing" id="payment-and-hours" aria-labelledby="home-webpay-title">
    <div class="container">
        <div class="home-webpay__shell">
            <div class="home-webpay__main">
                <span class="home-webpay__eyebrow">Официальная информация интернет-магазина</span>
                <h2 id="home-webpay-title">Оплата картой и режим работы</h2>
                <p>Заказ можно оплатить банковской картой через защищённую систему WEBPAY после того, как менеджер подтвердит наличие, состав и итоговую сумму.</p>
                <div class="home-webpay__logos" aria-label="Платёжные системы">
                    <img src="{{ asset('assets/images/payment/visa.svg') }}" alt="Visa" width="92" height="46">
                    <img src="{{ asset('assets/images/payment/master-card.svg') }}" alt="Mastercard" width="92" height="46">
                    <img src="{{ asset('assets/images/payment/belkart.svg') }}" alt="Белкарт" width="128" height="46">
                    <img src="{{ asset('assets/images/payment/mir.svg') }}" alt="МИР" width="112" height="46">
                    <a href="https://www.webpay.by/" target="_blank" rel="noopener noreferrer" aria-label="Перейти на официальный сайт WEBPAY"><img src="{{ asset('assets/images/payment/webpay.svg') }}" alt="WEBPAY" width="142" height="46"></a>
                </div>
                <a href="{{ url('/dostavka#order-procedure') }}" class="tf-btn btn-fill animate-btn mt-24">Правила заказа, оплаты и доставки</a>
            </div>
            <div class="home-webpay__details">
                <div class="home-webpay__detail">
                    <span>Режим работы</span>
                    <strong>Онлайн-заказы — круглосуточно, 24/7</strong>
                    <strong>Консультации — ежедневно до 20:00</strong>
                    <small>Самовывоз — по предварительному согласованию</small>
                </div>
                <div class="home-webpay__detail">
                    <span>Продавец</span>
                    <strong>{{ $homeSellerName }}</strong>
                    <small>УНП {{ $homeSellerUnp }}</small>
                    <small>{{ $homeSellerAddress }}</small>
                </div>
                <div class="home-webpay__contacts">
                    @foreach(($homeSeller['phones'] ?? ['+375 (29) 354-40-41', '+375 (33) 377-70-93']) as $phone)
                        <a href="tel:{{ preg_replace('/\D+/', '', $phone) }}">{{ $phone }}</a>
                    @endforeach
                    <a href="mailto:{{ $homeSellerEmail }}">{{ $homeSellerEmail }}</a>
                </div>
            </div>
        </div>
    </div>
</section>

@once
@push('styles')
<style>
.home-webpay{padding-top:20px}.home-webpay__shell{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:42px;padding:42px;border:1px solid #e8e8e8;border-radius:22px;background:linear-gradient(135deg,#f7faf8 0%,#fff 58%)}.home-webpay__eyebrow{display:block;margin-bottom:9px;color:#de3c31;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.home-webpay h2{margin-bottom:13px;font-size:36px}.home-webpay__main>p{max-width:720px;margin-bottom:20px;color:#5f6662;line-height:1.65}.home-webpay__logos{display:flex;flex-wrap:wrap;align-items:center;gap:8px}.home-webpay__logos img{display:block;max-width:142px;height:46px;padding:7px 9px;border:1px solid #e3e7e4;border-radius:8px;background:#fff;object-fit:contain}.home-webpay__details{display:flex;flex-direction:column;justify-content:center;gap:20px;padding:26px;border-radius:16px;background:#111;color:#fff}.home-webpay__detail span{display:block;margin-bottom:7px;color:#9aa29e;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase}.home-webpay__detail strong,.home-webpay__detail small{display:block}.home-webpay__detail strong{margin-bottom:4px;font-size:14px}.home-webpay__detail small{margin-top:5px;color:#c3c8c5;line-height:1.5}.home-webpay__contacts{display:flex;flex-wrap:wrap;gap:7px 14px;padding-top:16px;border-top:1px solid #333}.home-webpay__contacts a{color:#fff;text-decoration:underline;text-underline-offset:3px}
@media(max-width:991px){.home-webpay__shell{grid-template-columns:1fr;gap:24px;padding:30px}.home-webpay__details{display:grid;grid-template-columns:1fr 1fr}.home-webpay__contacts{grid-column:1/-1}}
@media(max-width:575px){.home-webpay__shell{padding:22px 18px;border-radius:16px}.home-webpay h2{font-size:28px}.home-webpay__details{display:flex;padding:20px}.home-webpay__logos img{max-width:112px;height:42px}}
</style>
@endpush
@endonce
