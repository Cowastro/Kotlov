<section aria-labelledby="sakovich-kit-heading" class="mb-4">
    <h2 id="sakovich-kit-heading" class="h5 mb-3">Выберите комплект Sakovich</h2>
    <div class="row g-2">
        @foreach ($sakovichKits as $kit)
            @php($variant = \App\Services\Suppliers\SakovichPelletKits::OPTIONS[$kit->sku])
            <div class="col-12 col-md-6 col-xl-4">
                <a class="d-flex flex-column h-100 border rounded p-3 link"
                   href="{{ url($kit->category->slug.'/'.$kit->slug) }}">
                    <strong>{{ $variant['model'] }} {{ $variant['power_kw'] }} кВт</strong>
                    <dl class="my-2 text-caption-01">
                        <div class="d-flex justify-content-between gap-2">
                            <dt>Мощность котла</dt>
                            <dd class="mb-0 text-end">{{ $variant['power_kw'] }} кВт</dd>
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <dt>Горелка</dt>
                            <dd class="mb-0 text-end">{{ $variant['burner'] }}</dd>
                        </div>
                        <div class="d-flex justify-content-between gap-2">
                            <dt>Бункер</dt>
                            <dd class="mb-0 text-end">{{ $variant['hopper_l'] ? $variant['hopper_l'].' л' : 'Объём уточняется' }}</dd>
                        </div>
                        @isset($variant['steel_mm'])
                            <div class="d-flex justify-content-between gap-2">
                                <dt>Толщина стали</dt>
                                <dd class="mb-0 text-end">{{ $variant['steel_mm'] }} мм</dd>
                            </div>
                        @endisset
                    </dl>
                    <span class="mt-auto fw-semibold">{{ $kit->hasVerifiedPublicPrice() ? number_format($kit->price, 2, ',', ' ').' BYN' : 'Цену уточняйте' }}</span>
                    <span class="text-caption-01 text-decoration-underline">Выбрать комплект</span>
                </a>
            </div>
        @endforeach
    </div>
</section>
