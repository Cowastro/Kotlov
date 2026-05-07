<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">

    <title>{{ $title ?? 'Kotlov.by' }}</title>

    <meta name="keywords" content="{{ $keywords ?? '' }}">
    <meta name="description" content="{{ $description ?? '' }}">

    <meta property="og:title" content="{{ $ogTitle ?? $title ?? 'Kotlov.by' }}">
    <meta property="og:description" content="{{ $ogDescription ?? $description ?? '' }}">
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:url" content="{{ $ogUrl ?? url()->current() }}">
    <meta property="og:image" content="{{ $ogImage ?? asset('img/logo.png') }}">
    <meta property="og:site_name" content="kotlov.by">
    <meta property="og:image:width" content="800">
    <meta property="og:image:height" content="600">

    @isset($schemaJson)
        <script type="application/ld+json">
            {!! $schemaJson !!}
        </script>
    @endisset

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="yandex-verification" content="7a9017a97d9459a9">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    @isset($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endisset

    <link type="image/ico" href="/favicon.ico" rel="shortcut icon">

    {{-- Старые стили сайта --}}
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/awesome.css">
    <link rel="stylesheet" href="/css/animate.min.css">

    {{-- Новый шаблон Amerce --}}
    <link rel="stylesheet" href="{{ asset('amerce/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('amerce/assets/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('amerce/assets/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet" href="{{ asset('amerce/assets/css/styles.css') }}">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Старые JS сайта --}}
    <script defer src="/js/jquery.js"></script>
    <script defer src="/js/jquery-ui.js"></script>
    <script defer src="/js/inputmask.js"></script>
    <script defer src="/js/nouislider.js"></script>
    <script defer src="/js/wow.min.js"></script>
    <script defer src="/js/general.js"></script>

    {{-- Google Analytics 4 --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-7QPT1BYQQF"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-7QPT1BYQQF');
    </script>

    {{-- Yandex.Metrika --}}
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {
                if (document.scripts[j].src === r) { return; }
            }
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

        ym(102254764, "init", {
            clickmap:true,
            trackLinks:true,
            accurateTrackBounce:true,
            webvisor:true
        });
    </script>

    <script type="text/javascript" src="//web.it-center.by/nw" charset="UTF-8" async></script>

    
</head>

<body class="preload">

    <noscript>
        <div>
            <img src="https://mc.yandex.ru/watch/102254764"
                 style="position:absolute; left:-9999px;"
                 alt="">
        </div>
    </noscript>

    @include('partials.mobile-nav')
    @include('partials.header')

    @yield('content')

    @include('partials.footer')

    <div class="container">
        <div class="basket {{ $orderActive ?? '' }}">
            <a href="/cart" class="bsk-text">
                Мой заказ
                <span class="count order-num">{{ $cartCount ?? 0 }}</span>
            </a>

            <a href="/compare" class="bsk-text">
                Сравнение
                <span class="count compare-num">{{ $compareCount ?? 0 }}</span>
            </a>
        </div>
    </div>

    <div class="go-call phone-ring"></div>

    <a href="tel:{{ $phoneCall ?? '+375293544041' }}" class="call-container">
        <i></i>
    </a>

    @include('partials.callback-popup')
    @include('partials.region-popup')

    {{-- Amerce JS: jquery НЕ подключаем повторно --}}
    <script src="{{ asset('amerce/assets/js/plugin/bootstrap.min.js') }}"></script>
    <script src="{{ asset('amerce/assets/js/plugin/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('amerce/assets/js/plugin/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('amerce/assets/js/plugin/infiniteslide.js') }}"></script>
    <script src="{{ asset('amerce/assets/js/plugin/parallaxie.js') }}"></script>
    <script src="{{ asset('amerce/assets/js/carousel.js') }}"></script>
    <script src="{{ asset('amerce/assets/js/main.js') }}"></script>

    {{-- Umnico widget --}}
    <script type="text/javascript">
        document.umnicoWidgetHash = 'd23f029234c8a1ab66264142802cc4e7';
        var x = document.createElement('script');
        x.src = 'https://umnico.com/assets/widget-loader.js';
        x.type = 'text/javascript';
        x.charset = 'UTF-8';
        x.async = true;
        document.body.appendChild(x);
    </script>

</body>
</html>