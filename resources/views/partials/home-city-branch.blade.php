@push('styles')
<style>
    .city-store__location-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .city-store__branch, .city-store__map { padding: clamp(18px, 3vw, 32px); border: 1px solid #e5e5e5; border-radius: 16px; }
    .city-store__branch dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 8px 16px; margin: 16px 0; }
    .city-store__branch dd { margin: 0; overflow-wrap: anywhere; }
    .city-store__promo { margin-top: 16px; }
    .city-store__map iframe { display: block; width: 100%; height: 340px; margin-top: 16px; border: 0; border-radius: 12px; }
    .city-store__gallery-grid img { width: 100%; aspect-ratio: 4 / 3; border-radius: 12px; object-fit: cover; background: #f5f2ee; }
    @media (max-width: 767px) { .city-store__location-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@php($primaryMapLink = $primaryBranch->mapLink())
@php($primaryMapEmbed = $primaryBranch->mapEmbedLink())
    <section class="flat-spacing pt-0" id="city-branch">
        <div class="container">
            <div class="sect-heading type-2">
                <h2 class="s-title">Магазин KOTLOV в {{ $city->name_title }}</h2>
                <a class="tf-btn btn-white btn-stroke mt-3" href="/catalog">Весь каталог</a>
            </div>
            <div class="city-store__location-grid">
                <article class="city-store__branch">
                    <p class="text-caption-01 cl-text-2">Магазин и выдача заказов</p>
                    <h2>{{ $primaryBranch->name }}</h2>
                    <dl>
                        @if($primaryBranch->address)<dt>Адрес</dt><dd>{{ $primaryBranch->address }}</dd>@endif
                        @if($primaryBranch->working_hours)<dt>Режим работы</dt><dd>{{ $primaryBranch->working_hours }}</dd>@endif
                        @if($primaryBranch->email)<dt>E-mail</dt><dd><a href="mailto:{{ $primaryBranch->email }}">{{ $primaryBranch->email }}</a></dd>@endif
                        @if($primaryBranch->phones)<dt>Телефон</dt><dd>@foreach($primaryBranch->phoneLinks() as $phone)<a href="tel:{{ $phone['href'] }}">{{ $phone['display'] }}</a>@if(!$loop->last)<br>@endif @endforeach</dd>@endif
                    </dl>
                    @if($primaryBranch->storefront_promo)<div class="city-store__promo">{{ $primaryBranch->storefront_promo }}</div>@endif
                </article>
                <div class="city-store__map">
                    <h2>Как нас найти</h2><p>{{ $primaryBranch->address }}</p>
                    @if($primaryMapEmbed)
                        <iframe src="{{ $primaryMapEmbed }}" loading="lazy" title="{{ $primaryBranch->name }} на карте" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    @endif
                    @if($primaryMapLink)<a class="tf-btn btn-white btn-stroke mt-3" href="{{ $primaryMapLink }}" target="_blank" rel="noopener">Построить маршрут</a>@endif
                </div>
            </div>
        </div>
    </section>

    @php($branchPhotos = collect([$primaryBranch->hero_image])->merge($primaryBranch->gallery ?? [])->filter()->unique())
    @if($branchPhotos->isNotEmpty())
        <section class="flat-spacing pt-0"><div class="container"><div class="sect-heading type-2"><h2>Фотографии магазина</h2></div><div class="city-store__gallery-grid tf-grid-layout tf-col-2 md-col-3 gap-20">
            @foreach($branchPhotos as $image)
                <a href="{{ \Illuminate\Support\Facades\Storage::url($image) }}" target="_blank" rel="noopener" aria-label="Открыть фото {{ $loop->iteration }} магазина">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($image) }}" alt="{{ $primaryBranch->name }} — фото {{ $loop->iteration }}" loading="lazy">
                </a>
            @endforeach
        </div></div></section>
    @endif
