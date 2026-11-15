<div class="d-flex flex-wrap gap-2 {{ ($materialButtons ?? false) ? '' : 'ps-3 pb-2' }}" aria-label="Резьбовые фитинги по материалу">
    @foreach ($materialLinks as $materialLink)
        <a href="{{ url($materialLink['path']) }}" class="{{ ($materialButtons ?? false) ? 'tf-btn small '.(request()->getPathInfo() === $materialLink['path'] ? 'btn-primary' : 'btn-stroke') : 'link text-caption-01' }}"
           @if (request()->getPathInfo() === $materialLink['path']) aria-current="page" @endif>
            {{ $materialLink['short'] }}@if ($materialButtons ?? false) <span class="text-caption-01 cl-text-2">{{ $materialLink['count'] }}</span>@endif
        </a>
    @endforeach
</div>
