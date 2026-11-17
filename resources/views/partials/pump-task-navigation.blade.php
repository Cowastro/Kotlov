@php
    $tasks = [
        'tsirkulyatsionnyie' => ['Для отопления', 'Циркуляция в системе отопления', 'icon-ArrowClockwise'],
        'mnogostupenchatye-nasosy' => ['Повысить давление', 'Многоступенчатые · подбор по напору и расходу', 'icon-Wind'],
        'skvajinnye-nasosy' => ['Вода из скважины', 'Скважинные погружные насосы', 'icon-Wind'],
        'poverhnostnyie' => ['Водоснабжение и полив', 'Поверхностные насосы', 'icon-HouseLine'],
        'nasosnyie-stantsii' => ['Готовая насосная станция', 'Насос с автоматикой для водоснабжения', 'icon-Devices'],
        'drenajnyie' => ['Откачка воды', 'Дренажные и канализационные насосы', 'icon-filter'],
        'promyshlennye-nasosy' => ['Промышленные системы', 'По расходу, напору и исполнению', 'icon-GitDiff'],
        'konsolnye-i-monoblochnye-nasosy' => ['Консольные и моноблочные', 'Водоснабжение и технологические линии', 'icon-GearSix'],
        'in-lain-nasosy' => ['Ин-лайн', 'Отопление и холодоснабжение', 'icon-ArrowClockwise'],
        'kanalizatsionnye-nasosy-sukhoy-ustanovki' => ['Канализационные сухой установки', 'Насосные станции и водоотведение', 'icon-filter'],
    ];
    $params = request()->only('q', 'brand', 'in_stock', 'pump_head', 'pump_flow', 'pump_head_to', 'pump_flow_to');
    if (is_string(request('pump.voltage'))) $params['pump']['voltage'] = request('pump.voltage');
    $suffix = $params ? '?'.http_build_query($params) : '';
    $entries = [];
    foreach ($tasks as $slug => [$name, $description, $icon]) {
        $child = $pumpTaskCategories->firstWhere('slug', $slug);
        if (! $child) continue;
        $entries[$child->slug] = ['name' => $name, 'description' => $description, 'icon' => $icon,
            'count' => $child->products_count, 'url' => url('/'.$child->slug).$suffix.(in_array($slug, \App\Support\Catalog\PumpCatalog::PICKER_CATEGORIES, true) ? '#pump-picker' : '')];
    }
@endphp
<nav class="container mt-3 mb-4" id="pump-tasks" aria-label="Насосы по задаче">
    <p class="text-caption-01 cl-text-2 mb-2">1. Выберите задачу → 2. Укажите модель или параметры → 3. Сравните насосы</p>
    @include('partials.catalog-type-cards', ['heading' => 'Для какой задачи нужен насос?', 'entries' => $entries, 'showStockToggle' => true])
    <div class="d-flex flex-wrap gap-3 mt-3">
        <a href="{{ route('pump-calculator') }}" class="link text-caption-01">Калькулятор циркуляционного насоса →</a>
        @foreach ($subcategories as $child)
            @continue(isset($tasks[$child->slug]))
            <a href="{{ url('/'.$child->slug) }}" class="link text-caption-01">{{ $child->name }} →</a>
        @endforeach
        @if ($category->slug !== 'vodosnabzhenie')<a href="{{ url('/vodosnabzhenie') }}" class="link text-caption-01">← Другие задачи и типы насосов</a>@endif
    </div>
</nav>
