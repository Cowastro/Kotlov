<section aria-labelledby="sakovich-kit-heading" class="mb-4">
    <h2 id="sakovich-kit-heading" class="h5 mb-3">Выберите комплект Sakovich</h2>
    <div class="row g-2">
        @foreach ($sakovichKits as $kit)
            @php($variant = \App\Services\Suppliers\SakovichPelletKits::OPTIONS[$kit->sku])
            <div class="col-12 col-md-4">
                <a class="d-flex flex-column h-100 border rounded p-3 link"
                   href="{{ url($kit->category->slug.'/'.$kit->slug) }}">
                    <strong>{{ $variant['model'] }}</strong>
                    <span class="text-caption-01 my-2">{{ $variant['parts'] }}</span>
                    <span class="mt-auto fw-semibold">{{ $kit->hasVerifiedPublicPrice() ? number_format($kit->price, 2, ',', ' ').' BYN' : 'Цену уточняйте' }}</span>
                    <span class="text-caption-01 text-decoration-underline">Выбрать комплект</span>
                </a>
            </div>
        @endforeach
    </div>
</section>
