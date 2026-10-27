@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 2);
        $end = min($last, $current + 2);
        if ($current <= 3) $end = min($last, 5);
        if ($current >= $last - 2) $start = max(1, $last - 4);
    @endphp
    <nav class="b2b-pagination" aria-label="Навигация по страницам">
        <p class="b2b-pagination__summary mb-0">
            Товары {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} из {{ $paginator->total() }}
        </p>
        <div class="b2b-pagination__pages">
            @if ($paginator->onFirstPage())
                <span class="b2b-pagination__disabled" aria-disabled="true">←</span>
            @else
                <a class="b2b-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Предыдущая страница">←</a>
            @endif

            @if ($start > 1)
                <a class="b2b-pagination__link" href="{{ $paginator->url(1) }}">1</a>
                @if ($start > 2)<span class="b2b-pagination__ellipsis">…</span>@endif
            @endif

            @for ($page = $start; $page <= $end; $page++)
                @if ($page === $current)
                    <span class="b2b-pagination__current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="b2b-pagination__link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                @endif
            @endfor

            @if ($end < $last)
                @if ($end < $last - 1)<span class="b2b-pagination__ellipsis">…</span>@endif
                <a class="b2b-pagination__link" href="{{ $paginator->url($last) }}">{{ $last }}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="b2b-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Следующая страница">→</a>
            @else
                <span class="b2b-pagination__disabled" aria-disabled="true">→</span>
            @endif
        </div>
    </nav>
@endif
