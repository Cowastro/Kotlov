<nav class="container mt-3 mb-4" aria-label="Типы котлов">
    @include('partials.catalog-type-cards', [
        'heading' => 'Выберите тип котла',
        'entries' => $boilerCategories->mapWithKeys(fn ($boiler) => [
            $boiler->slug => [...$boilerTypes->get($boiler->slug), 'count' => $boiler->products_count],
        ]),
        'selectedTypeSlug' => $selectedSubcategory?->slug,
    ])

    @if ($selectedSubcategory !== null)
        <a href="{{ url('/kotly') }}#catalog-products" class="link fw-medium d-inline-block mt-3">Все котлы</a>
    @endif
</nav>
