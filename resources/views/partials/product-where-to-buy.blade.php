{{-- "Где купить": partner branches of the selected city; phones are revealed on demand and logged. --}}
@php
    $wtbCity = $selectedCity ?? null;
    $wtbBranches = $wtbCity?->exists && \Illuminate\Support\Facades\Schema::hasTable('partner_branches')
        ? \App\Models\PartnerBranch::query()->visibleIn($wtbCity)->get()
        : collect();
    $wtbCityName = $wtbCity?->name ?: ($selectedCityName ?? 'Минск');
    $wtbCityIn = $wtbCity?->name_in ?: 'в '.$wtbCityName;
    $wtbIcons = [
        'store' => '<path d="M3 9 5 4h14l2 5M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0zM5 13v8h14v-8"/>',
        'pin' => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'shield' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.5"/><circle cx="17" cy="17.5" r="1.5"/>',
    ];
    $wtbIcon = fn (string $name): string => '<svg viewBox="0 0 24 24" aria-hidden="true">'.$wtbIcons[$name].'</svg>';
@endphp
@once
    @push('styles')
        <style>
            .wtb { position:relative; margin-top:20px; padding:22px 22px 18px; border:1px solid var(--line,#e8e8e8); border-radius:16px; background:var(--white,#fff); overflow:hidden; }
            .wtb::before { content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--primary,#e04747); }
            .wtb svg { fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
            .wtb__head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px; }
            .wtb__kicker { display:block; margin-bottom:4px; color:var(--primary,#e04747); font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
            .wtb__head h2 { margin:0; font-size:20px; line-height:1.25; }
            .wtb__count { color:var(--text-2,#6b7280); font-size:14px; font-weight:500; }
            .wtb__city { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border:1px solid var(--line,#e8e8e8); border-radius:999px; color:var(--text,#111)!important; font-size:13px; font-weight:600; }
            .wtb__city svg { width:15px; height:15px; color:var(--primary,#e04747); }
            .wtb__list { display:grid; gap:10px; margin:0; padding:0; list-style:none; }
            .wtb__item { display:grid; grid-template-columns:44px minmax(0,1fr) auto; align-items:center; gap:14px; padding:14px; border:1px solid transparent; border-radius:14px; background:var(--bg-2,#f6f5f2); transition:.2s ease; }
            .wtb__item:hover { border-color:var(--line,#e8e8e8); background:var(--white,#fff); box-shadow:0 10px 26px rgba(27,32,36,.07); }
            .wtb__avatar { display:grid; place-items:center; width:44px; height:44px; border-radius:12px; background:var(--white,#fff); color:var(--primary,#e04747); }
            .wtb__avatar svg { width:22px; height:22px; }
            .wtb__name { display:flex; flex-wrap:wrap; align-items:center; gap:6px 8px; margin-bottom:4px; }
            .wtb__name strong { font-size:15px; line-height:1.3; }
            .wtb__badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px; background:#e8f6ef; color:#0b8a5c; font-size:11px; font-weight:700; }
            .wtb__badge svg { width:12px; height:12px; stroke-width:2.2; }
            .wtb__meta { display:flex; align-items:flex-start; gap:6px; margin-top:2px; color:var(--text-2,#6b7280); font-size:13px; line-height:1.4; }
            .wtb__meta svg { flex:0 0 14px; width:14px; height:14px; margin-top:2px; }
            .wtb__meta a { color:inherit!important; text-decoration:underline; text-underline-offset:2px; }
            .wtb__phone { display:inline-flex; align-items:center; gap:7px; padding:10px 16px; border:0; border-radius:999px; background:#1d252c; color:#fff; font-size:13px; font-weight:600; white-space:nowrap; cursor:pointer; transition:.2s ease; }
            .wtb__phone svg { width:15px; height:15px; }
            .wtb__phone:hover { background:var(--primary,#e04747); }
            .wtb__phone:disabled { opacity:.6; cursor:default; }
            .wtb__phones { display:grid; gap:2px; text-align:right; }
            .wtb__phones a { color:var(--text,#111)!important; font-size:15px; font-weight:700; white-space:nowrap; }
            .wtb__note { display:flex; align-items:center; gap:7px; margin:14px 0 0; color:var(--text-2,#6b7280); font-size:12px; }
            .wtb__note svg { width:15px; height:15px; color:#0b8a5c; }
            @media (max-width:575px) {
                .wtb { padding:18px 16px 14px; }
                .wtb__head { flex-direction:column; }
                .wtb__item { grid-template-columns:40px minmax(0,1fr); }
                .wtb__item > [data-wtb-slot], .wtb__item > .wtb__phones { grid-column:1/-1; }
                .wtb__phone { justify-content:center; width:100%; }
                .wtb__phones { text-align:left; }
            }
        </style>
    @endpush
@endonce
<section class="wtb" aria-labelledby="wtb-title" data-where-to-buy>
    <div class="wtb__head">
        <div>
            <span class="wtb__kicker">Продавцы KOTLOV</span>
            <h2 id="wtb-title">Где купить {{ $wtbCityIn }}
                @if($wtbBranches->count() > 1)<span class="wtb__count">· {{ $wtbBranches->count() }} {{ trans_choice('точка|точки|точек', $wtbBranches->count()) }}</span>@endif
            </h2>
        </div>
        @if($citySelectorEnabled ?? false)
            <a href="#citySelector" data-bs-toggle="offcanvas" class="wtb__city">{!! $wtbIcon('pin') !!}{{ $wtbCityName }}</a>
        @endif
    </div>
    @if($wtbBranches->isNotEmpty())
        <ul class="wtb__list">
            @foreach($wtbBranches as $branch)
                <li class="wtb__item">
                    <span class="wtb__avatar">{!! $wtbIcon('store') !!}</span>
                    <div>
                        <div class="wtb__name"><strong>{{ $branch->name }}</strong><span class="wtb__badge">{!! $wtbIcon('shield') !!}Партнёр KOTLOV</span></div>
                        @if($branch->address)
                            <div class="wtb__meta">{!! $wtbIcon('pin') !!}<span>@if($branch->map_url)<a href="{{ $branch->map_url }}" target="_blank" rel="noopener">{{ $branch->address }}</a>@else{{ $branch->address }}@endif</span></div>
                        @endif
                        @if($branch->working_hours)<div class="wtb__meta">{!! $wtbIcon('clock') !!}<span>{{ $branch->working_hours }}</span></div>@endif
                    </div>
                    <div data-wtb-slot>
                        <button type="button" class="wtb__phone"
                            data-wtb-phone="{{ route('partner-branches.phone', $branch) }}"
                            data-product-id="{{ $product->id ?? '' }}">{!! $wtbIcon('phone') !!}Показать телефон</button>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="wtb__note">{!! $wtbIcon('shield') !!}Официальные партнёры KOTLOV: цена и гарантия — как на сайте.</p>
    @else
        <ul class="wtb__list">
            <li class="wtb__item">
                <span class="wtb__avatar">{!! $wtbIcon('truck') !!}</span>
                <div>
                    <div class="wtb__name"><strong>{{ $cityContact['company_name'] ?? 'KOTLOV.BY' }}</strong></div>
                    <div class="wtb__meta"><span>Заказ онлайн с доставкой {{ $wtbCityIn }}</span></div>
                </div>
                <div class="wtb__phones">
                    @foreach(array_slice($cityContact['phone_links'] ?? [], 0, 1) as $phone)
                        <a href="tel:{{ $phone['href'] }}">{{ $phone['display'] }}</a>
                    @endforeach
                </div>
            </li>
        </ul>
    @endif
</section>
@include('partials.partner-branch-phone-script')
