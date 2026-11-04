@php($iconFile = config('navigation.icons.'.$category->slug))
<span class="mobile-catalog-category__icon">
    @if($iconFile)
        <img src="{{ route('catalog.icon', ['file' => pathinfo($iconFile, PATHINFO_FILENAME)]) }}"
            alt="" width="28" height="28" loading="lazy">
    @else
        <i class="icon icon-SquaresFour" aria-hidden="true"></i>
    @endif
</span>
