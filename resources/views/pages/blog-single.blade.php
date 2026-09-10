@extends('layouts.amerce')

@push('styles')
<style>
    .kotlov-blog-content .blog-author-box,
    .kotlov-blog-content .blog-next-box {
        position: relative;
        overflow: hidden;
        border: 1px solid #e7e4de;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 10px 35px rgba(25, 31, 35, .045);
    }
    .kotlov-blog-content .blog-author-box { padding: 26px 28px 26px 31px !important; }
    .kotlov-blog-content .blog-author-box::before {
        position: absolute;
        top: 24px;
        bottom: 24px;
        left: 0;
        width: 3px;
        border-radius: 0 3px 3px 0;
        background: #f4554c;
        content: '';
    }
    .kotlov-blog-content .blog-next-box { padding: 30px !important; }
    .kotlov-blog-content .blog-next-link {
        padding: 18px 20px !important;
        border: 1px solid #ebe8e2;
        border-radius: 14px;
        background: #fff;
        transition: border-color .2s ease, transform .2s ease, box-shadow .2s ease;
    }
    .kotlov-blog-content .blog-next-link:hover {
        transform: translateY(-2px);
        border-color: #d7d2ca;
        box-shadow: 0 9px 24px rgba(25, 31, 35, .07);
    }
    @media (max-width: 767px) {
        .kotlov-blog-content .blog-author-box { padding: 22px 20px 22px 24px !important; }
        .kotlov-blog-content .blog-author-box::before { top: 20px; bottom: 20px; }
        .kotlov-blog-content .blog-next-box { padding: 24px 20px !important; }
        .kotlov-blog-content .blog-next-link { padding: 17px 18px !important; }
    }
</style>
@endpush

@section('content')
@php
    $shareUrl = $canonical ?? url()->current();
    $shareTitle = $post->title;
    $shareDescription = $post->meta_description ?: strip_tags($post->excerpt ?? $description ?? '');
    $shareText = trim($shareTitle . ($shareDescription ? ' — ' . Str::limit($shareDescription, 140) : ''));
@endphp

<main id="wrapper">

    <div class="section-page-title-single flat-spacing-3">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="/blog" class="text-caption-01 cl-text-3 link">Статьи</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">{{ Str::limit($post->title, 60) }}</p>
                </div>
                <div class="nav-post-list">
                    <a href="/blog" class="link nav-all-post nav-post-link">
                        <i class="icon icon-SquaresFour"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="section-blog-single">
        <div class="main-blog-single">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="blog-image">
                            <img loading="eager" width="1600" height="900"
                                src="{{ $post->cover_image_url }}"
                                alt="{{ $post->title }}"
                                onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                        </div>
                    </div>

                    <div class="col-lg-8 mx-auto">
                        <article class="blog-content kotlov-blog-content">
                            <div class="blog-heading">
                                @if ($post->category)
                                    <div class="entry-tag fw-medium">{{ $post->category->name }}</div>
                                @endif
                                <h3 class="entry-title">{{ $post->title }}</h3>
                                <div class="entry-meta">
                                    <div class="meta-item">
                                        <i class="icon icon-User"></i>
                                        <a href="/about" class="text-body-1 link">{{ $post->author?->name ?: 'Команда KOTLOV' }}</a>
                                    </div>
                                    <div class="br-line type-vertical"></div>
                                    <div class="meta-item meta-date">
                                        <i class="icon icon-CalendarBlank"></i>
                                        <span class="text-body-1">{{ $post->published_at->translatedFormat('d F Y') }}</span>
                                    </div>
                                    @if ($post->views_count)
                                        <div class="br-line type-vertical"></div>
                                        <div class="meta-item">
                                            <i class="icon icon-Eye"></i>
                                            <span class="text-body-1">{{ $post->views_count }} просмотров</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if ($post->excerpt)
                                <div class="d-grid gap-12">
                                    <div class="text text-body-1 s1">{!! $post->excerpt !!}</div>
                                </div>
                            @endif

                            <div class="blog-body d-grid gap-16">
                                {!! $post->content !!}
                            </div>

                            <aside class="blog-author-box mt-32 p-24" id="about-material" aria-label="Об авторе материала">
                                <p class="text-caption-01 text-primary fw-semibold mb-8">О МАТЕРИАЛЕ</p>
                                <h5 class="mb-8">Подготовлено командой KOTLOV</h5>
                                <p class="text-body-2 cl-text-2 mb-0">
                                    Практические материалы о подборе и монтаже отопительного оборудования.
                                    Характеристики конкретного проекта уточняются инженерным расчётом.
                                    <a href="/about" class="link text-decoration-underline">Подробнее о компании</a>.
                                </p>
                            </aside>

                            @if (($isHeatPumpContent ?? false) && ($heatPumpLinks ?? collect())->isNotEmpty())
                                <aside class="blog-next-box mt-32 p-24" id="heat-pump-next-step" aria-label="Материалы по подбору теплового насоса">
                                    <h4 class="mb-8">Следующий шаг по вашему проекту</h4>
                                    <p class="text-body-1 cl-text-2 mb-20">
                                        Перейдите от общего вопроса к моделям, расчёту и опыту на реальных объектах.
                                    </p>
                                    <div class="tf-grid-layout sm-col-2">
                                        @foreach ($heatPumpLinks as $link)
                                            <a href="{{ $link['url'] }}" class="blog-next-link p-16 link d-block">
                                                <h6 class="mb-6">{{ $link['title'] }}</h6>
                                                <p class="text-caption-01 cl-text-2 mb-0">{{ $link['text'] }}</p>
                                            </a>
                                        @endforeach
                                    </div>
                                </aside>
                            @endif

                            <div class="box-social-tag">
                                <div class="tags-right d-flex align-items-center flex-wrap gap-8">
                                    <p>Теги:</p>
                                    @if ($post->tags && count($post->tags) > 0)
                                        @foreach ($post->tags as $tag)
                                            <a href="/blog?tag={{ urlencode($tag) }}" class="tag-item text-caption-01">{{ $tag }}</a>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="social-left">
                                    <p>Поделиться:</p>
                                    <ul class="tf-social-icon-2">
                                        <li>
                                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}&quote={{ rawurlencode($shareText) }}" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook">
                                                <i class="icon icon-FacebookLogo"></i>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="https://t.me/share/url?url={{ rawurlencode($shareUrl) }}&text={{ rawurlencode($shareTitle) }}" target="_blank" rel="noopener" aria-label="Telegram" title="Telegram">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.8 4.7 18.5 20c-.2 1-.8 1.2-1.6.8l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.4 13.8 1.7 12.3c-1-.3-1-1 .2-1.5L20.3 3.7c.9-.3 1.7.2 1.5 1z"/></svg>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="viber://forward?text={{ rawurlencode($shareTitle . ' ' . $shareUrl) }}" aria-label="Viber" title="Viber">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.5 2.7A13.5 13.5 0 0 0 6.4 2.3C3.6 3.1 2.2 5 2 8.1a30 30 0 0 0 .2 6c.3 2.1 1.5 3.7 3.4 4.5v3c0 .5.6.8 1 .4l3.1-2.7c3.2.2 5.8-.1 7.8-1.1 2.5-1.2 3.7-3.5 3.9-7 .2-3.8-1.1-6.7-3.9-8.5zm-1.4 12.7c-.5.4-1.2.7-1.9.4-2.3-.9-4.1-2.3-5.4-4.2-.8-1.1-1.3-2.2-1.6-3.2-.2-.7.1-1.4.7-1.8l.7-.4c.4-.2.9-.1 1.1.3l1 1.8c.2.4.1.8-.2 1.1l-.4.4c.5 1 1.3 1.8 2.3 2.4l.5-.5c.3-.3.8-.4 1.1-.1l1.7 1.2c.4.3.5.8.3 1.2l-.4.7zm-3.4-9.5c2.7.2 4.2 1.7 4.4 4.4h-1.3c-.2-2-1.2-3-3.1-3.1V5.9zm.1 2.1c1.6.2 2.4 1 2.5 2.6H14c-.1-.9-.4-1.2-1.3-1.4V8z"/></svg>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="https://wa.me/?text={{ rawurlencode($shareTitle . ' ' . $shareUrl) }}" target="_blank" rel="noopener" aria-label="WhatsApp" title="WhatsApp">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.1 3.9A11.4 11.4 0 0 0 2.2 17.6L1 22.1l4.6-1.2a11.4 11.4 0 0 0 5.5 1.4h.1A11.4 11.4 0 0 0 20.1 3.9zM12.1 20a9 9 0 0 1-4.6-1.3l-.3-.2-2.7.7.7-2.6-.2-.3a9 9 0 1 1 7.1 3.7zm5-6.7c-.3-.1-1.7-.8-1.9-.9-.3-.1-.5-.1-.7.1l-.9 1.1c-.2.2-.3.2-.6.1a7.4 7.4 0 0 1-3.7-3.2c-.2-.3 0-.4.1-.6l.5-.6c.1-.2.2-.4.3-.6.1-.2 0-.4 0-.6l-.9-2c-.2-.5-.5-.4-.7-.4h-.6c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.8 0 1.6 1.2 3.2 1.4 3.4.2.2 2.3 3.6 5.7 5 .8.4 1.5.6 2 .7.8.3 1.6.2 2.2.1.7-.1 1.7-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.4z"/></svg>
                                            </a>
                                        </li>
                                        <li>
                                            <button type="button" class="blog-copy-link" data-copy-url="{{ $shareUrl }}" aria-label="Скопировать ссылку" title="Скопировать ссылку">
                                                <i class="icon icon-Files"></i>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="group-direc">
                                <a href="/blog" class="btn-direc prev link">
                                    <p class="fw-semibold text-decoration-underline">Все статьи</p>
                                    <p class="name-post h6 fw-medium">Вернуться в блог KOTLOV</p>
                                </a>
                                <span class="br-line type-vertical"></span>
                                <a href="/contacts" class="btn-direc next link">
                                    <p class="fw-semibold text-decoration-underline">Консультация</p>
                                    <p class="name-post h6 fw-medium">Подобрать оборудование</p>
                                </a>
                            </div>
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
                        <p class="s-desc text-body-1 cl-text-2">Ещё материалы из рубрики «{{ $post->category->name }}»</p>
                    @endif
                </div>
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
                                        <a href="/blog?category={{ $item->category->slug }}" class="text-caption-01 cl-text-2 link">
                                            {{ $item->category->name }}
                                        </a>
                                    @endif
                                </div>
                                <h5 class="entry-title">
                                    <a href="/blog/{{ $item->slug }}" class="link-underline link">{{ $item->title }}</a>
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
        var oldTitle = button.getAttribute('title');
        button.classList.add('is-copied');
        button.setAttribute('title', 'Ссылка скопирована');
        setTimeout(function () {
            button.classList.remove('is-copied');
            button.setAttribute('title', oldTitle);
        }, 1800);
    });
});
</script>
@endsection
