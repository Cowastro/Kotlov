<form method="GET" action="{{ route('b2b.catalog') }}" class="b2b-command-bar" id="b2b-filter-form" autocomplete="off">
    <input type="hidden" name="mode" value="{{ $mode }}">
    @if($staffWorkspace)<input type="hidden" name="workspace" value="1"><input type="hidden" name="selection_id" data-workspace-selection-id value="{{ $workspaceState['id'] }}">@endif
    @if ($categoryId > 0)<input type="hidden" name="category" value="{{ $categoryId }}">@endif
    @if ($preview)<input type="hidden" name="b2b-preview" value="1">@endif

    <div class="b2b-search" data-suggest-url="{{ route('b2b.catalog.suggest', array_filter(['mode' => $mode, 'b2b-preview' => $preview ? 1 : null, 'workspace' => $staffWorkspace ? 1 : null, 'selection_id' => $staffWorkspace ? $workspaceState['id'] : null])) }}">
        <i class="icon icon-MagnifyingGlass" aria-hidden="true"></i>
        <input id="b2b-search-input" type="search" name="q" value="{{ $query }}"
            placeholder="Название или артикул" aria-label="Поиск по названию, артикулу, бренду или категории"
            aria-autocomplete="list" aria-controls="b2b-search-suggest" aria-expanded="false">
        @if ($query !== '')
            <a href="{{ request()->fullUrlWithQuery(['q' => null, 'category' => null, 'page' => null]) }}" class="b2b-search__clear" aria-label="Очистить поиск">×</a>
        @else
            <kbd>/</kbd>
        @endif
        <button type="submit">Найти</button>
        <div class="b2b-search-suggest" id="b2b-search-suggest" role="listbox" hidden></div>
    </div>

    <div class="b2b-command-bar__filters">
        @unless($staffWorkspace)<div class="b2b-mode-switch" aria-label="Режим каталога">
            <a href="{{ request()->fullUrlWithQuery(['mode' => 'orderable', 'page' => null]) }}" class="{{ $mode === 'orderable' ? 'active' : '' }}">В наличии и под заказ</a>
            <a href="{{ request()->fullUrlWithQuery(['mode' => 'reference', 'page' => null, 'source' => null]) }}" class="{{ $mode === 'reference' ? 'active' : '' }}">Справочный каталог</a>
        </div>@else<p class="b2b-workspace-price-note">{{ $showProcurement ? 'Внутренний подбор · входные цены и маржа доступны администраторам и менеджерам CRM' : 'Все товары · цены сайта · наличие и условия поставки уточняются' }}</p>@endunless
        <div class="b2b-filter-selects">
            <label><span>Бренд</span><select name="brand" data-auto-submit>
                <option value="">Все бренды</option>
                @foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected($brandId === (int) $brand->id)>{{ $brand->name }}</option>@endforeach
            </select></label>
            @if ($mode === 'orderable' && $sourceGroups->count() > 1)
                <label><span>Поставщик</span><select name="source" data-auto-submit>
                    <option value="">Все поставщики</option>
                    @foreach ($sourceGroups as $sourceGroup)<option value="{{ $sourceGroup['key'] }}" @selected($sourceKey === $sourceGroup['key'])>{{ $sourceGroup['label'] }}</option>@endforeach
                </select></label>
            @endif
            @if ($mode === 'orderable' || $staffWorkspace)
                <label><span>Сортировка</span><select name="sort" data-auto-submit>
                    <option value="name" @selected($sort === 'name')>По названию</option>
                    @unless($staffWorkspace)<option value="stock" @selected($sort === 'stock')>Сначала в наличии</option>@endunless
                    <option value="price_asc" @selected($sort === 'price_asc')>Сначала дешевле</option>
                    <option value="price_desc" @selected($sort === 'price_desc')>Сначала дороже</option>
                </select></label>
            @endif
            <label><span>На странице</span><select name="per_page" data-auto-submit>@foreach([30,60,100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach</select></label>
            @if ($query !== '' || $categoryId > 0 || $brandId > 0 || $sourceKey !== '' || $sort !== 'name')<a href="{{ $resetUrl }}" class="b2b-reset-filters">Сбросить</a>@endif
        </div>
    </div>
    @include('pages.b2b.catalog-quick-filters')
</form>
