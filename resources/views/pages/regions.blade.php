@extends('layouts.amerce')
@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2">
        <div class="container">
            <div class="breadcrumbs justify-content-center"><a href="/">Главная</a><span aria-hidden="true">›</span><span>Магазины в городах</span></div>
            <h1 class="h2 mt-3">Магазины KOTLOV в городах Беларуси</h1>
            <p class="text-body-1 cl-text-2 mt-3">Адреса, график работы и региональные каталоги действующих партнёров.</p>
        </div>
    </section>
    @include('partials.regional-stores', ['fullDirectory' => true])
</main>
@endsection
