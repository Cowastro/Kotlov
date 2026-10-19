@extends('layouts.amerce')

@section('title', 'База знаний — техническая информация по оборудованию | KOTLOV')
@section('description', 'База знаний KOTLOV: техническая информация по отопительному и газовому оборудованию — расшифровка обозначений, варианты исполнения, таблицы производителей для скачивания.')

@php
    use App\Support\KnowledgeBase;

    $activeCategory = request('category') ? ($categories[request('category')] ?? null) : null;
    $recentItems = $all->take(4);
@endphp

@push('styles')
<style>
    .kb-list .blog-image img[src$=".png"],.kb-list .sb-recent img[src$=".png"]{object-fit:contain;background:#f7f7f7;padding:6%}
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
                    @if ($activeCategory)
                        <a href="{{ KnowledgeBase::URL }}" class="text-caption-01 cl-text-3 link">База знаний</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <p class="text-caption-01">{{ $activeCategory }}</p>
                    @else
                        <p class="text-caption-01">База знаний</p>
                    @endif
                </div>
                <h1 class="h3">{{ $activeCategory ?: 'База знаний' }}</h1>
                <p class="text-body-1 cl-text-2">
                    Техническая информация по оборудованию: расшифровка обозначений,<br class="d-none d-lg-block">
                    варианты исполнения и документы производителей для скачивания.
                </p>
            </div>
        </div>
    </section>

    <section class="section-blog flat-spacing kb-list">
        <div class="container">
            <div class="row">

                <div class="col-lg-8">
                    <div class="sb-search d-lg-none mb-24">
                        <form action="{{ KnowledgeBase::URL }}" method="GET" class="form-search-blog">
                            <fieldset>
                                <input class="style-stroke-bottom" type="text" name="q"
                                    value="{{ request('q') }}"
                                    placeholder="Поиск по базе знаний..." required>
                            </fieldset>
                            <button type="submit" class="btn-action link">
                                <i class="icon icon-MagnifyingGlass"></i>
                            </button>
                        </form>
                    </div>

                    <div class="tf-grid-layout sm-col-2">

                        @forelse ($items as $item)
                            <article class="article-blog hover-img">
                                <a href="{{ $item['url'] }}" class="blog-image img-style">
                                    <img loading="lazy" width="900" height="675"
                                        src="{{ $item['image'] }}"
                                        alt="{{ $item['title'] }}"
                                        onerror="this.onerror=null;this.src='{{ KnowledgeBase::FALLBACK_IMAGE }}'">
                                </a>
                                <div class="blog-content">
                                    <div class="blog-entry-meta mb-8">
                                        <time class="entry-date text-caption-01 fw-semibold"
                                              datetime="{{ $item['date'] }}">
                                            {{ \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d F Y') }}
                                        </time>
                                        <a href="{{ KnowledgeBase::URL }}?category={{ $item['category'] }}"
                                           class="entry-category text-caption-01 link">
                                            {{ $item['category_name'] }}
                                        </a>
                                    </div>
                                    <h5 class="entry-title">
                                        <a href="{{ $item['url'] }}" class="link-underline link">
                                            {{ $item['title'] }}
                                        </a>
                                    </h5>
                                    <p class="entry-desc cl-text-2">
                                        {{ \Illuminate\Support\Str::limit($item['excerpt'], 140) }}
                                    </p>
                                </div>
                            </article>
                        @empty
                            <div class="wd-full text-center py-48">
                                <i class="icon icon-Article fs-48 cl-text-3 mb-16"></i>
                                <p class="h5 cl-text-2">Ничего не найдено</p>
                                <a href="{{ KnowledgeBase::URL }}" class="link text-decoration-underline">Все материалы</a>
                            </div>
                        @endforelse

                    </div>
                </div>

                <div class="col-lg-4 d-none d-lg-block">
                    <div class="blog-sidebar sidebar-content-wrap sticky-top">

                        <div class="sidebar-item">
                            <div class="sb-search">
                                <form action="{{ KnowledgeBase::URL }}" method="GET" class="form-search-blog">
                                    <fieldset>
                                        <input class="style-stroke-bottom" type="text" name="q"
                                            value="{{ request('q') }}"
                                            placeholder="Поиск по базе знаний..." required>
                                    </fieldset>
                                    <button type="submit" class="btn-action link">
                                        <i class="icon icon-MagnifyingGlass"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="sidebar-item">
                            <h5 class="sb-title">Рубрики</h5>
                            <ul class="sb-category">
                                <li>
                                    <a href="{{ KnowledgeBase::URL }}" class="{{ !request('category') ? 'fw-semibold' : '' }}">
                                        Все материалы
                                        <span class="count">({{ $all->count() }})</span>
                                    </a>
                                </li>
                                @foreach ($categories as $slug => $name)
                                    <li>
                                        <a href="{{ KnowledgeBase::URL }}?category={{ $slug }}"
                                           class="{{ request('category') === $slug ? 'fw-semibold' : '' }}">
                                            {{ $name }}
                                            <span class="count">({{ $all->where('category', $slug)->count() }})</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="sidebar-item">
                            <h5 class="sb-title">Последние материалы</h5>
                            <ul class="sb-recent">
                                @foreach ($recentItems as $recent)
                                    <li class="recent-item">
                                        <a href="{{ $recent['url'] }}" class="image">
                                            <img loading="lazy" width="90" height="90"
                                                src="{{ $recent['image'] }}"
                                                alt="{{ $recent['title'] }}"
                                                onerror="this.onerror=null;this.src='{{ KnowledgeBase::FALLBACK_IMAGE }}'">
                                        </a>
                                        <div class="meta">
                                            <p class="meta-date text-caption-01 cl-text-2">
                                                {{ \Illuminate\Support\Carbon::parse($recent['date'])->translatedFormat('d F Y') }}
                                            </p>
                                            <a href="{{ $recent['url'] }}" class="meta-name link-underline link fw-medium">
                                                {{ \Illuminate\Support\Str::limit($recent['title'], 60) }}
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($tags->isNotEmpty())
                            <div class="sidebar-item">
                                <h5 class="sb-title">Теги</h5>
                                <div class="sb-tags d-flex flex-wrap gap-8">
                                    @foreach ($tags as $tag)
                                        <a href="{{ KnowledgeBase::URL }}?tag={{ urlencode($tag) }}"
                                           class="tf-btn btn-outline small radius-3 {{ request('tag') === $tag ? 'btn-primary' : '' }}">
                                            {{ $tag }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                    </div>
                </div>

            </div>
        </div>
    </section>

</main>
@endsection
