@if ($posts->isNotEmpty())
    <section class="section-blog home-blog-section flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 has-col-right">
                <div class="wow fadeInUp">
                    <h3 class="s-title">Свежие статьи об отоплении</h3>
                    <p class="s-desc text-body-1 cl-text-2">
                        Новые материалы, разборы оборудования и реальные объекты KOTLOV.
                    </p>
                </div>
                <div class="col-right wow fadeInUp" data-wow-delay="0.1s">
                    <a href="{{ route('blog') }}" class="tf-btn btn-white btn-stroke">Все статьи</a>
                </div>
            </div>

            <div dir="ltr" class="swiper tf-swiper" data-preview="3" data-tablet="2" data-mobile-sm="2"
                data-mobile="1" data-space-lg="30" data-space-md="20" data-space="10" data-pagination="1"
                data-pagination-sm="2" data-pagination-md="2" data-pagination-lg="3">
                <div class="swiper-wrapper">
                    @foreach ($posts as $post)
                        <div class="swiper-slide">
                            <article class="article-blog hover-img wow fadeInUp"
                                @if ($loop->index > 0) data-wow-delay="{{ min($loop->index, 2) / 10 }}s" @endif>
                                <a href="{{ route('blog.show', $post->slug) }}" class="blog-image img-style">
                                    <img loading="lazy" width="900" height="614"
                                        src="{{ $post->cover_image_url }}"
                                        alt="{{ $post->title }}"
                                        onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                                    @if ($post->category)
                                        <div class="wrap-tags d-flex gap-12">
                                            <span class="tag text-caption-01">{{ mb_strtoupper($post->category->name) }}</span>
                                        </div>
                                    @endif
                                </a>
                                <div class="blog-content">
                                    <time class="entry-date text-caption-01 fw-semibold cl-text-3"
                                        datetime="{{ $post->published_at->format('Y-m-d') }}">
                                        {{ $post->published_at->translatedFormat('d F Y') }}
                                    </time>
                                    <h4 class="entry-title">
                                        <a href="{{ route('blog.show', $post->slug) }}" class="link-underline link">
                                            {{ $post->title }}
                                        </a>
                                    </h4>
                                    @if ($post->excerpt)
                                        <p class="entry-desc cl-text-2">
                                            {{ Str::limit(strip_tags($post->excerpt), 145) }}
                                        </p>
                                    @endif
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
                <div class="sw-line-default style-2 tf-sw-pagination"></div>
            </div>
        </div>
    </section>
@endif
