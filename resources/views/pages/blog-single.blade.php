@extends('layouts.amerce')

@section('content')
@php
    $shareUrl = $canonical ?? url()->current();
    $shareTitle = $post->title;
@endphp

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
                    @if ($post->views_count)
                        <span class="cl-text-3">·</span>
                        <span class="text-caption-01 cl-text-2">{{ $post->views_count }} просмотров</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="section-blog-single blog-article-section">
        <div class="main-blog-single">
            <div class="container">
                <div class="row">

                    <div class="col-lg-12">
                        <div class="blog-image blog-article-cover">
                            <img loading="eager" width="1410" height="600"
                                src="{{ $post->cover_image_url }}"
                                alt="{{ $post->title }}"
                                onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                        </div>
                    </div>

                    <div class="col-lg-8 mx-auto">
                        <article class="blog-content blog-article-content">

                            @if ($post->excerpt)
                                <div class="blog-article-lead">{!! $post->excerpt !!}</div>
                            @endif

                            <div class="blog-share-panel" aria-label="Поделиться статьёй">
                                <span>Поделиться</span>
                                <a href="https://t.me/share/url?url={{ rawurlencode($shareUrl) }}&text={{ rawurlencode($shareTitle) }}" target="_blank" rel="noopener">Telegram</a>
                                <a href="viber://forward?text={{ rawurlencode($shareTitle . ' ' . $shareUrl) }}">Viber</a>
                                <a href="https://wa.me/?text={{ rawurlencode($shareTitle . ' ' . $shareUrl) }}" target="_blank" rel="noopener">WhatsApp</a>
                                <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener">Facebook</a>
                                <button type="button" class="blog-copy-link" data-copy-url="{{ $shareUrl }}">Скопировать ссылку</button>
                            </div>

                            <div class="blog-body">
                                {!! $post->content !!}
                            </div>

                            <div class="blog-article-cta">
                                <div>
                                    <p class="blog-article-cta-title">Нужен подбор теплового насоса под дом?</p>
                                    <p>Подскажем по мощности, радиаторам, тёплому полу, резервному котлу и бюджету монтажа.</p>
                                </div>
                                <a href="/contacts" class="tf-btn btn-fill radius-4">Получить консультацию</a>
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

                        </article>
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

<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('.blog-copy-link');
    if (!button) return;

    navigator.clipboard.writeText(button.dataset.copyUrl).then(function () {
        var oldText = button.textContent;
        button.textContent = 'Ссылка скопирована';
        setTimeout(function () {
            button.textContent = oldText;
        }, 1800);
    });
});
</script>
@endsection
