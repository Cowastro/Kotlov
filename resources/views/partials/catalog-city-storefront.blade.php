@if(($isCityStorefront ?? false) && $cityStorefrontBranch)
    <section class="city-catalog-branch" aria-label="Филиал KOTLOV {{ $currentCity->name_in }}">
        <div class="container">
            <div class="city-catalog-branch__inner">
                <div>
                    <span>Локальная витрина KOTLOV</span>
                    <h2>{{ $cityStorefrontBranch->storefront_title ?: $cityStorefrontBranch->name }}</h2>
                    <p>{{ $cityStorefrontBranch->storefront_promo ?: 'Закажите товар онлайн и получите его через филиал в вашем городе. Наличие и срок получения подтвердит менеджер.' }}</p>
                </div>
                <div class="city-catalog-branch__contacts">
                    @if($cityStorefrontBranch->address)<strong>{{ $cityStorefrontBranch->address }}</strong>@endif
                    @if($cityStorefrontBranch->working_hours)<small>{{ $cityStorefrontBranch->working_hours }}</small>@endif
                    <div>
                        @if($cityStorefrontBranch->mapLink())<a href="{{ $cityStorefrontBranch->mapLink() }}" target="_blank" rel="noopener">Построить маршрут</a>@endif
                        <a href="/">О магазине</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @once
        @push('styles')
            <style>
                .city-catalog-branch{margin-top:22px}.city-catalog-branch__inner{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);gap:32px;align-items:center;padding:26px 30px;border-radius:20px;background:#151b22;color:#fff}.city-catalog-branch span{display:block;margin-bottom:6px;color:#ff6b70;font-size:11px;font-weight:750;letter-spacing:.07em;text-transform:uppercase}.city-catalog-branch h2{margin:0 0 7px;color:#fff;font-size:25px}.city-catalog-branch p{max-width:800px;margin:0;color:rgba(255,255,255,.72);font-size:14px;line-height:1.55}.city-catalog-branch__contacts{display:grid;gap:6px;padding-left:24px;border-left:1px solid rgba(255,255,255,.16)}.city-catalog-branch__contacts small{color:rgba(255,255,255,.64)}.city-catalog-branch__contacts div{display:flex;flex-wrap:wrap;gap:9px;margin-top:7px}.city-catalog-branch__contacts a{padding:8px 12px;border:1px solid rgba(255,255,255,.28);border-radius:999px;color:#fff;font-size:12px;font-weight:650}@media(max-width:767px){.city-catalog-branch{margin-top:15px}.city-catalog-branch__inner{grid-template-columns:1fr;gap:18px;padding:21px 18px;border-radius:16px}.city-catalog-branch h2{font-size:21px}.city-catalog-branch__contacts{padding:16px 0 0;border-top:1px solid rgba(255,255,255,.16);border-left:0}}
            </style>
        @endpush
    @endonce
@endif
