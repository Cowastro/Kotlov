@extends('layouts.amerce')

@section('title', 'База знаний — техническая информация по оборудованию | KOTLOV')
@section('description', 'База знаний KOTLOV: техническая информация по отопительному и газовому оборудованию — расшифровка обозначений, варианты исполнения, таблицы производителей для скачивания.')

@push('styles')
<style>
    .kb{padding-bottom:72px}
    .kb__section + .kb__section{margin-top:44px}
    .kb__section > h2{margin-bottom:6px}
    .kb__section > p{margin:0 0 20px;color:var(--text-2)}
    .kb__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
    .kb__card{display:flex;flex-direction:column;padding:24px;border:1px solid var(--line);border-radius:18px;background:#fff}
    .kb__tag{align-self:flex-start;margin-bottom:12px;padding:3px 10px;border-radius:999px;background:var(--bg);font-size:12px;color:var(--text-2)}
    .kb__card h3{margin:0 0 8px;font-size:19px;line-height:1.3}
    .kb__card h3 a{color:#111}
    .kb__card p{flex:1;margin:0;color:var(--text-2);font-size:14px;line-height:1.55}
    .kb__links{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin-top:16px;font-size:14px}
    .kb__links a{color:#111;font-weight:600;text-decoration:underline;text-underline-offset:4px}
    .kb__links .kb__open{text-decoration:none;padding:8px 16px;border-radius:999px;background:#111;color:#fff}
    @media(max-width:767px){.kb{padding-bottom:48px}.kb__card{padding:20px}}
</style>
@endpush

@section('content')
<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">База знаний</p>
                </div>
                <h1 class="h3">База знаний</h1>
                <p class="text-body-1 cl-text-2">
                    Техническая информация по оборудованию: расшифровка обозначений,
                    варианты исполнения и документы производителей для скачивания.
                </p>
            </div>
        </div>
    </section>

    <section class="kb flat-spacing">
        <div class="container">
            @foreach ($sections as $section)
                <div class="kb__section">
                    <h2>{{ $section['title'] }}</h2>
                    <p>{{ $section['description'] }}</p>
                    <div class="kb__grid">
                        @foreach ($section['items'] as $item)
                            <article class="kb__card">
                                <span class="kb__tag">{{ $item['category'] }}</span>
                                <h3><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h3>
                                <p>{{ $item['description'] }}</p>
                                <div class="kb__links">
                                    <a class="kb__open" href="{{ $item['url'] }}">Открыть</a>
                                    @foreach ($item['downloads'] ?? [] as $download)
                                        <a href="{{ $download['url'] }}" @if (str_ends_with($download['url'], '.pdf')) target="_blank" rel="noopener" @endif>Скачать {{ $download['label'] }}</a>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

</main>
@endsection
