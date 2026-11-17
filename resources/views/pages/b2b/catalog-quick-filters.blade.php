<div class="b2b-quick-flags" aria-label="Быстрые фильтры">
    @foreach(\App\Services\B2bCatalogFilters::LABELS as $value => $label)
        @continue($value === 'selected' && !$staffWorkspace)
        @continue($value === 'priced' && $mode === 'orderable')
        <label class="b2b-quick-flag"><input type="checkbox" name="flags[]" value="{{ $value }}" @checked(in_array($value, $flags, true)) data-auto-submit><span>{{ $label }}</span></label>
    @endforeach
    <button type="button" class="b2b-density" data-b2b-density aria-pressed="false">Компактные строки</button>
</div>
<details class="b2b-extra-filters" @if($priceMin !== null || $priceMax !== null || $supplierId > 0) open @endif>
    <summary>Цена{{ $showProcurement ? ' и поставщик' : '' }}@if($priceMin !== null || $priceMax !== null || $supplierId > 0) · применено@endif</summary>
    <div><fieldset><legend>{{ $mode === 'reference' ? 'Цена сайта, BYN' : 'Ваша B2B-цена, BYN' }}</legend><label><span class="visually-hidden">Цена от</span><input type="number" name="price_min" min="0" step="0.01" value="{{ $priceMin }}" placeholder="От" inputmode="decimal"></label><label><span class="visually-hidden">Цена до</span><input type="number" name="price_max" min="0" step="0.01" value="{{ $priceMax }}" placeholder="До" inputmode="decimal"></label></fieldset>
    @if($showProcurement && $workspaceSuppliers->isNotEmpty())<label>Поставщик<select name="workspace_supplier" data-auto-submit><option value="">Все поставщики</option>@foreach($workspaceSuppliers as $supplier)<option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>@endif
    <button type="submit" class="b2b-density">Применить</button>
    @if($flags || $priceMin !== null || $priceMax !== null || $supplierId > 0)<a class="b2b-reset-filters" href="{{ $resetUrl }}">Сбросить фильтры</a>@endif
    </div>
</details>
