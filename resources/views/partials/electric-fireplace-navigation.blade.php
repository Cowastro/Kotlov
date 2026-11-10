@php
    $types = \App\Support\Catalog\ElectricFireplaceNavigation::ENTRIES;
    $icons = ['insert' => 'flame', 'kit' => 'icon-Package', 'portal' => 'icon-Layout'];
    $baseUrl = url('/elektrokamini');
    $entries = [
        'all' => [
            'name' => 'Все электрокамины',
            'icon' => 'icon-SquaresFour',
            'url' => $baseUrl.($inStockOnly ? '?in_stock=1' : ''),
        ],
    ];
    foreach ($types as $key => $type) {
        $entries[$key] = [
            'name' => $type['label'],
            'icon' => $icons[$key],
            'url' => url('/'.$type['category']).'?fireplace_type='.$key.($inStockOnly ? '&in_stock=1' : ''),
        ];
    }
@endphp

<nav class="container mt-3 mb-4" aria-label="Разделы электрокаминов">
    @include('partials.catalog-type-cards', [
        'heading' => 'Выберите раздел',
        'entries' => $entries,
        'selectedTypeSlug' => $fireplaceType ?? ($category->slug === 'elektrokamini' ? 'all' : null),
    ])
</nav>
