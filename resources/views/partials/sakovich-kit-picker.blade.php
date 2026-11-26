@once
@push('styles')
<style>
    .sk-picker { margin-bottom: 32px; }
    .sk-picker h2 { margin-bottom: 16px; font-size: clamp(22px, 3vw, 32px); line-height: 1.15; }
    .sk-series { display: grid; grid-template-columns: minmax(150px, 22%) minmax(0, 1fr); border: 1px solid #d9dfdc; border-radius: 16px; overflow: hidden; margin-bottom: 14px; background: #fff; }
    .sk-series__cover { display: flex; flex-direction: column; justify-content: space-between; padding: 18px; gap: 10px; background: #edf3ed; }
    .sk-series__cover h3 { font-size: 21px; line-height: 1.15; color: #203e32; }
    .sk-series__cover img { width: 100%; height: 140px; object-fit: contain; mix-blend-mode: multiply; }
    .sk-series__variants { padding: 4px 14px; }
    .sk-variant { display: grid; grid-template-columns: minmax(76px, .75fr) minmax(115px, 1.45fr) minmax(58px, .65fr) minmax(95px, 1fr) 22px; align-items: center; gap: 10px; padding: 12px 0; color: #182820; font-size: 14px; line-height: 1.3; }
    .sk-variant + .sk-variant { border-top: 1px solid #e2e8e3; }
    .sk-variant:hover, .sk-variant:focus-visible { background: #f5f8f5; color: #175737; }
    .sk-variant:focus-visible { outline: 2px solid #286646; outline-offset: -2px; }
    .sk-variant small { display: block; font-size: 12px; color: #58675e; line-height: 1.4; }
    .sk-variant__power, .sk-variant__price { font-weight: 700; }
    .sk-variant__power { font-size: 18px; }
    .sk-variant__price { text-align: right; }
    .sk-variant__arrow { color: #276447; font-size: 18px; }
    @media (max-width: 1199px) {
        .sk-series { grid-template-columns: 1fr; }
        .sk-series__cover { flex-direction: row; align-items: center; padding: 10px 16px; }
        .sk-series__cover img { width: 88px; height: 76px; }
    }
    @media (max-width: 767px) {
        .sk-variant { grid-template-columns: minmax(0, 1fr) minmax(95px, auto) 22px; gap: 5px 10px; }
        .sk-variant__power { grid-area: 1 / 1; }
        .sk-variant__price { grid-area: 1 / 2; }
        .sk-variant__arrow { grid-area: 1 / 3; }
        .sk-variant__burner { grid-area: 2 / 1; }
        .sk-variant__hopper { grid-area: 2 / 2 / 3 / 4; }
    }
</style>
@endpush
@endonce

@php($options = \App\Services\Suppliers\SakovichPelletKits::OPTIONS)
<section aria-labelledby="sakovich-kit-heading" class="sk-picker">
    <h2 id="sakovich-kit-heading">Комплекты Sakovich</h2>
    @foreach ($sakovichKits->groupBy(fn ($kit) => $options[$kit->sku]['model']) as $series => $variants)
        <div class="sk-series">
            <div class="sk-series__cover">
                <h3>{{ $series }}</h3>
                <img src="{{ $variants->first()->image_url }}" alt="" loading="lazy" width="180" height="140">
            </div>
            <div class="sk-series__variants">
                @foreach ($variants->sortBy(fn ($kit) => $options[$kit->sku]['power_kw']) as $kit)
                    @php($variant = $options[$kit->sku])
                    <a class="sk-variant" href="{{ url($kit->category->slug.'/'.$kit->slug) }}">
                        <span class="sk-variant__power"><small>Котёл</small>{{ $variant['power_kw'] }} кВт @isset($variant['steel_mm'])<small>Сталь {{ $variant['steel_mm'] }} мм</small>@endisset</span>
                        <span class="sk-variant__burner"><small>Горелка</small>{{ $variant['burner'] }}</span>
                        <span class="sk-variant__hopper"><small>Бункер</small>{{ $variant['hopper_l'] ? $variant['hopper_l'].' л' : 'Объём уточняется' }}</span>
                        <span class="sk-variant__price">{{ $kit->hasVerifiedPublicPrice() ? number_format($kit->price, 2, ',', ' ').' BYN' : 'Цену уточняйте' }}</span>
                        <span class="sk-variant__arrow" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</section>
