<nav class="container mt-3 mb-4" aria-label="Типы водонагревателей">
    @include('partials.catalog-type-cards', [
        'heading' => 'Тип водонагревателя',
        'entries' => collect($waterHeaterNavigation)->map(fn ($entry) => [...$entry, 'name' => $entry['short']]),
    ])

    @if ($waterHeaterRelated->isNotEmpty())
        <div class="d-flex flex-wrap gap-3 mt-3">
            @foreach ($waterHeaterRelated as $related)
                <a class="link text-caption-01" href="{{ url('/'.$related->slug) }}">
                    {{ $related->slug === \App\Support\Catalog\WaterHeaterCatalog::BUFFER ? 'Буферные ёмкости' : 'Запчасти и комплектующие' }}
                </a>
            @endforeach
        </div>
    @endif
</nav>
