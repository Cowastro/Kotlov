@php
    $tasks = [
        'tsirkulyatsionnyie' => ['Для отопления', 'Циркуляция в системе отопления', 'icon-ArrowClockwise'],
        'skvajinnye-nasosy' => ['Вода из скважины', 'Скважинные погружные насосы', 'icon-Wind'],
        'poverhnostnyie' => ['Водоснабжение и полив', 'Поверхностные насосы', 'icon-HouseLine'],
        'nasosnyie-stantsii' => ['Готовая насосная станция', 'Насос с автоматикой для водоснабжения', 'icon-Devices'],
        'drenajnyie' => ['Откачка воды', 'Дренажные и канализационные насосы', 'icon-filter'],
        'promyshlennye-nasosy' => ['Промышленные системы', 'По расходу, напору и исполнению', 'icon-GitDiff'],
        'mnogostupenchatye-nasosy' => ['Многоступенчатые', 'Повышение давления и водоснабжение', 'icon-Wind'],
        'konsolnye-i-monoblochnye-nasosy' => ['Консольные и моноблочные', 'Водоснабжение и технологические линии', 'icon-GearSix'],
        'in-lain-nasosy' => ['Ин-лайн', 'Отопление и холодоснабжение', 'icon-ArrowClockwise'],
        'kanalizatsionnye-nasosy-sukhoy-ustanovki' => ['Канализационные сухой установки', 'Насосные станции и водоотведение', 'icon-filter'],
    ];
    $params = request()->only('q', 'brand', 'in_stock', 'pump_head', 'pump_flow', 'pump_head_to', 'pump_flow_to');
    if (is_string(request('pump.voltage'))) $params['pump']['voltage'] = request('pump.voltage');
    $suffix = $params ? '?'.http_build_query($params) : '';
    $entries = [];
    foreach ($tasks as $slug => [$name, $description, $icon]) {
        $child = $subcategories->firstWhere('slug', $slug);
        if (! $child) continue;
        $entries[$child->slug] = ['name' => $name, 'description' => $description, 'icon' => $icon,
            'count' => $child->products_count, 'url' => url('/'.$child->slug).$suffix];
    }
@endphp
<nav class="container mt-3 mb-4" id="pump-tasks" aria-label="Насосы по задаче">
    @include('partials.catalog-type-cards', ['heading' => 'Для какой задачи нужен насос?', 'entries' => $entries, 'showStockToggle' => true])
    <div class="d-flex flex-wrap gap-3 mt-3">
        @foreach ($subcategories as $child)
            @continue(isset($tasks[$child->slug]))
            <a href="{{ url('/'.$child->slug) }}" class="link text-caption-01">{{ $child->name }} →</a>
        @endforeach
        @if ($category->slug !== 'vodosnabzhenie')<a href="{{ url('/vodosnabzhenie') }}" class="link text-caption-01">← Другие задачи и типы насосов</a>@endif
    </div>
</nav>
