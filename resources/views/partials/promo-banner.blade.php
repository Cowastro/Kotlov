{{-- Промо-баннер в табе. Переменные: $banner (модель|null), $fallbackImage, $fallbackLink, $fallbackTitle, $fallbackDesc, $fallbackBtn --}}
<div class="banner-image-text type-abs style-18 v2 mb-20">
    <a href="{{ $banner?->link ?: $fallbackLink }}" class="bn-image img-style">
        <img loading="lazy" width="450" height="608"
            src="{{ $banner ? asset('storage/' . $banner->image) : asset($fallbackImage) }}"
            alt="{{ $banner?->title ?: $fallbackTitle }}">
    </a>
    <div class="bn-content wow fadeInUp">
        <a href="{{ $banner?->link ?: $fallbackLink }}" class="title text-white h3 fw-medium link mb-8">
            {!! $banner ? nl2br(e($banner->title)) : $fallbackTitle !!}
        </a>
        <p class="desc text-body-1 text-white mb-28">
            {{ $banner?->subtitle ?: $fallbackDesc }}
        </p>
        <a href="{{ $banner?->link ?: $fallbackLink }}" class="tf-btn btn-white hv-primary">
            {{ $banner?->button_text ?: $fallbackBtn }}
        </a>
    </div>
</div>
