@extends('layouts.amerce')
@include('partials.branch-social-styles')
@push('styles')
<style>
.local-article .breadcrumbs{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.local-shop-nav{display:flex;flex-wrap:wrap;gap:10px;margin:22px 0}.local-shop-nav a{padding:8px 14px;border:1px solid #e3dfd8;border-radius:999px;font-size:14px;text-decoration:none!important}.local-shop-photos{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin:26px 0}.local-shop-photos figure,.local-shop-photo-wide{margin:0;min-width:0}.local-shop-photos a,.local-shop-photo-wide>a{display:block;width:100%}.local-shop-photos img,.local-shop-photo-wide img{display:block;width:100%;height:auto;border-radius:14px}.local-shop-photos figcaption,.local-shop-photo-wide figcaption{font-size:14px;line-height:1.5;color:#687078;margin-top:10px}.local-shop-photo-wide{margin:26px 0}.local-shop-photo-wide img{max-height:620px;object-fit:contain;background:#f7f5f2}.local-shop-visit{padding:24px;background:#f7f5f2;border:1px solid #e8e3dc;border-radius:16px;margin:24px 0}.local-shop-visit h3{font-size:23px;margin-bottom:14px}.local-shop-visit dl{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px 18px;margin-bottom:24px}.local-shop-visit dd{margin:0;font-weight:600}.local-shop-map{display:block;width:100%;height:340px;border:0;border-radius:16px;margin:22px 0}.local-article__body ol{list-style:decimal;padding-left:24px}.local-article__body li{margin-bottom:12px}
@media(max-width:575px){.local-shop-photos{grid-template-columns:1fr}.local-shop-visit{padding:20px 16px}.local-shop-visit dl{grid-template-columns:1fr;gap:3px}.local-shop-visit dd{margin-bottom:10px}.local-shop-map{height:280px}}
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
    @include('partials.article-share')
    <div class="local-article__actions"><a class="tf-btn" href="{{ $newsCity->storefrontUrl() }}/#city-branch">Контакты магазина</a><a class="tf-btn btn-white btn-stroke" href="/catalog">Каталог в {{ $newsCity->name_title }}</a><a class="tf-btn btn-white btn-stroke" href="/news">Все новости города</a></div>
</article></section></main>
@endsection
