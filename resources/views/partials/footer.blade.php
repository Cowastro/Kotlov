<footer class="footer-min">
    <div class="container">

        <div class="footer-min__top">
            <a class="footer-min__logo" href="/">
                <img src="/img/logo2.png" alt="Kotlov.by">
            </a>

            <a class="footer-min__phone" href="tel:{{ $pCall1 ?? '+375293544041' }}">
                {{ $phone1 ?? '+375 (29) 354-40-41' }}
            </a>
        </div>

        <div class="footer-min__middle">
            <div class="footer-min__address">
                {{ $address ?? 'Минск, ул. Селицкого, 39Б, каб. 23' }}
            </div>

            <div class="footer-min__time">
                пн–пт: {{ $time1 ?? '9:00–18:00' }}
            </div>
        </div>

        <div class="footer-min__bottom">
            © 2011–{{ date('Y') }} KOTLOV
        </div>

    </div>

    {!! $cookieInfo ?? '' !!}
</footer>