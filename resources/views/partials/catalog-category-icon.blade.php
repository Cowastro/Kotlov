@php($iconFile = config('navigation.icons.'.$category->slug))
<span class="mobile-catalog-category__icon">
    @if($iconFile)
        <img src="{{ asset('assets/icon/catalog/'.pathinfo($iconFile, PATHINFO_FILENAME).'.svg') }}"
            alt="" width="28" height="28" loading="lazy">
    @else
        <i class="icon icon-SquaresFour" aria-hidden="true"></i>
    @endif
</span>
