@once
    @push('styles')
        <style>
            .catalog-type-nav__header {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 8px 16px;
                margin-bottom: 12px;
            }

            .catalog-type-nav__header h2 {
                margin: 0;
                font-size: 20px;
            }

            .catalog-type-nav__grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
            }

            .catalog-type-card {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
                padding: 14px;
                color: var(--text);
                background: var(--white);
                border: 1px solid var(--line);
                border-radius: 16px;
            }

            .catalog-type-card:hover,
            .catalog-type-card:focus-visible,
            .catalog-type-card[aria-current="page"] {
                color: var(--text);
                border-color: var(--text);
            }

            .catalog-type-card[hidden] { display: none; }

            .catalog-type-card__icon {
                display: grid;
                place-items: center;
                flex: 0 0 42px;
                width: 42px;
                height: 42px;
                border-radius: 11px;
                color: var(--type-ink);
                background: var(--type-fill);
            }

            .catalog-type-card--flame { --type-ink: #a63c1c; --type-fill: #fff0e9; }
            .catalog-type-card--lightning { --type-ink: #2257ba; --type-fill: #eaf1ff; }
            .catalog-type-card--wood { --type-ink: #80502e; --type-fill: #f7eee5; }
            .catalog-type-card--pellets { --type-ink: #42633b; --type-fill: #eef4e9; }
            .catalog-type-card--indirect { --type-ink: #684694; --type-fill: #f3edfa; }
            .catalog-type-card--combined { --type-ink: #17615c; --type-fill: #e7f5f2; }
            .catalog-type-card--equipment { --type-ink: #48576b; --type-fill: #edf1f6; }

            .catalog-type-card__icon .icon { font-size: 21px; }

            .catalog-type-card__text {
                display: grid;
                gap: 3px;
                flex: 1;
                min-width: 0;
            }

            .catalog-type-card__name {
                font-size: 15px;
                line-height: 1.25;
                overflow-wrap: anywhere;
            }

            .catalog-type-card__detail {
                font-size: 12px;
                line-height: 1.35;
                color: var(--text-2);
            }

            .catalog-type-card__arrow {
                flex: none;
                font-size: 13px;
                color: var(--text-2);
            }

            @media (max-width: 1199px) {
                .catalog-type-card__arrow { display: none; }
            }

            @media (max-width: 991px) {
                .catalog-type-nav__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }

            @media (max-width: 575px) {
                .catalog-type-nav__header h2 { font-size: 17px; }
                .catalog-type-nav__grid { gap: 8px; }
                .catalog-type-card { flex-direction: column; align-items: flex-start; gap: 7px; padding: 10px; }
                .catalog-type-card__icon { flex-basis: 36px; width: 36px; height: 36px; }
                .catalog-type-card__name { font-size: 13px; }
            }
        </style>
    @endpush
@endonce

@php
    $categoryIcons = [
        'flame' => 'M12 22a7 7 0 0 0 7-7c0-4-3-6-4-9-.5 2-1.5 3-3 4-1-3-1-5 0-8-4 3-7 8-7 13a7 7 0 0 0 7 7Zm0 0c-2 0-3.5-1.5-3.5-3.5 0-1.7 1-3.2 2.5-4.5.4 1.3 1.2 2 2.2 2.3.3-.6.5-1.2.5-2.1 1.2 1.3 1.8 2.8 1.8 4.3C15.5 20.5 14 22 12 22Z',
        'lightning' => 'M13 2 4 13h7l-1 9 10-12h-7l1-8Z',
        'wood' => 'M6 10 15 4c2-1 4 0 5 2s1 4-1 5l-2 1M5 11l5-3c2-1 4 0 5 2l2 3M7 20l12-6c2-1 2-4 1-5M7 20c2-1 2-3 1-5s-3-3-5-2-2 3-1 5 3 3 5 2Zm-2-4 .8 1.2',
        'pellets' => 'M5 4h3a1.5 1.5 0 0 1 0 3H5a1.5 1.5 0 0 1 0-3Zm10 2h3a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 1 0-3ZM6 11h3a1.5 1.5 0 0 1 0 3H6a1.5 1.5 0 0 1 0-3Zm10 2h3a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 1 0-3ZM8 18h3a1.5 1.5 0 0 1 0 3H8a1.5 1.5 0 0 1 0-3Z',
        'indirect' => 'M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm1 6h6a2 2 0 0 1 0 4h-4a2 2 0 0 0 0 4h6',
        'combined' => 'M9 4c-2 3-6 7-6 10a6 6 0 0 0 11.7 1.8M17 3l-5 7h4l-1 6 6-8h-4l1-5Z',
    ];
@endphp

@isset($heading)
    <div class="catalog-type-nav__header">
        <h2>{{ $heading }}</h2>
        @if ($showStockToggle ?? true)
            @include('partials.catalog-stock-toggle')
        @endif
    </div>
@endisset

@if (count($entries) > 0)
    <div class="catalog-type-nav__grid">
        @foreach ($entries as $slug => $entry)
            @php
                $icon = $entry['icon'];
                $tone = match ($icon) {
                    'icon-Sparkle' => 'flame',
                    'icon-Lightning', 'icon-Lightning-1', 'icon-Devices' => 'lightning',
                    'icon-Leaf' => 'pellets',
                    'icon-Layout', 'icon-CopySimple', 'icon-Tag' => 'indirect',
                    'icon-Wind', 'icon-filter', 'icon-GitDiff', 'icon-ArrowClockwise' => 'combined',
                    default => isset($categoryIcons[$icon]) ? $icon : 'equipment',
                };
                $href = $entry['url'] ?? url('/'.$slug).($inStockOnly ? '?in_stock=1#catalog-products' : '');
            @endphp
            <a class="catalog-type-card catalog-type-card--{{ $tone }}"
               href="{{ $href }}"
               @isset($entry['search']) data-accessories-item data-search="{{ $entry['search'] }}" @endisset
               @if (($selectedTypeSlug ?? null) === $slug) aria-current="page" @endif>
                <span class="catalog-type-card__icon" aria-hidden="true">
                    @isset($categoryIcons[$icon])
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="{{ $categoryIcons[$icon] }}"></path>
                        </svg>
                    @else
                        <i class="icon {{ $icon }}"></i>
                    @endisset
                </span>
                <span class="catalog-type-card__text">
                    <strong class="catalog-type-card__name">{{ $entry['name'] }}</strong>
                    @if (! empty($entry['description']))
                        <span class="catalog-type-card__detail">{{ $entry['description'] }}</span>
                    @endif
                    @isset($entry['count'])
                        <span class="catalog-type-card__detail">{{ $entry['count'] }} в каталоге</span>
                    @endisset
                </span>
                <i class="catalog-type-card__arrow icon icon-CaretRightThin" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif
