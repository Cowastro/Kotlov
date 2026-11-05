@extends('layouts.amerce')
@push('styles')
<style>
.local-article .breadcrumbs{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.local-article{max-width:940px;margin:auto}.local-article h1{font-size:clamp(30px,4vw,48px);line-height:1.15;margin:18px 0}.local-article__cover{display:block;width:100%;max-height:480px;object-fit:cover;border-radius:18px;margin:24px 0}.local-article__body{font-size:17px;line-height:1.8;overflow-wrap:anywhere}.local-article__body h2{font-size:28px;margin:28px 0 14px}.local-article__body p{margin-bottom:18px}.local-article__body a{text-decoration:underline;text-underline-offset:3px}.local-article__body img{max-width:100%;height:auto}.local-article__body ul{padding-left:24px;list-style:disc}.local-article__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px;padding-top:24px;border-top:1px solid #e5e5e5}
</style>
@endpush
@section('content')
<main id="wrapper"><section class="container flat-spacing"><article class="local-article">
    <div class="breadcrumbs"><a href="/">{{ $newsCity->name }}</a><span aria-hidden="true">›</span><a href="/news">Новости магазина</a></div>
    <h1>{{ $post->title }}</h1>
    <p class="cl-text-2"><time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->format('d.m.Y') }}</time> · KOTLOV · {{ $newsCity->name }}</p>
    <img class="local-article__cover" src="{{ $post->cover_image_url }}" alt="{{ $post->title }}" width="1600" height="900">
    <div class="local-article__body">{!! $post->content !!}</div>
    <div class="local-article__actions"><a class="tf-btn" href="{{ $newsCity->storefrontUrl() }}/#city-branch">Контакты магазина</a><a class="tf-btn btn-white btn-stroke" href="/catalog">Каталог в {{ $newsCity->name_title }}</a><a class="tf-btn btn-white btn-stroke" href="/news">Все новости города</a></div>
</article></section></main>
@endsection
