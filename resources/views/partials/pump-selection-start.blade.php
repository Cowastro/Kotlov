@php
    $replacementParameters = \Illuminate\Support\Arr::dot(request()->except('page', 'q'));
    $replacementQuery = $specificationSearch;
    [$pumpTitle, $pumpDescription, $pumpHelp] = match ($category->slug) {
        'skvajinnye-nasosy' => ['Подбор скважинного насоса', 'Укажите диаметр скважины, сравните напор, расход и питание. Проверьте допустимую глубину погружения.', 'Подготовьте внутренний диаметр скважины, динамический уровень воды, дебит и нужное давление в доме. Для замены — фото шильдика и длину кабеля.'],
        'poverhnostnyie' => ['Подбор поверхностного насоса', 'Сравните напор, расход, питание и максимальную высоту всасывания.', 'Нужны источник воды, высота от зеркала воды до насоса, длина и диаметр трубы, требуемый расход и давление. Отметьте, какая автоматика уже есть.'],
        'drenajnyie' => ['Подбор дренажного насоса', 'Начните с допустимого размера частиц и погружения, затем сравните напор, расход и питание.', 'Уточните состав и температуру воды, наличие песка и волокон, размер твёрдых частиц, глубину установки, высоту откачки и длину шланга.'],
        'nasosnyie-stantsii' => ['Подбор насосной станции', 'Выберите напор, расход, питание и высоту всасывания. Объём бака указан только для подтверждённой комплектации.', 'Подготовьте источник и уровень воды, высоту до станции, длину трубы, нужный расход и давление, место установки и доступное питание.'],
        'tsirkulyatsionnyie' => ['Подбор циркуляционного насоса', 'Сравните напор, питание и монтажную длину. Дополнительно проверьте размер присоединений в карточке.', 'Для замены нужны фото шильдика, расстояние между присоединениями и их размер. Для новой системы — расчётный расход и потери давления в контуре. Площадь дома сама по себе не определяет модель насоса.'],
        default => ['Подбор многоступенчатого насоса', 'Сузьте список по напору, расходу и питанию. Затем сравните модели по рабочим кривым производителя.', 'Нужны назначение системы, требуемый расход и давление, условия на входе и электропитание. Максимумы из каталога помогают сузить список, но не заменяют проверку рабочей точки.'],
    };
@endphp
@push('styles')
<style>
.pump-start{scroll-margin-top:100px}.pump-start__steps{display:flex;flex-wrap:wrap;gap:8px 20px;margin-bottom:12px;font-size:12px;color:var(--text-2)}.pump-start__steps strong{color:var(--text)}.pump-start__heading{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}.pump-start__heading h2{font-size:22px;margin:0}.pump-start__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.pump-start__card{min-width:0;padding:16px;border:1px solid var(--line);border-radius:16px;background:var(--white)}.pump-start__title{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:600;margin-bottom:8px}.pump-start__icon{display:grid;place-items:center;flex:0 0 36px;width:36px;height:36px;border-radius:10px;background:#eaf1ff;color:#2257ba}.pump-start__icon--green{background:#e7f5f2;color:#17615c}.pump-start__card p{font-size:13px;color:var(--text-2);line-height:1.5;margin-bottom:12px}.pump-start__search{display:flex;gap:8px;align-items:stretch}.pump-start__search input{min-width:0;width:100%;flex:1}.pump-start__search button{flex:none}.pump-start__help{font-size:12px;line-height:1.5;color:var(--text-2);margin-top:12px}.pump-start__help summary{cursor:pointer;color:var(--text);font-weight:600}.pump-start__help p{margin:8px 0 0}.pump-start__link{display:inline-flex;gap:8px;align-items:center;font-size:13px;font-weight:600;text-decoration:underline;text-underline-offset:4px}@media(max-width:767px){.pump-start__grid{grid-template-columns:1fr}.pump-start__card{padding:12px}.pump-start__heading h2{font-size:20px}.pump-start__search button{padding:10px 14px}.pump-start__steps{gap:6px 12px}}
</style>
@endpush
<section class="container pb-3 pump-start catalog-controls" id="pump-picker" aria-label="С чего начать подбор насоса">
    <div class="pump-start__steps" aria-label="Этапы подбора">
        <a href="{{ url('/vodosnabzhenie') }}#pump-tasks" class="link">1. Изменить задачу</a>
        <strong>2. Модель или параметры</strong>
        <a href="#catalog-products" class="link">3. Результаты · {{ $products->total() }}</a>
        @if (request()->query())<a href="{{ url()->current() }}#pump-picker" class="link">Начать заново</a>@endif
    </div>
    <div class="pump-start__heading">
        <h2>{{ $pumpTitle }}</h2>
        @include('partials.catalog-stock-toggle')
    </div>
    @if($category->slug === 'tsirkulyatsionnyie')<p class="mb-3"><a class="pump-start__link" href="{{ route('pump-calculator') }}">Калькулятор расхода и напора →</a><span class="text-caption-01 cl-text-2"> · для новой системы или замены насоса</span></p>@endif
    <div class="pump-start__grid">
        <div class="pump-start__card" id="pump-replacement">
            <h3 class="pump-start__title"><span class="pump-start__icon" aria-hidden="true"><i class="icon icon-ArrowClockwise"></i></span>Заменить насос</h3>
            <p>Введите марку и модель с шильдика старого насоса. Подойдёт также артикул или часть названия.</p>
            <form method="get" action="{{ url()->current() }}#catalog-products" role="search" aria-label="Поиск в разделе">
                @foreach ($replacementParameters as $key => $value)
                    @if (is_scalar($value))<input type="hidden" name="{{ preg_replace('/\.([^.]+)/', '[$1]', $key) }}" value="{{ $value }}">@endif
                @endforeach
                <label class="visually-hidden" for="pump-model">Марка или модель насоса</label>
                <div class="pump-start__search">
                    <input type="search" id="pump-model" class="form-control" name="q" value="{{ $replacementQuery }}" maxlength="100" placeholder="Марка, модель или артикул">
                    <button type="submit" class="tf-btn btn-primary small">Найти</button>
                </div>
            </form>
        </div>
        <div class="pump-start__card">
            <h3 class="pump-start__title"><span class="pump-start__icon pump-start__icon--green" aria-hidden="true"><i class="icon icon-filter"></i></span>Подобрать по параметрам</h3>
            <p>{{ $pumpDescription }}</p>
            <a href="#pump-parameters" class="pump-start__link">Указать параметры <span aria-hidden="true">↓</span></a>
            <details class="pump-start__help">
                <summary>Не знаете, что указать?</summary>
                <p>{{ $pumpHelp }}</p>
                <a href="{{ route('contacts') }}" class="link">Помощь с подбором →</a>
            </details>
        </div>
    </div>
</section>
