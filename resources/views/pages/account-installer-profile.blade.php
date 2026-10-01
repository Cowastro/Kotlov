@extends('layouts.amerce')

@section('title', 'Кабинет монтажника — KOTLOV')

@push('styles')
<style>
    .installer-cabinet { --ic-accent:#f28c00;--ic-ink:#171717;--ic-muted:#737373;--ic-line:#e8e6e2;--ic-soft:#f7f6f3; }
    .installer-cabinet > .flat-spacing { background:linear-gradient(180deg,#faf9f7 0,#fff 360px); }
    .installer-cabinet__hero { position:relative;isolation:isolate;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:28px;padding:30px 32px;border-radius:24px;background:linear-gradient(120deg,#111 0%,#201a16 58%,#3e2918 100%);color:#fff;box-shadow:0 18px 45px rgba(24,18,12,.14); }
    .installer-cabinet__hero::after { content:"";position:absolute;z-index:-1;right:-80px;top:-140px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(242,140,0,.28),rgba(242,140,0,0) 70%); }
    .installer-cabinet__hero h2 { letter-spacing:-.03em; }
    .installer-cabinet__hero p { color:rgba(255,255,255,.68); }
    .installer-cabinet__status { display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.1);font-size:11px;font-weight:700; }
    .installer-cabinet__grid { display:grid;grid-template-columns:minmax(0,1.55fr) minmax(340px,.65fr);gap:26px;align-items:start; }
    .installer-cabinet__card { padding:28px;border:1px solid var(--ic-line);border-radius:22px;background:#fff;box-shadow:0 12px 34px rgba(24,18,12,.045); }
    .installer-cabinet__section-head { display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px; }
    .installer-cabinet__section-head p { color:#777;font-size:13px; }
    .installer-cabinet__preview-card { position:sticky;top:22px;padding:0;overflow:hidden; }
    .installer-cabinet__photo { position:relative;width:100%;aspect-ratio:4/3;overflow:hidden;background:linear-gradient(135deg,#efe7dc,#d7c3a9); }
    .installer-cabinet__photo::after { content:"";position:absolute;inset:auto 0 0;height:45%;background:linear-gradient(transparent,rgba(0,0,0,.52));pointer-events:none; }
    .installer-cabinet__photo img { width:100%;height:100%;object-fit:cover; }
    .installer-cabinet__photo-fallback { display:grid;place-items:center;width:100%;height:100%;font-size:76px;font-weight:700;letter-spacing:-.06em;color:#6d5239; }
    .installer-cabinet__photo-label { position:absolute;z-index:1;left:18px;bottom:16px;display:flex;align-items:center;gap:7px;color:#fff;font-size:12px;font-weight:700; }
    .installer-cabinet__preview-body { padding:22px; }
    .installer-cabinet__preview-kicker { color:#a86200;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase; }
    .installer-cabinet__metrics { display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:18px 0; }
    .installer-cabinet__metric { padding:13px;border-radius:13px;background:var(--ic-soft); }
    .installer-cabinet__metric strong { display:block;font-size:20px;line-height:1;color:var(--ic-ink); }
    .installer-cabinet__metric span { display:block;margin-top:5px;color:var(--ic-muted);font-size:11px; }
    .installer-cabinet__check-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px; }
    .installer-cabinet__check { display:flex;align-items:center;gap:9px;padding:12px 14px;border:1px solid var(--ic-line);border-radius:12px;background:#fff;cursor:pointer;transition:.2s ease; }
    .installer-cabinet__check:has(input:checked) { border-color:#f2c174;background:#fff9ef; }
    .installer-cabinet__check input { width:17px;height:17px; }
    .installer-cabinet__work { padding:18px;border:1px solid var(--ic-line);border-radius:16px;background:#fff;transition:.2s ease; }
    .installer-cabinet__work:hover { border-color:#d8d2c9;box-shadow:0 10px 24px rgba(24,18,12,.05); }
    .installer-cabinet__work + .installer-cabinet__work { margin-top:14px; }
    .installer-cabinet__work-head { display:grid;grid-template-columns:110px 1fr auto;gap:16px;align-items:center; }
    .installer-cabinet__work-thumb { width:110px;aspect-ratio:16/10;border-radius:10px;overflow:hidden;background:#eee; }
    .installer-cabinet__work-thumb img { width:100%;height:100%;object-fit:cover; }
    .installer-cabinet__work summary { cursor:pointer;font-weight:700;color:#a85d00;list-style:none; }
    .installer-cabinet__work summary::-webkit-details-marker { display:none; }
    .installer-cabinet__work details[open] summary { margin-bottom:18px; }
    .installer-cabinet__hint { padding:14px 16px;border:1px solid #f4dfbd;border-radius:13px;background:#fff8ed;color:#714600;font-size:12px;line-height:1.55; }
    .installer-cabinet .tf-field > label { display:block;margin-bottom:7px;color:#34302b;font-size:12px;font-weight:650; }
    .installer-cabinet .tf-field > input:not([type="checkbox"]):not([type="file"]),
    .installer-cabinet .tf-field > select,
    .installer-cabinet .tf-field > textarea { width:100%;min-height:48px;padding:12px 14px;border:1px solid #ddd9d2;border-radius:12px;background:#fff;color:#222;outline:none;transition:border-color .2s,box-shadow .2s; }
    .installer-cabinet .tf-field > textarea { min-height:128px;resize:vertical; }
    .installer-cabinet .tf-field > input:focus,.installer-cabinet .tf-field > select:focus,.installer-cabinet .tf-field > textarea:focus { border-color:#d78a20;box-shadow:0 0 0 4px rgba(242,140,0,.09); }
    .installer-cabinet fieldset[disabled] input,.installer-cabinet fieldset[disabled] select,.installer-cabinet fieldset[disabled] textarea { opacity:1;color:#35322e;background:#f7f6f3;border-color:#e5e2dc; }
    .installer-cabinet textarea { min-height:120px; }
    .installer-cabinet input[type="file"] { width:100%;padding:12px;border:1px dashed #d8d3cb;border-radius:12px;background:#faf9f7; }
    @media(max-width:991px){
        .installer-cabinet__grid { grid-template-columns:1fr; }
        .installer-cabinet__hero { align-items:flex-start;flex-direction:column; }
        .installer-cabinet__preview-card { position:static; }
    }
    @media(max-width:575px){
        .installer-cabinet__hero,.installer-cabinet__card { padding:19px; }
        .installer-cabinet__preview-card { padding:0; }
        .installer-cabinet__check-grid { grid-template-columns:1fr; }
        .installer-cabinet__work-head { grid-template-columns:82px 1fr; }
        .installer-cabinet__work-thumb { width:82px; }
        .installer-cabinet__work-head > :last-child { grid-column:1/-1; }
    }
</style>
@endpush

@section('content')
@php
    $previewMode = $previewMode ?? false;
    $portfolioPhoto = $profile->works->pluck('photos')->filter()->flatten()->filter()->first();
    $profilePhoto = \App\Support\InstallerMedia::url($profile->photo ?: ($profile->logo ?: $portfolioPhoto));
    $profileInitials = collect(preg_split('/\s+/u', trim($profile->contact_name ?: $profile->company_name ?: 'KOTLOV')))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $publishedWorksCount = $profile->works->where('is_published', true)->count();
    $statusLabels = ['active' => 'Активен', 'pending' => 'На проверке', 'suspended' => 'Приостановлен', 'blocked' => 'Заблокирован'];
@endphp

<main id="wrapper" class="installer-cabinet">
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('account') }}" class="text-caption-01 cl-text-3 link">Личный кабинет</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Профиль монтажника</p>
                </div>
                <h1 class="h3">Кабинет монтажника</h1>
                <p class="text-body-1 cl-text-2">Редактируйте публичный профиль и добавляйте выполненные работы.</p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            @if($previewMode)
            <div class="alert alert-warning mb-20 d-flex flex-wrap align-items-center justify-content-between gap-12">
                <div>
                    <strong>Предпросмотр от имени администратора.</strong>
                    Вы видите кабинет монтажника, но вход под его учетной записью не выполнялся. Все поля заблокированы.
                </div>
                <a href="{{ \App\Filament\Resources\InstallerProfiles\InstallerProfileResource::getUrl('edit', ['record' => $profile]) }}" class="tf-btn btn-outline">Вернуться в админку</a>
            </div>
            @endif
            @if(session('success'))
            <div class="alert alert-success mb-20">{{ session('success') }}</div>
            @endif
            @if(isset($errors) && $errors->any())
            <div class="alert alert-danger mb-20">
                <strong>Проверьте заполнение формы:</strong>
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
            @endif

            <div class="installer-cabinet__hero mb-24">
                <div>
                    <span class="installer-cabinet__status mb-10">
                        <i class="icon icon-CheckCircle"></i>
                        {{ $statusLabels[$profile->status] ?? $profile->status }}
                        @if($profile->is_verified) · проверен KOTLOV @endif
                    </span>
                    <h2 class="h4 mb-6" style="color:#fff;">{{ $profile->display_name }}</h2>
                    <p class="mb-0">Все изменения контактных данных и новые работы публикуются в вашем профиле.</p>
                </div>
                <div class="d-flex flex-wrap gap-10">
                    @if($profile->is_published && $profile->slug)
                    <a href="{{ route('installers.show', $profile->slug) }}" target="_blank" class="tf-btn btn-white">Открыть профиль</a>
                    @endif
                    @if($previewMode)
                    <a href="{{ \App\Filament\Resources\InstallerProfiles\InstallerProfileResource::getUrl('edit', ['record' => $profile]) }}" class="tf-btn btn-outline" style="border-color:rgba(255,255,255,.35);color:#fff;">Редактировать в админке</a>
                    @else
                    <a href="{{ route('account') }}" class="tf-btn btn-outline" style="border-color:rgba(255,255,255,.35);color:#fff;">Обычный кабинет</a>
                    @endif
                </div>
            </div>

            <div class="installer-cabinet__grid">
                <div>
                    <section class="installer-cabinet__card mb-24">
                        <div class="installer-cabinet__section-head">
                            <div><h3 class="h5 mb-4">Данные профиля</h3><p class="mb-0">Информация, которую увидят клиенты в каталоге монтажников.</p></div>
                        </div>

                        <form method="POST" action="{{ route('account.installer-profile.update') }}" enctype="multipart/form-data" class="form-setting">
                            @csrf
                            @method('PUT')
                            <fieldset @disabled($previewMode) style="border:0;padding:0;margin:0;min-width:0;">

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium">Имя специалиста *</label>
                                    <input name="contact_name" value="{{ old('contact_name', $profile->contact_name) }}" required>
                                </fieldset>
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium">Компания</label>
                                    <input name="company_name" value="{{ old('company_name', $profile->company_name) }}">
                                </fieldset>
                            </div>

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Телефон *</label><input name="phone" value="{{ old('phone', $profile->phone) }}" required></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Дополнительный телефон</label><input name="additional_phone" value="{{ old('additional_phone', $profile->additional_phone) }}"></fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Email</label><input type="email" name="email" value="{{ old('email', $profile->email) }}"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Сайт</label><input type="url" name="website" value="{{ old('website', $profile->website) }}" placeholder="https://..."></fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-3 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Telegram</label><input name="telegram" value="{{ old('telegram', $profile->telegram) }}" placeholder="@username"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Viber</label><input name="viber" value="{{ old('viber', $profile->viber) }}"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $profile->whatsapp) }}"></fieldset>
                            </div>

                            <fieldset class="tf-field mb-16">
                                <label class="tf-lable fw-medium">Краткое описание</label>
                                <input name="short_description" maxlength="255" value="{{ old('short_description', $profile->short_description) }}" placeholder="Основная специализация и опыт">
                            </fieldset>
                            <fieldset class="tf-field mb-16">
                                <label class="tf-lable fw-medium">О специалисте</label>
                                <textarea name="bio" maxlength="3000" placeholder="Опыт, виды систем, подход к работе...">{{ old('bio', $profile->bio) }}</textarea>
                            </fieldset>

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Опыт, лет</label><input type="number" min="0" max="70" name="experience_years" value="{{ old('experience_years', $profile->experience_years) }}"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Стоимость работ от, BYN</label><input type="number" min="0" step="1" name="price_from" value="{{ old('price_from', $profile->price_from) }}"></fieldset>
                            </div>

                            <p class="fw-semibold mb-10">Специализации</p>
                            <div class="installer-cabinet__check-grid mb-20">
                                @foreach($specializations as $value => $label)
                                <label class="installer-cabinet__check">
                                    <input type="checkbox" name="specializations[]" value="{{ $value }}" @checked(in_array($value, old('specializations', $profile->specializations ?? []), true))>
                                    <span>{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>

                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Город</label><input name="city" value="{{ old('city', $profile->city) }}"></fieldset>
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium">Регион</label>
                                    <select name="region"><option value="">— Не указан —</option>@foreach($regions as $region)<option value="{{ $region }}" @selected(old('region', $profile->region) === $region)>{{ $region }}</option>@endforeach</select>
                                </fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Рабочие регионы</label><input name="work_regions_text" value="{{ old('work_regions_text', implode(', ', $profile->work_regions ?? [])) }}" placeholder="Через запятую"></fieldset>
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Рабочие города</label><input name="work_cities_text" value="{{ old('work_cities_text', implode(', ', $profile->work_cities ?? [])) }}" placeholder="Через запятую"></fieldset>
                            </div>
                            <div class="tf-grid-layout sm-col-2 mb-20">
                                <fieldset class="tf-field"><label class="tf-lable fw-medium">Радиус выезда, км</label><input type="number" min="0" max="2000" name="work_radius_km" value="{{ old('work_radius_km', $profile->work_radius_km) }}"></fieldset>
                                <label class="installer-cabinet__check align-self-end"><input type="checkbox" name="nationwide" value="1" @checked(old('nationwide', $profile->nationwide))><span>Выезжаю по всей Беларуси</span></label>
                            </div>

                            <fieldset class="tf-field mb-20">
                                <label class="tf-lable fw-medium">Фотография профиля</label>
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                                <span class="text-caption-01 cl-text-3 mt-4">JPG, PNG или WebP, до 5 МБ.</span>
                            </fieldset>

                            @unless($previewMode)<button class="tf-btn animate-btn" type="submit">Сохранить профиль</button>@endunless
                            </fieldset>
                        </form>
                    </section>

                    <section class="installer-cabinet__card">
                        <div class="installer-cabinet__section-head">
                            <div><h3 class="h5 mb-4">Мои работы</h3><p class="mb-0">Редактируйте описание объектов и управляйте их видимостью.</p></div>
                            <span class="installer-cabinet__status" style="background:#f4f4f4;color:#222;">{{ $profile->works->count() }} работ</span>
                        </div>

                        @forelse($profile->works as $work)
                        @php $workPhoto = is_array($work->photos) && count($work->photos) ? \App\Support\InstallerMedia::url($work->photos[0]) : null; @endphp
                        <article class="installer-cabinet__work">
                            <div class="installer-cabinet__work-head">
                                <div class="installer-cabinet__work-thumb">@if($workPhoto)<img src="{{ $workPhoto }}" alt="">@endif</div>
                                <div>
                                    <p class="fw-semibold mb-4">{{ $work->title }}</p>
                                    <p class="text-caption-01 cl-text-3 mb-0">{{ implode(' · ', array_filter([$work->city, $work->completed_at?->format('m.Y')])) }}</p>
                                </div>
                                <span class="installer-cabinet__status" style="background:{{ $work->is_published ? '#eaf8ef' : '#f3f3f3' }};color:{{ $work->is_published ? '#16713a' : '#666' }};">{{ $work->is_published ? 'Опубликована' : 'Скрыта' }}</span>
                            </div>
                            <details class="mt-16">
                                <summary>Редактировать работу ↓</summary>
                                <form method="POST" action="{{ route('account.installer-works.update', $work) }}" enctype="multipart/form-data">
                                    @csrf @method('PUT')
                                    <fieldset @disabled($previewMode) style="border:0;padding:0;margin:0;min-width:0;">
                                    @include('pages.partials.installer-work-fields', ['item' => $work])
                                    @unless($previewMode)<button class="tf-btn animate-btn mt-18" type="submit">Сохранить работу</button>@endunless
                                    </fieldset>
                                </form>
                                @unless($previewMode)
                                <form class="mt-10" method="POST" action="{{ route('account.installer-works.destroy', $work) }}" onsubmit="return confirm('Удалить эту работу из портфолио?');">
                                    @csrf @method('DELETE')
                                    <button class="tf-btn btn-outline" type="submit">Удалить</button>
                                </form>
                                @endunless
                            </details>
                        </article>
                        @empty
                        <p class="cl-text-3 mb-0">Работ пока нет. Добавьте первый объект ниже.</p>
                        @endforelse
                    </section>

                    @unless($previewMode)
                    <section class="installer-cabinet__card mt-24" id="add-work">
                        <div class="installer-cabinet__section-head">
                            <div><h3 class="h5 mb-4">Добавить работу</h3><p class="mb-0">Покажите объект, оборудование и результат — хорошее портфолио повышает доверие клиентов.</p></div>
                        </div>
                        <form method="POST" action="{{ route('account.installer-works.store') }}" enctype="multipart/form-data">
                            @csrf
                            <fieldset style="border:0;padding:0;margin:0;min-width:0;">
                            @include('pages.partials.installer-work-fields', ['item' => null])
                            <button class="tf-btn animate-btn mt-18" type="submit">Добавить в портфолио</button>
                            </fieldset>
                        </form>
                    </section>
                    @endunless
                </div>

                <aside>
                    <section class="installer-cabinet__card installer-cabinet__preview-card">
                        <div class="installer-cabinet__photo">
                            @if($profilePhoto)
                            <img src="{{ $profilePhoto }}" alt="{{ $profile->display_name }}">
                            @else
                            <div class="installer-cabinet__photo-fallback">{{ $profileInitials }}</div>
                            @endif
                            <span class="installer-cabinet__photo-label"><i class="icon icon-CheckCircle"></i> Так профиль видит клиент</span>
                        </div>
                        <div class="installer-cabinet__preview-body">
                            <div class="installer-cabinet__preview-kicker mb-8">Публичный профиль</div>
                            <h3 class="h6 mb-7">{{ $profile->display_name }}</h3>
                            <p class="text-caption-01 cl-text-3 mb-0">{{ $profile->short_description ?: 'Добавьте краткое описание профиля' }}</p>
                            <div class="installer-cabinet__metrics">
                                <div class="installer-cabinet__metric"><strong>{{ $profile->experience_years ?: '—' }}</strong><span>лет опыта</span></div>
                                <div class="installer-cabinet__metric"><strong>{{ $publishedWorksCount }}</strong><span>работ в портфолио</span></div>
                            </div>
                            <div class="installer-cabinet__hint mb-14">Контакты, описание и работы монтажник меняет сам. Статус публикации и отметку «Проверен KOTLOV» контролирует администратор.</div>
                            @if($profile->is_published && $profile->slug)
                            <a href="{{ route('installers.show', $profile->slug) }}" target="_blank" class="tf-btn btn-outline w-100">Открыть публичную страницу</a>
                            @endif
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </section>
</main>
@endsection
