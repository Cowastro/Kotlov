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
            .catalog-type-card--water { --type-ink: #0a6c99; --type-fill: #e5f4fb; }

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
    $plumbingIcons = [
        // Сантехника: свой рисунок у каждого подраздела, без повторов.
        'smesiteli' => ['water', 'M4 21h10M7 21V10a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v2h-3v-1a1 1 0 0 0-1-1h-5v11M11.5 7V4M9 4h5M17.5 15v1m0 3v1'],
        'dushevye-sistemy' => ['combined', 'M5 22V7a4 4 0 0 1 7.5-2M10 9h9l-1.5-3.5a2 2 0 0 0-1.8-1.2h-2.4a2 2 0 0 0-1.8 1.2L10 9m1 3.5-.5 1.5m4-1.5V14m4-1.5.5 1.5M12 17l-.5 1.5m4-1.5v1.5m3.5-1.5.5 1.5'],
        'gibkaya-podvodka-dlya-vody' => ['lightning', 'M2 9h3v6H2V9Zm17 0h3v6h-3V9ZM5 11c1.4-1.6 2.6 1.6 4 0s2.6 1.6 4 0 2.6 1.6 4 0l2 .3M5 13c1.4 1.6 2.6-1.6 4 0s2.6-1.6 4 0 2.6-1.6 4 0l2-.3'],
        'zhestkaya-podvodka-dlya-vody' => ['equipment', 'M3 3h5v3H3V3Zm2.5 3v9a3 3 0 0 0 3 3H17m0-2.5h3v5h-3v-5ZM8 3V2m-5 1V2m17 15h2M8.5 9.5v4'],
        'shlangi-i-soediniteli' => ['water', 'M4 3v4m-2 0h4v4H2V7Zm2 4v5a4 4 0 0 0 8 0v-3a4 4 0 0 1 8 0v4m-2 0h4v4h-4v-4Zm2 4v2'],
        'sifony-i-trapy' => ['combined', 'M3 3h12l-1.5 3h-9L3 3Zm6 3v6a4 4 0 0 0 8 0V9h4m0-2.5v5'],
        'installyatsii-i-armatura-bachkov' => ['indirect', 'M6 2h12v13H6V2Zm3.5 4h5v3.5h-5V6Zm2.5 0v3.5M6 15v7m12-7v7M4 22h4m8 0h4M6 12h12'],
        'santekhnicheskaya-posuda' => ['lightning', 'M6 3h5v8H6V3Zm-2 8h16a7 7 0 0 1-7 6h-.5l.8 4H8l.7-4.4A7 7 0 0 1 4 11Z'],
        'polotentsesushiteli' => ['flame', 'M6 2v20M18 2v20M6 6h12M6 10.5h12M6 15h12M6 19.5h12M4 4h2m12 0h2'],
        'komplektuyushchie-dlya-santekhniki' => ['equipment', 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6Z'],
        'vodorazbornye-krany' => ['combined', 'M3 11h12a5 5 0 0 1 5 5v2h-4v-2a1 1 0 0 0-1-1H3m4-4V7h5v4M9.5 7V3M6 4h7M3 9v8m16 4h-2'],
        'raspredelitelnye-kollektory' => ['combined', 'M3 8h18v7H3V8Zm4 7v6m5-6v6m5-6v6M7 8V3m5 5V3m5 5V3M5 4h4m1 0h4m1 0h4'],
        'komplektuyushchie-kollektorov' => ['equipment', 'M4 5h6v14H4V5Zm2-3h2v3m-2 14v3m7-15h7v5h-7V7Zm3-3v3m0 5v8m-3-3h6M6 9h2m-2 4h2'],
        'instrument-dlya-prochistki-trub' => ['equipment', 'M4 5h10a5 5 0 0 1 0 10H8a3 3 0 0 0 0 6h12M4 3v4M1 4v2m15-1V2h4v3m-4 14v4m4-4v4'],
        'fitingi-dlya-mednyh-trub' => ['wood', 'M3 3h7v8h11v7H8a5 5 0 0 1-5-5V3Zm0 2h7m9 6v7M1 3h11m9 6v11'],
        'gazovye-filtry' => ['pellets', 'M3 7h18v10H3V7Zm-2 2h2m18 0h2M8 7v10m8-10v10m-4-7v4m-1 3v5h5m-2-2v4'],
        'aksessuary-dlya-vannoy' => ['indirect', 'M4 9h16v7a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9Zm2 0V5a2 2 0 0 1 4 0v1m-7 3h18M7 21v2m10-2v2'],
    ];
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
                $plumbingIcon = $plumbingIcons[$slug] ?? null;
                $tone = match ($icon) {
                    'icon-Sparkle' => 'flame',
                    'icon-Lightning', 'icon-Lightning-1', 'icon-Devices' => 'lightning',
                    'icon-Leaf' => 'pellets',
                    'icon-Layout', 'icon-CopySimple', 'icon-Tag' => 'indirect',
                    'icon-Wind', 'icon-filter', 'icon-GitDiff', 'icon-ArrowClockwise' => 'combined',
                    default => isset($categoryIcons[$icon]) ? $icon : 'equipment',
                };
                $tone = $plumbingIcon[0] ?? $tone;
                $href = $entry['url'] ?? url('/'.$slug).($inStockOnly ? '?in_stock=1#catalog-products' : '');
            @endphp
            <a class="catalog-type-card catalog-type-card--{{ $tone }}"
               href="{{ $href }}"
               @isset($entry['search']) data-accessories-item data-search="{{ $entry['search'] }}" @endisset
               @if (($selectedTypeSlug ?? null) === $slug) aria-current="page" @endif>
                <span class="catalog-type-card__icon" aria-hidden="true">
                    @if ($plumbingIcon)
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="{{ $plumbingIcon[1] }}"></path></svg>
                    @elseif (isset($categoryIcons[$icon]))
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
                             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="{{ $categoryIcons[$icon] }}"></path>
                        </svg>
                    @else
                        <i class="icon {{ $icon }}"></i>
                    @endif
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
