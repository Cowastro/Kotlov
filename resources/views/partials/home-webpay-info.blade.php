@php
    $homeSeller = config('legal.seller', []);
    $homeSellerName = $homeSeller['short_name'] ?? 'ООО «СанБизнесГруп»';
    $homeSellerUnp = $homeSeller['unp'] ?? '191355768';
    $homeSellerAddress = $homeSeller['address'] ?? '220075, г. Минск, ул. Селицкого, д. 39Б';
    $homeSellerEmail = $homeSeller['email'] ?? 'info@kotlov.by';
@endphp

<section class="home-webpay flat-spacing" id="payment-and-hours" aria-labelledby="home-webpay-title">
    <div class="container">
        <div class="home-webpay__shell">
            <div class="home-webpay__main">
                <span class="home-webpay__eyebrow">Официальная информация интернет-магазина</span>
                <h2 id="home-webpay-title">Оплата картой и режим работы</h2>
                <p>Заказ можно оплатить банковской картой через защищённую систему WEBPAY после того, как менеджер подтвердит наличие, состав и итоговую сумму.</p>
                <a class="home-webpay__logos" href="https://www.webpay.by/" target="_blank" rel="noopener noreferrer" aria-label="Платёжные системы WEBPAY, Visa, Mastercard и БЕЛКАРТ — перейти на официальный сайт WEBPAY">
                    <img src="{{ asset('assets/images/payment/webpay-payment-systems.png') }}" alt="WEBPAY, Visa, Visa Secure, Mastercard SecureCode, БЕЛКАРТ и БЕЛКАРТ ИнтернетПароль" width="7944" height="550">
                </a>
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
                    <a class="home-webpay__requisites" href="{{ url('/rekvizity') }}">Полные реквизиты</a>
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
.home-webpay{padding-top:20px}.home-webpay__shell{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:42px;padding:42px;border:1px solid #e8e8e8;border-radius:22px;background:linear-gradient(135deg,#f7faf8 0%,#fff 58%)}.home-webpay__eyebrow{display:block;margin-bottom:9px;color:#de3c31;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.home-webpay h2{margin-bottom:13px;font-size:36px}.home-webpay__main>p{max-width:720px;margin-bottom:20px;color:#5f6662;line-height:1.65}.home-webpay__logos{display:block;max-width:760px;padding:13px 16px;border:1px solid #e3e7e4;border-radius:10px;background:#fff}.home-webpay__logos img{display:block;width:100%;height:auto}.home-webpay__details{display:flex;flex-direction:column;justify-content:center;gap:20px;padding:26px;border-radius:16px;background:#111;color:#fff}.home-webpay__detail span{display:block;margin-bottom:7px;color:#9aa29e;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase}.home-webpay__detail strong,.home-webpay__detail small{display:block}.home-webpay__detail strong{margin-bottom:4px;font-size:14px}.home-webpay__detail small{margin-top:5px;color:#c3c8c5;line-height:1.5}.home-webpay__requisites{display:inline-block;margin-top:9px;color:#fff;text-decoration:underline;text-underline-offset:3px}.home-webpay__contacts{display:flex;flex-wrap:wrap;gap:7px 14px;padding-top:16px;border-top:1px solid #333}.home-webpay__contacts a{color:#fff;text-decoration:underline;text-underline-offset:3px}
@media(max-width:991px){.home-webpay__shell{grid-template-columns:1fr;gap:24px;padding:30px}.home-webpay__details{display:grid;grid-template-columns:1fr 1fr}.home-webpay__contacts{grid-column:1/-1}}
@media(max-width:575px){.home-webpay__shell{padding:22px 18px;border-radius:16px}.home-webpay h2{font-size:28px}.home-webpay__details{display:flex;padding:20px}.home-webpay__logos{padding:9px 10px}}
</style>
@endpush
@endonce
