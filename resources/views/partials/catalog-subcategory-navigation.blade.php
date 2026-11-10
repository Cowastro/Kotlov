@php
    $groups = $catalogNavigationGroups;
    $linkFilters = array_filter([
        'brand' => request('brand') ? (int) request('brand') : null,
        'in_stock' => $inStockOnly ? '1' : null,
    ]);
    $linkSuffix = $linkFilters ? '?'.http_build_query($linkFilters) : '';
    $entriesFor = fn ($children, string $groupText = '') => $children->mapWithKeys(fn ($subcategory) => [
        $subcategory->slug => [
            'name' => $subcategory->name,
            'count' => $subcategory->products_count,
            'icon' => \App\Support\CategoryIconResolver::resolve($subcategory->slug, $subcategory->name),
            'url' => '/'.$subcategory->slug.$linkSuffix,
            'search' => $groupText !== '' ? $groupText.' '.$subcategory->name : null,
        ],
    ]);
@endphp

@if ($groups->isNotEmpty())
    @push('styles')
        <style>
            .catalog-subcategory-nav__search {
                max-width: 380px;
                margin-bottom: 12px;
            }

            .catalog-subcategory-group {
                margin-bottom: 10px;
                padding: 0 12px;
                border: 1px solid var(--line);
                border-radius: 16px;
            }

            .catalog-subcategory-group__heading {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px 0;
                min-height: 48px;
                font-size: 15px;
                font-weight: 600;
                list-style: none;
                cursor: pointer;
            }

            .catalog-subcategory-group__heading::-webkit-details-marker { display: none; }
            .catalog-subcategory-group__heading .icon-CaretDown { margin-left: auto; font-size: 13px; }
            .catalog-subcategory-group[open] .icon-CaretDown { transform: rotate(180deg); }
            .catalog-subcategory-group .catalog-type-nav__grid { margin-bottom: 12px; }

            @media (max-width: 575px) {
                .catalog-subcategory-group { padding: 0 8px; }
            }
        </style>
    @endpush

    <nav class="container mt-3 mb-4" data-accessories-nav aria-labelledby="accessories-nav-title">
        <div class="catalog-type-nav__header">
            <h2 id="accessories-nav-title">Выберите раздел</h2>
            @if ($showSubcategoryStockToggle)
                @include('partials.catalog-stock-toggle')
            @endif
        </div>
        <div class="catalog-subcategory-nav__search">
            <label for="accessories-nav-search" class="visually-hidden">Поиск подраздела</label>
            <input class="form-control" type="search" id="accessories-nav-search" placeholder="Найти подраздел" autocomplete="off">
        </div>
        @foreach ($groups as $group)
            <details class="catalog-subcategory-group" data-accessories-group open>
                <summary class="catalog-subcategory-group__heading">
                    <i class="icon {{ $group['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $group['title'] }}</span>
                    <i class="icon icon-CaretDown" aria-hidden="true"></i>
                </summary>
                @include('partials.catalog-type-cards', [
                    'entries' => $entriesFor($group['items'], $group['title'].' '.$group['description']),
                ])
            </details>
        @endforeach
        <p class="text-caption-01 cl-text-2" data-accessories-empty aria-live="polite" hidden>
            Подходящий раздел не найден.
        </p>
    </nav>
@else
    <nav class="container mt-3 mb-4" aria-label="Подразделы каталога">
        @include('partials.catalog-type-cards', [
            'heading' => 'Выберите раздел',
            'showStockToggle' => $showSubcategoryStockToggle,
            'entries' => $entriesFor($subcategories),
        ])
    </nav>
@endif
