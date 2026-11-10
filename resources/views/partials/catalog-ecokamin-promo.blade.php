@if (($ecokaminPromoHits ?? collect())->count() >= 3)
    <section class="catalog-ecokamin-promo">
        <div class="container">
            @include('partials.ecokamin-promo-slide')
        </div>
    </section>
    <style>.catalog-ecokamin-promo{padding:28px 0 8px}</style>
@endif
