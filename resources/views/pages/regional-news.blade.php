@extends('layouts.amerce')
@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2">
        <div class="container">
            <div class="breadcrumbs justify-content-center"><a href="/">Главная</a><span aria-hidden="true">›</span><span>{{ $newsCity ? 'Новости города' : 'Новости регионов' }}</span></div>
            <h1 class="h2 mt-3">{{ $heading }}</h1>
            <p class="text-body-1 cl-text-2 mt-3">{{ $description }}</p>
        </div>
    </section>
    <section class="container flat-spacing pt-0">
        @if($posts->isNotEmpty())
            @include('partials.regional-news-cards', ['newsPosts' => $posts])
            @if($posts->hasPages())
            <nav class="d-flex flex-wrap gap-3 align-items-center mt-4" aria-label="Страницы новостей">
                @if($posts->previousPageUrl())<a class="tf-btn btn-white btn-stroke" href="{{ $posts->previousPageUrl() }}">← Назад</a>@endif
                <span>Страница {{ $posts->currentPage() }} из {{ $posts->lastPage() }}</span>
                @if($posts->nextPageUrl())<a class="tf-btn btn-white btn-stroke" href="{{ $posts->nextPageUrl() }}">Далее →</a>@endif
            </nav>
            @endif
        @else
            <p class="text-center">Новости магазина скоро появятся здесь. Адрес, график и каталог уже доступны на главной странице города.</p>
        @endif
        <div class="mt-4"><a class="tf-btn btn-white btn-stroke" href="{{ $newsCity ? $newsCity->storefrontUrl().'/' : route('regions') }}">{{ $newsCity ? 'Магазин и каталог' : 'Все магазины KOTLOV' }}</a></div>
    </section>
</main>
@endsection
