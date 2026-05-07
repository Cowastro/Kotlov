<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">

    <title>{{ $title ?? 'KOTLOV — маркетплейс отопления' }}</title>

    <meta name="description" content="{{ $description ?? 'KOTLOV — маркетплейс отопления, котлов, печей, каминов, дымоходов и монтажных услуг в Беларуси.' }}">
    <meta name="keywords" content="{{ $keywords ?? 'котлы, печи, камины, дымоходы, отопление, монтаж, маркетплейс отопления' }}">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">

    {{-- Fonts --}}
    <link rel="stylesheet" href="{{ asset('assets/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/icon/icomoon/style.css') }}">

    {{-- CSS Amerce --}}
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/photoswipe.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kotlov.css') }}">

    {{-- Custom CSS --}}
   {{-- <link rel="stylesheet" href="{{ asset('css/styles.css') }}"> --}}

    {{-- Favicon --}}
    <link rel="shortcut icon" href="{{ asset('assets/images/logo/favicon.ico') }}">
    <link rel="apple-touch-icon-precomposed" href="{{ asset('assets/images/logo/favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

   @include('partials.amerce-header')

    @yield('content')

 {{-- @include('partials.footer') --}}

    {{-- @include('partials.mobile-nav') --}}
{{-- @include('partials.region-popup') --}}
{{-- @include('partials.callback-popup') --}}

    {{-- JS --}}
   <script src="assets/js/plugin/jquery.min.js"></script>
    <script src="assets/js/plugin/bootstrap.min.js"></script>
    <script src="assets/js/plugin/swiper-bundle.min.js"></script>
    <script src="assets/js/plugin/bootstrap-select.min.js"></script>
    <script src="assets/js/plugin/count-down.js"></script>
    <script src="assets/js/plugin/infinityslide.js"></script>
    <script src="assets/js/plugin/wow.min.js"></script>
    <script src="assets/js/zoom.js"></script>
    <script src="assets/js/carousel.js"></script>
    <script src="assets/js/plugin/drift.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/plugin/photoswipe-lightbox.umd.min.js"></script>
    <script src="assets/js/plugin/photoswipe.umd.min.js"></script> 

</body>
</html>