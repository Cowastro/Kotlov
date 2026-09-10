<section class="flat-spacing bg-surface" aria-labelledby="heat-pump-product-help">
    <div class="container">
        <div class="sect-heading text-center mb-32">
            <h3 class="s-title" id="heat-pump-product-help">Подбор и монтаж этой модели</h3>
            <p class="s-desc text-body-1 cl-text-2">
                Проверьте совместимость с вашим домом, температурой подачи и системой отопления.
            </p>
        </div>
        <div class="row g-20 align-items-stretch">
            @foreach ($articles as $article)
                <div class="col-md-4">
                    <a href="/blog/{{ $article->slug }}" class="d-block p-24 rounded-4 bg-white h-100 link">
                        <p class="text-caption-01 text-primary fw-semibold mb-8">ПОЛЕЗНЫЙ МАТЕРИАЛ</p>
                        <h5 class="mb-10">{{ $article->title }}</h5>
                        <p class="text-body-2 cl-text-2 mb-0">{{ Str::limit(strip_tags($article->excerpt ?? ''), 125) }}</p>
                    </a>
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-20 mt-24 p-24 rounded-4 bg-white">
            <div>
                <h4 class="mb-6">Нужен расчёт под конкретный объект?</h4>
                <p class="text-body-1 cl-text-2 mb-0">Подберём мощность, гидравлическую схему и состав котельной.</p>
            </div>
            <a href="/montazh-teplovyh-nasosov#heat-pump-request" class="tf-btn animate-btn flex-shrink-0"
               data-analytics-event="heat_pump_lead_click">Рассчитать и подобрать</a>
        </div>
    </div>
</section>
