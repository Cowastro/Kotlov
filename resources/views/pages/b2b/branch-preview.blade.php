@extends('layouts.amerce', ['title' => 'Предпросмотр магазина — KOTLOV'])
@section('content')
<main class="container" style="padding-top:30px;padding-bottom:30px">
    <div style="padding:16px;background:#fff4df;border-radius:12px;margin-bottom:24px"><strong>Предпросмотр магазина</strong><p>Эта версия доступна только партнёру и KOTLOV. Публикация и городской сайт включаются отдельно после проверки.</p><p>Адрес городского сайта: {{ $city->storefrontUrl() }}</p></div>
    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:24px;align-items:center;margin-bottom:32px;padding:24px;border-radius:20px;background:#f5f3f0">
        <div><small>KOTLOV · {{ $city->name }}</small><h1 style="font-size:clamp(28px,5vw,48px)">{{ $primaryBranch->storefront_title ?: $primaryBranch->name }}</h1><p>{{ $primaryBranch->storefront_intro }}</p></div>
        @if($primaryBranch->hero_image)<img src="{{ Storage::url($primaryBranch->hero_image) }}" alt="{{ $primaryBranch->name }}" style="width:100%;height:320px;object-fit:cover;border-radius:16px">@endif
    </section>
    @include('partials.home-city-branch')
</main>
@endsection
