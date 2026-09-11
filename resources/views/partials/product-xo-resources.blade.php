@once
<style>
    .xo-product-resources { background:#f5f6f3; }
    .xo-product-resource { display:block; height:100%; overflow:hidden; border:1px solid #e1e3df; border-radius:20px; background:#fff; color:inherit!important; transition:.25s ease; }
    .xo-product-resource:hover { transform:translateY(-4px); box-shadow:0 18px 38px rgba(28,31,27,.08); }
    .xo-product-resource__image { aspect-ratio:16/8; overflow:hidden; background:#17191c; }
    .xo-product-resource__image img { width:100%; height:100%; object-fit:cover; transition:transform .4s ease; }
    .xo-product-resource:hover img { transform:scale(1.025); }
    .xo-product-resource__body { padding:24px; }
    .xo-product-resource__meta { margin-bottom:10px; color:#ef4b4b; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .xo-product-resource__body p { color:#72777e; }
    .xo-product-resources__cta { display:flex; align-items:center; justify-content:space-between; gap:25px; margin-top:24px; padding:28px 32px; border-radius:20px; background:#181b1e; color:#fff; }
    .xo-product-resources__cta h4,.xo-product-resources__cta p { color:#fff; }
    @media(max-width:767px){.xo-product-resources__cta{align-items:stretch;flex-direction:column;padding:24px 21px}.xo-product-resources__cta .tf-btn{width:100%}}
</style>
@endonce

<section class="flat-spacing xo-product-resources" aria-labelledby="xo-product-help">
    <div class="container">
        <div class="sect-heading type-2 text-center mb-36"><p class="text-caption-01 text-primary fw-semibold mb-8">ПОДБОР И ПРИМЕНЕНИЕ</p><h2 class="s-title" id="xo-product-help">Перед заказом проверьте котельную</h2><p class="s-desc text-body-1 cl-text-2">Условия акции, встроенный Wi‑Fi, совместимость с котлом и технический разбор модели 100 кВт.</p></div>
        <div class="row g-20"><div class="col-md-6"><a href="/akcii/kotlov-xo-ceramic-pro" class="xo-product-resource"><div class="xo-product-resource__image"><img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-ceramic-pro-sale-cover-v2.webp') }}" alt="Акция на KOTLOV XO Ceramic PRO 100 кВт"></div><div class="xo-product-resource__body"><p class="xo-product-resource__meta">Акция −10%</p><h4 class="mb-10">Специальная цена на модель 100 кВт</h4><p class="mb-0">Условия предложения, экономия и форма инженерного расчёта.</p></div></a></div><div class="col-md-6"><a href="/blog/pelletnaya-gorelka-100-kvt-kotlov-xo-ceramic-pro" class="xo-product-resource"><div class="xo-product-resource__image"><img loading="lazy" src="{{ $product->imageUrl(1) }}" alt="Технический обзор KOTLOV XO Ceramic PRO 100 кВт"></div><div class="xo-product-resource__body"><p class="xo-product-resource__meta">Технический разбор</p><h4 class="mb-10">Как автоматизировать котельную на 100 кВт</h4><p class="mb-0">Горение, очистка, расход пеллет, автоматика и требования к монтажу.</p></div></a></div></div>
        <div class="xo-product-resources__cta"><div><p class="text-caption-01 mb-7" style="color:#ff716a">ИНЖЕНЕРНЫЙ ПОДБОР KOTLOV</p><h4 class="mb-7">Есть существующий котёл?</h4><p class="mb-0" style="opacity:.67">Проверим топку, дверцу, дымоход, шнек, бункер и автоматику.</p></div><a href="/akcii/kotlov-xo-ceramic-pro#xo-request" class="tf-btn btn-white">Получить расчёт</a></div>
    </div>
</section>
