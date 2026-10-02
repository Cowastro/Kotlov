@extends('layouts.amerce')

@section('title', 'Монтажники отопления в Беларуси — каталог специалистов KOTLOV')
@section('description', 'Выберите проверенного монтажника котлов, тепловых насосов, дымоходов, каминов и систем отопления в своём регионе.')

@push('styles')
<style>
    .installer-catalog { --installer-accent:#f28c00; --installer-ink:#161616; }
    .installer-catalog__hero { padding:54px 0 42px; background:linear-gradient(135deg,#f7f7f7 0%,#fff 55%,#fff6e9 100%); border-bottom:1px solid #ececec; }
    .installer-catalog__eyebrow { display:inline-flex;align-items:center;gap:8px;margin-bottom:14px;color:#b76500;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase; }
    .installer-catalog__eyebrow::before { content:"";width:28px;height:2px;background:var(--installer-accent); }
    .installer-catalog__stats { display:flex;flex-wrap:wrap;gap:10px;margin-top:26px; }
    .installer-catalog__stat { min-width:138px;padding:12px 16px;border:1px solid #e7e7e7;border-radius:12px;background:rgba(255,255,255,.82); }
    .installer-catalog__stat strong { display:block;font-size:20px;line-height:1.1;color:var(--installer-ink); }
    .installer-catalog__stat span { color:#707070;font-size:12px; }
    .installer-filter { padding:20px;border:1px solid #e6e6e6;border-radius:16px;background:#fff;position:sticky;top:96px; }
    .installer-filter__summary { display:none;align-items:center;justify-content:space-between;list-style:none;font-weight:700;cursor:pointer; }
    .installer-filter__toggle { display:none; }
    .installer-filter__count { display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;padding:0 7px;border-radius:999px;background:#111;color:#fff;font-size:12px; }
    .installer-filter__body { display:grid;gap:16px; }
    .installer-filter label { display:block;margin-bottom:7px;color:#333;font-size:13px;font-weight:650; }
    .installer-filter .form-select,.installer-filter .form-control { min-height:46px;border-radius:10px;border-color:#dedede; }
    .installer-filter__check { display:flex!important;align-items:center;gap:9px;margin:0!important;font-weight:500!important;cursor:pointer; }
    .installer-filter__check input { width:18px;height:18px;accent-color:#111; }
    .installer-result-head { display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px; }
    .installer-card { display:flex;flex-direction:column;height:100%;overflow:hidden;border:1px solid #e7e7e7;border-radius:18px;background:#fff;transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease; }
    .installer-card:hover { transform:translateY(-3px);border-color:#d7d7d7;box-shadow:0 16px 38px rgba(0,0,0,.08); }
    .installer-card__media { position:relative;height:224px;background:linear-gradient(135deg,#efefef,#fafafa);overflow:hidden; }
    .installer-card__media img { width:100%;height:100%;object-fit:cover; }
    .installer-card__media.is-logo { display:flex;align-items:center;justify-content:center;background:linear-gradient(145deg,#f3f3f3,#fbfbfb); }
    .installer-card__media.is-logo img { object-fit:contain;padding:24px 32px; }
    .installer-card__initials { display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:54px;font-weight:750;color:#333;background:linear-gradient(145deg,#f0f0f0,#fff5e6); }
    .installer-card__verified { position:absolute;top:14px;left:14px;padding:7px 10px;border-radius:999px;background:#ecf8ef;color:#16713a;font-size:11px;font-weight:750;box-shadow:0 4px 14px rgba(0,0,0,.07); }
    .installer-card__body { display:flex;flex-direction:column;flex:1;padding:20px; }
    .installer-card__place { color:#6c6c6c;font-size:13px; }
    .installer-card__rating { display:inline-flex;align-items:center;gap:5px;color:#8c5700;font-size:13px;font-weight:700; }
    .installer-card__description { min-height:48px;color:#616161;font-size:14px;line-height:1.55; }
    .installer-card__chips { display:flex;flex-wrap:wrap;gap:6px;margin:14px 0 18px; }
    .installer-card__chip { padding:6px 9px;border-radius:999px;background:#f2f2f2;color:#363636;font-size:11px;font-weight:650; }
    .installer-card__facts { display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:14px 0;border-top:1px solid #ededed;border-bottom:1px solid #ededed; }
    .installer-card__fact { text-align:center; }
    .installer-card__fact strong { display:block;color:#171717;font-size:15px; }
    .installer-card__fact span { display:block;color:#818181;font-size:10px;line-height:1.25; }
    .installer-card__actions { display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:auto;padding-top:18px; }
    .installer-card__actions .tf-btn { min-height:44px;padding:10px 12px;font-size:13px; }
    .installer-empty { padding:58px 24px;border:1px dashed #d7d7d7;border-radius:18px;text-align:center;background:#fafafa; }
    .installer-pagination { display:flex;align-items:center;justify-content:center;gap:6px;margin-top:32px; }
    .installer-pagination a,.installer-pagination span { display:flex;align-items:center;justify-content:center;min-width:42px;height:42px;padding:0 12px;border:1px solid #dedede;border-radius:10px;color:#252525; }
    .installer-pagination .is-active { border-color:#111;background:#111;color:#fff; }
    .installer-pagination .is-disabled { color:#aaa;background:#f7f7f7; }
    @media(max-width:991px){
        .installer-catalog__hero { padding:34px 0 30px; }
        .installer-filter { position:static;padding:16px;margin-bottom:22px; }
        .installer-filter__summary { display:flex; }
        .installer-filter__toggle:not(:checked) + .installer-filter__body { display:none; }
        .installer-filter__body { padding-top:18px; }
    }
    @media(max-width:575px){
        .installer-catalog__stats { display:grid;grid-template-columns:1fr 1fr; }
        .installer-catalog__stat { min-width:0; }
        .installer-result-head { align-items:flex-start;flex-direction:column; }
        .installer-card__media { height:210px; }
        .installer-card__actions { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
@php
    $activeFilters = collect(['q','region','city','specialization','rating','experience','verified','nationwide'])
        ->filter(fn ($key) => request()->filled($key))
        ->count();
@endphp

<main id="wrapper" class="installer-catalog">
    <section class="installer-catalog__hero">
        <div class="container">
            <div class="row align-items-end gy-24">
                <div class="col-lg-8">
                    <div class="breadcrumbs mb-16">
                        <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <p class="text-caption-01">Монтажники</p>
                    </div>
                    <span class="installer-catalog__eyebrow">Специалисты KOTLOV</span>
                    <h1 class="mb-14">Монтажники отопления в Беларуси</h1>
                    <p class="text-body-1 cl-text-2 mb-0" style="max-width:760px;">
                        Выберите специалиста по региону, опыту и направлению работ. В наполненных профилях доступны портфолио, отзывы и адресная заявка.
                    </p>
                    <div class="installer-catalog__stats">
                        <div class="installer-catalog__stat"><strong>{{ $installersCount }}</strong><span>опубликовано профилей</span></div>
                        <div class="installer-catalog__stat"><strong>{{ $worksCount }}</strong><span>работ в портфолио</span></div>
                        <div class="installer-catalog__stat"><strong>{{ $reviewsCount }}</strong><span>отзывов клиентов</span></div>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('become-installer') }}" class="tf-btn btn-outline">Стать монтажником</a>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing" id="installer-results">
        <div class="container">
            <div class="row g-28">
                <div class="col-lg-3">
                    <div class="installer-filter">
                        <label class="installer-filter__summary" for="installer-filter-toggle">
                            <span>Фильтры</span>
                            @if($activeFilters)<span class="installer-filter__count">{{ $activeFilters }}</span>@endif
                        </label>
                        <input class="installer-filter__toggle" id="installer-filter-toggle" type="checkbox" @checked($activeFilters)>
                        <form method="GET" action="{{ route('installers.index') }}" class="installer-filter__body">
                            <div>
                                <label for="installer-q">Поиск</label>
                                <input id="installer-q" class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Имя, компания или город">
                            </div>
                            <div>
                                <label for="installer-region">Регион</label>
                                <select id="installer-region" class="form-select" name="region">
                                    <option value="">Все регионы</option>
                                    @foreach($regions as $value => $label)
                                        <option value="{{ $value }}" @selected(request('region') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="installer-city">Город</label>
                                <select id="installer-city" class="form-select" name="city">
                                    <option value="">Все города</option>
                                    @foreach($cities as $value => $label)
                                        <option value="{{ $value }}" @selected(request('city') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="installer-specialization">Специализация</label>
                                <select id="installer-specialization" class="form-select" name="specialization">
                                    <option value="">Все направления</option>
                                    @foreach($specializations as $value => $label)
                                        <option value="{{ $value }}" @selected(request('specialization') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-10">
                                <div class="col-6 col-lg-12">
                                    <label for="installer-rating">Рейтинг</label>
                                    <select id="installer-rating" class="form-select" name="rating">
                                        <option value="">Любой</option>
                                        @foreach($ratings as $value => $label)
                                            <option value="{{ $value }}" @selected(request('rating') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-lg-12">
                                    <label for="installer-experience">Опыт</label>
                                    <select id="installer-experience" class="form-select" name="experience">
                                        <option value="">Любой</option>
                                        @foreach($experienceOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(request('experience') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <label class="installer-filter__check">
                                <input type="checkbox" name="verified" value="1" @checked(request()->boolean('verified'))>
                                Только проверенные
                            </label>
                            <label class="installer-filter__check">
                                <input type="checkbox" name="nationwide" value="1" @checked(request()->boolean('nationwide'))>
                                Выезд по Беларуси
                            </label>
                            <div>
                                <label for="installer-sort">Сортировка</label>
                                <select id="installer-sort" class="form-select" name="sort">
                                    <option value="recommended" @selected(request('sort', 'recommended') === 'recommended')>Рекомендуемые</option>
                                    <option value="rating" @selected(request('sort') === 'rating')>По рейтингу</option>
                                    <option value="experience" @selected(request('sort') === 'experience')>По опыту</option>
                                </select>
                            </div>
                            <button type="submit" class="tf-btn animate-btn w-100">Показать специалистов</button>
                            @if($activeFilters || request('sort'))
                                <a href="{{ route('installers.index') }}" class="text-center link" style="font-size:13px;">Сбросить фильтры</a>
                            @endif
                        </form>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="installer-result-head">
                        <div>
                            <h2 class="h4 mb-4">Специалисты</h2>
                            <p class="cl-text-2 mb-0">Найдено: {{ $installers->total() }}</p>
                        </div>
                        <a href="{{ route('install-requests.create') }}" class="link fw-semibold">Не знаете, кого выбрать? Оставить общую заявку →</a>
                    </div>

                    @if($installers->count())
                        <div class="row g-20">
                            @foreach($installers as $installer)
                                @php
                                    $name = $installer->company_name ?: ($installer->contact_name ?: 'Монтажник KOTLOV');
                                    $person = $installer->company_name && $installer->contact_name ? $installer->contact_name : null;
                                    $initialsSource = $installer->contact_name ?: $installer->company_name ?: 'KOTLOV';
                                    $initials = collect(preg_split('/\s+/u', trim($initialsSource)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                                    $location = $installer->nationwide ? 'Вся Беларусь' : implode(', ', array_filter([$installer->city, $installer->region]));
                                    $profileSpecs = collect($installer->specializations ?? [])->filter(fn ($spec) => isset($specializations[$spec]));
                                    $description = $installer->short_description ?: $installer->bio;
                                    $featuredPhoto = $installer->featuredWork && is_array($installer->featuredWork->photos)
                                        ? ($installer->featuredWork->photos[0] ?? null)
                                        : null;
                                    $cardMedia = \App\Support\InstallerMedia::url($installer->photo ?? $installer->logo ?? $featuredPhoto);
                                    $cardMediaIsLogo = $installer->logo
                                        && (!$installer->photo || $installer->photo === $installer->logo);
                                @endphp
                                <div class="col-xl-6 col-md-6">
                                    <article class="installer-card">
                                        <a class="installer-card__media {{ $cardMediaIsLogo ? 'is-logo' : '' }}" href="{{ route('installers.show', $installer->slug) }}" aria-label="Открыть профиль {{ $name }}">
                                            @if($cardMedia)
                                                <img loading="lazy" src="{{ $cardMedia }}" alt="Работа монтажника {{ $name }}">
                                            @else
                                                <span class="installer-card__initials">{{ $initials ?: 'K' }}</span>
                                            @endif
                                            @if($installer->is_verified)
                                                <span class="installer-card__verified">✓ Проверен KOTLOV</span>
                                            @endif
                                        </a>
                                        <div class="installer-card__body">
                                            <div class="d-flex justify-content-between gap-10 align-items-start mb-8">
                                                <div>
                                                    <h3 class="h6 mb-3"><a class="link" href="{{ route('installers.show', $installer->slug) }}">{{ $name }}</a></h3>
                                                    @if($person)<p class="cl-text-3 mb-0" style="font-size:12px;">{{ $person }}</p>@endif
                                                </div>
                                                @if((float)$installer->rating > 0)
                                                    <span class="installer-card__rating">★ {{ number_format((float)$installer->rating, 1, ',', ' ') }}</span>
                                                @endif
                                            </div>
                                            <p class="installer-card__place mb-10">⌖ {{ $location ?: 'Регион уточняется' }}</p>
                                            <p class="installer-card__description mb-0">{{ $description ? \Illuminate\Support\Str::limit($description, 105) : 'Описание и условия работы специалиста уточняются.' }}</p>
                                            <div class="installer-card__chips">
                                                @foreach($profileSpecs->take(3) as $spec)
                                                    <span class="installer-card__chip">{{ $specializations[$spec] }}</span>
                                                @endforeach
                                            </div>
                                            <div class="installer-card__facts">
                                                <div class="installer-card__fact"><strong>{{ $installer->experience_years ?: '—' }}</strong><span>лет опыта</span></div>
                                                <div class="installer-card__fact"><strong>{{ $installer->works_count }}</strong><span>работ</span></div>
                                                <div class="installer-card__fact"><strong>{{ $installer->reviews_count }}</strong><span>отзывов</span></div>
                                            </div>
                                            @if($installer->price_from)
                                                <p class="fw-semibold mt-14 mb-0">Работы от {{ number_format((float)$installer->price_from, 0, ',', ' ') }} BYN</p>
                                            @endif
                                            <div class="installer-card__actions">
                                                <a href="{{ route('installers.show', $installer->slug) }}" class="tf-btn btn-outline text-center">Профиль</a>
                                                <a href="{{ route('install-requests.create', ['installer' => $installer->id]) }}" class="tf-btn animate-btn text-center">Отправить заявку</a>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            @endforeach
                        </div>

                        @if($installers->hasPages())
                            <nav class="installer-pagination" aria-label="Страницы каталога">
                                @if($installers->onFirstPage())<span class="is-disabled">←</span>@else<a href="{{ $installers->previousPageUrl() }}">←</a>@endif
                                @foreach(range(1, $installers->lastPage()) as $page)
                                    @if($page === $installers->currentPage())<span class="is-active">{{ $page }}</span>@else<a href="{{ $installers->url($page) }}">{{ $page }}</a>@endif
                                @endforeach
                                @if($installers->hasMorePages())<a href="{{ $installers->nextPageUrl() }}">→</a>@else<span class="is-disabled">→</span>@endif
                            </nav>
                        @endif
                    @else
                        <div class="installer-empty">
                            <div style="font-size:42px;margin-bottom:12px;">⌕</div>
                            <h3 class="h5 mb-10">По выбранным параметрам специалистов пока нет</h3>
                            <p class="cl-text-2 mb-24">Сбросьте часть фильтров или оставьте общую заявку — менеджер поможет подобрать исполнителя.</p>
                            <div class="d-flex flex-wrap justify-content-center gap-10">
                                <a href="{{ route('installers.index') }}" class="tf-btn btn-outline">Сбросить фильтры</a>
                                <a href="{{ route('install-requests.create') }}" class="tf-btn animate-btn">Оставить заявку</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
