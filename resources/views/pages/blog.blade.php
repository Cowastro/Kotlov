@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @if (request('category') && $activeCategory)
                        <a href="/blog" class="text-caption-01 cl-text-3 link">Статьи</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <p class="text-caption-01">{{ $activeCategory->name }}</p>
                    @else
                        <p class="text-caption-01">Статьи</p>
                    @endif
                </div>
                <h1 class="h3">{{ $activeCategory ? $activeCategory->name : 'Статьи об отоплении' }}</h1>
                <p class="text-body-1 cl-text-2">
                    Экспертные советы по выбору отопительного оборудования,<br class="d-none d-lg-block">
                    обзоры техники и руководства по монтажу.
                </p>
            </div>
        </div>
    </section>

    {{-- Блог --}}
    <section class="section-blog flat-spacing">
        <div class="container">
            <div class="row">

                {{-- Статьи --}}
                <div class="col-lg-8">
                    <div class="tf-grid-layout sm-col-2">

                        @forelse ($posts as $post)
                            <article class="article-blog hover-img">
                                <a href="/blog/{{ $post->slug }}" class="blog-image img-style">
                                    <img loading="lazy" width="450" height="307"
                                        src="{{ $post->cover_image ? asset('storage/' . $post->cover_image) : asset('img/blog/placeholder.jpg') }}"
                                        alt="{{ $post->title }}"
                                        onerror="this.src='{{ asset('img/blog/placeholder.jpg') }}'">
                                </a>
                                <div class="blog-content">
                                    <div class="d-flex align-items-center gap-12 mb-4">
                                        <p class="entry-date text-caption-01 fw-semibold cl-text-3">
                                            {{ $post->published_at->translatedFormat('d F Y') }}
                                        </p>
                                        @if ($post->category)
                                            <a href="/blog?category={{ $post->category->slug }}"
                                               class="text-caption-01 cl-text-2 link">
                                                {{ $post->category->name }}
                                            </a>
                                        @endif
                                    </div>
                                    <h5 class="entry-title">
                                        <a href="/blog/{{ $post->slug }}" class="link-underline link">
                                            {{ $post->title }}
                                        </a>
                                    </h5>
                                    @if ($post->excerpt)
                                        <p class="entry-desc cl-text-2">
                                            {{ Str::limit($post->excerpt, 120) }}
                                        </p>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="wd-full text-center py-48">
                                <i class="icon icon-Article fs-48 cl-text-3 mb-16"></i>
                                <p class="h5 cl-text-2">Статей пока нет</p>
                            </div>
                        @endforelse

                        {{-- Пагинация --}}
                        @if ($posts->hasPages())
                            <div class="wd-full">
                                <div class="tf-page-pagination">
                                    @if ($posts->onFirstPage())
                                        <span class="pag-item disabled">
                                            <i class="icon icon-CaretLeftThin"></i>
                                        </span>
                                    @else
                                        <a href="{{ $posts->previousPageUrl() }}" class="pag-item">
                                            <i class="icon icon-CaretLeftThin"></i>
                                        </a>
                                    @endif

                                    @foreach ($posts->getUrlRange(
                                        max(1, $posts->currentPage() - 2),
                                        min($posts->lastPage(), $posts->currentPage() + 2)
                                    ) as $page => $url)
                                        @if ($page == $posts->currentPage())
                                            <p class="pag-item active">{{ $page }}</p>
                                        @else
                                            <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                                        @endif
                                    @endforeach

                                    @if ($posts->hasMorePages())
                                        <a href="{{ $posts->nextPageUrl() }}" class="pag-item">
                                            <i class="icon icon-CaretRightThin"></i>
                                        </a>
                                    @else
                                        <span class="pag-item disabled">
                                            <i class="icon icon-CaretRightThin"></i>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                    </div>
                </div>

                {{-- Сайдбар --}}
                <div class="col-lg-4 d-none d-lg-block">
                    <div class="blog-sidebar sidebar-content-wrap sticky-top">

                        {{-- Поиск --}}
                        <div class="sidebar-item">
                            <div class="sb-search">
                                <form action="/blog" method="GET" class="form-search-blog">
                                    <fieldset>
                                        <input class="style-stroke-bottom" type="text" name="q"
                                            value="{{ request('q') }}"
                                            placeholder="Поиск по статьям..." required>
                                    </fieldset>
                                    <button type="submit" class="btn-action link">
                                        <i class="icon icon-MagnifyingGlass"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Категории --}}
                        @if ($categories->count() > 0)
                            <div class="sidebar-item">
                                <h5 class="sb-title">Категории</h5>
                                <ul class="sb-category">
                                    <li>
                                        <a href="/blog" class="{{ !request('category') ? 'fw-semibold' : '' }}">
                                            Все статьи
                                            <span class="count">({{ $totalCount }})</span>
                                        </a>
                                    </li>
                                    @foreach ($categories as $cat)
                                        <li>
                                            <a href="/blog?category={{ $cat->slug }}"
                                               class="{{ request('category') === $cat->slug ? 'fw-semibold' : '' }}">
                                                {{ $cat->name }}
                                                <span class="count">({{ $cat->posts_count }})</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Последние статьи --}}
                        @if ($recentPosts->count() > 0)
                            <div class="sidebar-item">
                                <h5 class="sb-title">Последние статьи</h5>
                                <ul class="sb-recent">
                                    @foreach ($recentPosts as $recent)
                                        <li class="recent-item">
                                            <a href="/blog/{{ $recent->slug }}" class="image">
                                                <img loading="lazy" width="90" height="90"
                                                    src="{{ $recent->cover_image ? asset('storage/' . $recent->cover_image) : asset('img/blog/placeholder.jpg') }}"
                                                    alt="{{ $recent->title }}"
                                                    onerror="this.src='{{ asset('img/blog/placeholder.jpg') }}'">
                                            </a>
                                            <div class="meta">
                                                <p class="meta-date text-caption-01 cl-text-2">
                                                    {{ $recent->published_at->translatedFormat('d F Y') }}
                                                </p>
                                                <a href="/blog/{{ $recent->slug }}"
                                                   class="meta-name link-underline link fw-medium">
                                                    {{ Str::limit($recent->title, 60) }}
                                                </a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- Теги --}}
                        @if ($tags->count() > 0)
                            <div class="sidebar-item">
                                <h5 class="sb-title">Теги</h5>
                                <div class="sb-tags d-flex flex-wrap gap-8">
                                    @foreach ($tags as $tag)
                                        <a href="/blog?tag={{ $tag }}"
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