<section class="container mt-3 mb-3">
    <nav class="d-flex flex-wrap justify-content-center gap-2" aria-label="Разделы электрокаминов">
        <a href="{{ url('/elektrokamini') }}"
           class="tf-btn s-small {{ $category->slug === 'elektrokamini' && $fireplaceType === null ? 'btn-primary' : 'btn-stroke' }}"
           @if ($category->slug === 'elektrokamini' && $fireplaceType === null) aria-current="page" @endif>
            Все электрокамины
        </a>
        @foreach (\App\Support\Catalog\ElectricFireplaceNavigation::ENTRIES as $key => $entry)
            <a href="{{ url('/'.$entry['category']).'?fireplace_type='.$key }}"
               class="tf-btn s-small {{ $fireplaceType === $key ? 'btn-primary' : 'btn-stroke' }}"
               @if ($fireplaceType === $key) aria-current="page" @endif>
                {{ $entry['label'] }}
            </a>
        @endforeach
    </nav>
</section>
