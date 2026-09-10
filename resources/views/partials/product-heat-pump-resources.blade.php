<style>
    .hp-product-resources { background: #f5f6f3; }
    .hp-product-resource-card { display: flex; flex-direction: column; height: 100%; overflow: hidden; border: 1px solid #e1e3df; border-radius: 20px; background: #fff; color: inherit; transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease; }
    .hp-product-resource-card:hover { transform: translateY(-4px); border-color: #d0d3cd; box-shadow: 0 18px 38px rgba(28, 31, 27, .08); }
    .hp-product-resource-card__image { aspect-ratio: 16 / 9; overflow: hidden; background: #e9ebe7; }
    .hp-product-resource-card__image img { width: 100%; height: 100%; object-fit: cover; transition: transform .45s ease; }
    .hp-product-resource-card:hover .hp-product-resource-card__image img { transform: scale(1.035); }
    .hp-product-resource-card__body { display: flex; flex: 1; flex-direction: column; padding: 22px 24px 24px; }
    .hp-product-resource-card__meta { display: flex; align-items: center; gap: 9px; margin-bottom: 12px; color: #73776f; font-size: 12px; font-weight: 600; letter-spacing: .035em; text-transform: uppercase; }
    .hp-product-resource-card__meta::before { width: 22px; height: 2px; background: #ef4b4b; content: ''; }
    .hp-product-resource-card__title { display: -webkit-box; overflow: hidden; margin-bottom: 12px; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
    .hp-product-resource-card__excerpt { display: -webkit-box; overflow: hidden; margin-bottom: 20px; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
    .hp-product-resource-card__more { display: inline-flex; align-items: center; gap: 8px; margin-top: auto; color: #171817; font-weight: 600; }
    .hp-product-resource-card__more span { transition: transform .2s ease; }
    .hp-product-resource-card:hover .hp-product-resource-card__more span { transform: translateX(4px); }
    .hp-product-resources__cta { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 28px; overflow: hidden; margin-top: 28px; padding: 30px 34px; border-radius: 20px; background: #191b19; color: #fff; }
    .hp-product-resources__cta::before { position: absolute; top: 0; bottom: 0; left: 0; width: 4px; background: #ef4b4b; content: ''; }
    .hp-product-resources__cta-copy { position: relative; max-width: 760px; }
    .hp-product-resources__cta-label { margin-bottom: 8px; color: #ff6b63; font-size: 12px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
    .hp-product-resources__cta h4, .hp-product-resources__cta p { color: #fff; }
    .hp-product-resources__cta p { opacity: .7; }
    .hp-product-resources__cta .tf-btn { min-width: 220px; border-color: #fff; background: #fff; color: #111; }

    @media (max-width: 767px) {
        .hp-product-resources .sect-heading { text-align: left !important; }
        .hp-product-resource-card__body { padding: 19px 20px 21px; }
        .hp-product-resources__cta { align-items: stretch; flex-direction: column; padding: 25px 22px 22px; }
        .hp-product-resources__cta .tf-btn { width: 100%; min-width: 0; }
    }
</style>

<section class="flat-spacing hp-product-resources" aria-labelledby="heat-pump-product-help">
    <div class="container">
        <div class="sect-heading text-center mb-32">
            <h3 class="s-title" id="heat-pump-product-help">Подбор и монтаж этой модели</h3>
            <p class="s-desc text-body-1 cl-text-2">
                Реальные объекты и практические разборы монтажной команды KOTLOV.
            </p>
        </div>

        <div class="row g-20 align-items-stretch">
            @foreach ($articles as $article)
                <div class="col-md-4">
                    <a href="/blog/{{ $article->slug }}" class="hp-product-resource-card">
                        <div class="hp-product-resource-card__image">
                            <img loading="lazy" width="720" height="405"
                                src="{{ $article->cover_image_url }}"
                                alt="{{ $article->title }}"
                                onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                        </div>
                        <div class="hp-product-resource-card__body">
                            <p class="hp-product-resource-card__meta">Реальный объект</p>
                            <h5 class="hp-product-resource-card__title">{{ $article->title }}</h5>
                            <p class="hp-product-resource-card__excerpt text-body-2 cl-text-2">
                                {{ Str::limit(strip_tags($article->excerpt ?? ''), 135) }}
                            </p>
                            <span class="hp-product-resource-card__more">Читать материал <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="hp-product-resources__cta">
            <div class="hp-product-resources__cta-copy">
                <p class="hp-product-resources__cta-label">Расчёт под ваш дом</p>
                <h4 class="mb-8">Проверим, подходит ли эта модель именно вашему объекту</h4>
                <p class="text-body-1 mb-0">Учтём теплопотери, тёплый пол или радиаторы, горячую воду и доступную электрическую мощность.</p>
            </div>
            <a href="/montazh-teplovyh-nasosov#heat-pump-request" class="tf-btn flex-shrink-0"
               data-analytics-event="heat_pump_lead_click">Получить расчёт</a>
        </div>
    </div>
</section>
