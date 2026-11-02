@extends('layouts.amerce')

@section('title', 'Кабинет монтажника — KOTLOV')

@push('styles')
@include('partials.installer-editor.styles')
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
                    @if(auth()->user()?->activeB2bClient())
                    <a href="{{ route('b2b.cabinet') }}" class="tf-btn btn-white">B2B-кабинет</a>
                    @endif
                    <a href="{{ route('account') }}" class="tf-btn btn-outline" style="border-color:rgba(255,255,255,.35);color:#fff;">Обычный кабинет</a>
                    @endif
                </div>
            </div>

@include('partials.installer-editor.editor')
        </div>
    </section>
</main>
@endsection
