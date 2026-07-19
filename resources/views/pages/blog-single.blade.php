@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="/blog" class="text-caption-01 cl-text-3 link">Статьи</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @if ($post->category)
                        <a href="/blog?category={{ $post->category->slug }}" class="text-caption-01 cl-text-3 link">
                            {{ $post->category->name }}
                        </a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @endif
                    <p class="text-caption-01">{{ Str::limit($post->title, 50) }}</p>
                </div>
                <h1 class="h3 mt-16">{{ $post->title }}</h1>
                <div class="entry-meta d-flex flex-wrap align-items-center gap-12 justify-content-center mt-8">
                    <div class="meta-item meta-date d-flex align-items-center gap-6">
                        <i class="icon icon-CalendarBlank cl-text-3"></i>
                        <span class="text-caption-01 cl-text-2">{{ $post->published_at->translatedFormat('d F Y') }}</span>
                    </div>
                    @if ($post->category)
                        <span class="cl-text-3">·</span>
                        <a href="/blog?category={{ $post->category->slug }}" class="text-caption-01 cl-text-2 link">
                            {{ $post->category->name }}
                        </a>
                    @endif
                    @if ($post->author)
                        <span class="cl-text-3">·</span>
                        <span class="text-caption-01 cl-text-2">{{ $post->author->name }}</span>
                    @endif
                </div>
                <div class="nav-post-list mt-12">
                    <a href="/blog" class="link nav-all-post nav-post-link">
                        <i class="icon icon-SquaresFour"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="section-blog-single">
        <div class="main-blog-single">
            <div class="container">
                <div class="row">

                    <div class="col-lg-12">
                        <div class="blog-image">
                            <img loading="lazy" width="1410" height="600"
                                src="{{ $post->cover_image_url }}"
                                alt="{{ $post->title }}"
                                onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                        </div>
                    </div>

                    <div class="col-lg-8 mx-auto">
                        <div class="blog-content">

                            @if ($post->views_count)
                                <div class="entry-meta mb-8">
                                    <div class="meta-item">
                                        <i class="icon icon-Eye cl-text-3"></i>
                                        <span class="text-caption-01 cl-text-2">{{ $post->views_count }} просмотров</span>
                                    </div>
                                </div>
                            @endif

                            @if ($post->excerpt)
                                <div class="text text-body-1 s1 fw-medium">{!! $post->excerpt !!}</div>
                            @endif

                            <div class="d-grid gap-12 blog-body">
                                {!! $post->content !!}
                            </div>

                            @if ($post->tags && count($post->tags) > 0)
                                <div class="box-social-tag">
                                    <div class="tags-right d-flex align-items-center flex-wrap gap-8">
                                        <p>Теги:</p>
                                        @foreach ($post->tags as $tag)
                                            <a href="/blog?tag={{ urlencode($tag) }}" class="tag-item text-caption-01">
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
        </div>
    </section>

    @if ($related->count() > 0)
        <section class="section-related flat-spacing">
            <div class="container">
                <div class="sect-heading text-center">
                    <h3 class="s-title">Похожие статьи</h3>
                    @if ($post->category)
                        <p class="s-desc text-body-1 cl-text-2">
                            Ещё материалы из рубрики «{{ $post->category->name }}»
                        </p>
                    @endif
                </div>
                <h4 class="d-none">Похожие статьи</h4>
                <div class="tf-grid-layout sm-col-2 xl-col-3">
                    @foreach ($related as $item)
                        <article class="article-blog hover-img">
                            <a href="/blog/{{ $item->slug }}" class="blog-image img-style">
                                <img loading="lazy" width="450" height="307"
                                    src="{{ $item->cover_image_url }}"
                                    alt="{{ $item->title }}"
                                    onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                            </a>
                            <div class="blog-content">
                                <div class="d-flex align-items-center gap-12 mb-4">
                                    <p class="entry-date text-caption-01 fw-semibold cl-text-3">
                                        {{ $item->published_at->translatedFormat('d F Y') }}
                                    </p>
                                    @if ($item->category)
                                        <a href="/blog?category={{ $item->category->slug }}"
                                           class="text-caption-01 cl-text-2 link">
                                            {{ $item->category->name }}
                                        </a>
                                    @endif
                                </div>
                                <h5 class="entry-title">
                                    <a href="/blog/{{ $item->slug }}" class="link-underline link">
                                        {{ $item->title }}
                                    </a>
                                </h5>
                                @if ($item->excerpt)
                                    <p class="entry-desc cl-text-2">{{ Str::limit(strip_tags($item->excerpt), 120) }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</main>
@endsection
