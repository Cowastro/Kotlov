{{-- "Где купить": partner branches of the selected city; phones are revealed on demand and logged. --}}
@include('partials.branch-social-styles')
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
            .wtb { position:relative; margin-top:28px; border:1px solid var(--line,#e8e8e8); border-radius:16px; background:var(--white,#fff); overflow:hidden; }
            .wtb::before { content:""; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--primary,#e04747); }
            .wtb svg { fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
            .wtb__head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px 14px; }
            .wtb__kicker { display:block; margin-bottom:4px; color:var(--primary,#e04747); font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
            .wtb__head h2 { margin:0; font-size:20px; line-height:1.25; }
            .wtb__count { color:var(--text-2,#6b7280); font-size:14px; font-weight:500; }
            .wtb__city { flex:0 0 auto; display:inline-flex; align-items:center; gap:7px; padding:7px 11px; border:1px solid var(--line,#e8e8e8); border-radius:999px; background:var(--white,#fff); color:var(--text,#111)!important; font-size:13px; text-decoration:none!important; cursor:pointer; transition:border-color .2s ease,background .2s ease,box-shadow .2s ease; }
            .wtb__city svg { width:15px; height:15px; color:var(--primary,#e04747); }
            .wtb__city-name { font-weight:700; }
            .wtb__city-action { padding-left:7px; border-left:1px solid var(--line,#e8e8e8); color:var(--primary,#e04747); font-size:12px; font-weight:700; }
            .wtb__city-chevron { width:7px!important; height:12px!important; color:var(--text-2,#6b7280)!important; }
            .wtb__city:hover { border-color:var(--primary,#e04747); background:#fff7f7; box-shadow:0 3px 12px rgba(224,71,71,.1); }
            .wtb__city:focus-visible { outline:2px solid var(--primary,#e04747); outline-offset:2px; }
            .wtb__list { display:grid; margin:0; padding:0; border-top:1px solid var(--line,#e8e8e8); list-style:none; }
            .wtb__item { display:grid; grid-template-columns:38px minmax(210px,.9fr) minmax(240px,1.25fr) auto; align-items:center; gap:12px 16px; min-height:70px; padding:11px 20px; background:var(--white,#fff); transition:.2s ease; }
            .wtb__item + .wtb__item { border-top:1px solid var(--line,#e8e8e8); }
            .wtb__item:hover { background:var(--bg-2,#f6f5f2); }
            .wtb__avatar { display:grid; place-items:center; width:38px; height:38px; border-radius:10px; background:var(--bg-2,#f6f5f2); color:var(--primary,#e04747); }
            .wtb__avatar svg { width:19px; height:19px; }
            .wtb__name { display:flex; flex-wrap:wrap; align-items:center; gap:5px 8px; }
            .wtb__name strong { font-size:15px; line-height:1.3; }
            .wtb__badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px; background:#e8f6ef; color:#0b8a5c; font-size:11px; font-weight:700; }
            .wtb__badge svg { width:12px; height:12px; stroke-width:2.2; }
            .wtb__details { display:grid; gap:3px; min-width:0; }
            .wtb__meta { display:flex; align-items:flex-start; gap:6px; color:var(--text-2,#6b7280); font-size:13px; line-height:1.35; }
            .wtb__meta svg { flex:0 0 14px; width:14px; height:14px; margin-top:2px; }
            .wtb__meta a { color:inherit!important; text-decoration:underline; text-underline-offset:2px; }
            .wtb__phone { display:inline-flex; align-items:center; gap:7px; min-height:40px; padding:9px 15px; border:0; border-radius:999px; background:#1d252c; color:#fff; font-size:13px; font-weight:600; white-space:nowrap; cursor:pointer; transition:.2s ease; }
            .wtb__phone svg { width:15px; height:15px; }
            .wtb__phone:hover { background:var(--primary,#e04747); }
            .wtb__phone:disabled { opacity:.6; cursor:default; }
            .wtb__phones { display:grid; gap:2px; text-align:right; }
            .wtb__phones a { color:var(--text,#111)!important; font-size:15px; font-weight:700; white-space:nowrap; }
            .wtb__footer { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:10px 20px 12px; border-top:1px solid var(--line,#e8e8e8); }
            .wtb__note { display:flex; align-items:center; gap:7px; margin:0; color:var(--text-2,#6b7280); font-size:12px; }
            .wtb__note svg { flex:0 0 15px; width:15px; height:15px; color:#0b8a5c; }
            .wtb__partner-cta { display:flex; align-items:center; gap:9px; color:var(--text-2,#6b7280); font-size:12px; white-space:nowrap; }
            .wtb__partner-link { display:inline-flex; align-items:center; gap:5px; color:var(--primary,#e04747)!important; font-size:12px; font-weight:700; text-decoration:none!important; }
            .wtb__partner-link::after { content:"→"; font-size:15px; line-height:1; transition:transform .2s ease; }
            .wtb__partner-link:hover::after { transform:translateX(3px); }
            .wtb__partner-link:focus-visible { outline:2px solid var(--primary,#e04747); outline-offset:3px; border-radius:2px; }
            @media (max-width:991px) {
                .wtb__item { grid-template-columns:38px minmax(170px,.9fr) minmax(180px,1fr) auto; }
            }
            @media (max-width:767px) {
                .wtb { margin-top:20px; border-radius:12px; }
                .wtb__head { align-items:flex-start; padding:15px 16px 13px; }
                .wtb__head h2 { font-size:18px; }
                .wtb__city { padding:6px 9px; }
                .wtb__city-action { font-size:11px; }
                .wtb__item { grid-template-columns:32px minmax(0,1fr) auto; gap:5px 10px; min-height:0; padding:10px 14px; }
                .wtb__avatar { width:32px; height:32px; border-radius:9px; }
                .wtb__avatar svg { width:17px; height:17px; }
                .wtb__badge { display:none; }
                .wtb__details { grid-column:2/4; }
                .wtb__item > [data-wtb-slot], .wtb__item > .wtb__phones { justify-self:end; grid-column:3; grid-row:1; margin:0; }
                .wtb__phone { justify-content:center; min-height:36px; padding:7px 12px; }
                .wtb__phone-label { font-size:0; }
                .wtb__phone-label::after { content:"Телефон"; font-size:12px; }
                .wtb__phones { text-align:right; }
                .wtb__footer { align-items:flex-start; flex-direction:column; gap:8px; padding:9px 16px 11px; }
                .wtb__partner-cta { flex-wrap:wrap; gap:4px 8px; white-space:normal; }
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
            <a href="#citySelector" data-bs-toggle="offcanvas" class="wtb__city"
               aria-label="Изменить город. Сейчас выбран: {{ $wtbCityName }}" title="Изменить город">
                {!! $wtbIcon('pin') !!}
                <span class="wtb__city-name">{{ $wtbCityName }}</span>
                <span class="wtb__city-action">Изменить</span>
                <svg class="wtb__city-chevron" viewBox="0 0 8 12" aria-hidden="true"><path d="m2 1 4 5-4 5"/></svg>
            </a>
        @endif
    </div>
    @if($wtbBranches->isNotEmpty())
        <ul class="wtb__list">
            @foreach($wtbBranches as $branch)
                <li class="wtb__item">
                    <span class="wtb__avatar">{!! $wtbIcon('store') !!}</span>
                    <div class="wtb__identity">
                        <div class="wtb__name"><strong>{{ $branch->name }}</strong><span class="wtb__badge">{!! $wtbIcon('shield') !!}Партнёр KOTLOV</span></div>
                    </div>
                    <div class="wtb__details">
                        @if($branch->address)
                            <div class="wtb__meta">{!! $wtbIcon('pin') !!}<span>@if($branch->map_url)<a href="{{ $branch->map_url }}" target="_blank" rel="noopener">{{ $branch->address }}</a>@else{{ $branch->address }}@endif</span></div>
                        @endif
                        @if($branch->working_hours)<div class="wtb__meta">{!! $wtbIcon('clock') !!}<span>{{ $branch->working_hours }}</span></div>@endif
                        <div class="wtb__social">@include('partials.branch-social-contacts', ['contactBranch' => $branch])</div>
                    </div>
                    <div data-wtb-slot>
                        <button type="button" class="wtb__phone"
                            data-wtb-phone="{{ route('partner-branches.phone', $branch) }}"
                            data-product-id="{{ $product->id ?? '' }}"
                            aria-label="Показать телефон {{ $branch->name }}">{!! $wtbIcon('phone') !!}<span class="wtb__phone-label">Показать телефон</span></button>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <ul class="wtb__list">
            <li class="wtb__item">
                <span class="wtb__avatar">{!! $wtbIcon('truck') !!}</span>
                <div class="wtb__identity">
                    <div class="wtb__name"><strong>{{ $cityContact['company_name'] ?? 'KOTLOV.BY' }}</strong></div>
                </div>
                <div class="wtb__details">
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
    <div class="wtb__footer">
        @if($wtbBranches->isNotEmpty())
            <p class="wtb__note">{!! $wtbIcon('shield') !!}Официальные партнёры KOTLOV: цена и гарантия — как на сайте.</p>
        @endif
        <div class="wtb__partner-cta">
            <span>Хотите, чтобы ваш магазин появился здесь?</span>
            <a href="{{ route('partners') }}" class="wtb__partner-link">Стать партнёром</a>
        </div>
    </div>
</section>
@include('partials.partner-branch-phone-script')
