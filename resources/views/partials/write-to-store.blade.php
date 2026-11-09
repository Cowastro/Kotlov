@php
    $channels = \Illuminate\Support\Facades\Schema::hasTable('lead_channels') ? \App\Models\LeadChannel::query()
        ->whereIn('provider', array_keys(\App\Models\LeadChannel::LABELS))
        ->where('enabled', true)
        ->whereNotNull('access_token')
        ->where(fn ($query) => $query->where('provider', 'viber')->orWhereNotNull('webhook_secret'))
        ->whereNotNull('public_url')
        ->pluck('public_url', 'provider')
        ->filter(fn (string $url): bool => filled($url)) : collect();
@endphp

@if ($channels->isNotEmpty())
    @once
        <style>
            .store-contact-widget { background: #fff; color: #263342; border-radius: 6px; margin-block: 12px; padding: 14px 16px 10px; font-size: 13px; }
            .store-contact-widget__title { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; color: inherit; font-size: inherit; font-weight: 500; }
            .store-contact-widget__links { display: flex; flex-wrap: wrap; gap: 2px 20px; list-style: none; margin: 0; padding: 0; }
            .store-contact-widget__link { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; color: #263342; font-size: inherit; font-weight: 400; }
            .store-contact-widget__link:hover { color: #263342; text-decoration: underline; }
            .store-contact-widget__link:focus-visible { outline: 2px solid #263342; outline-offset: 3px; }
            .store-contact-widget__icon { width: 16px; height: 16px; flex: 0 0 16px; }
            .store-contact-widget__title .store-contact-widget__icon { color: #ef4444; }
            .store-contact-widget__link--telegram { --channel-icon: #139fd8; }
            .store-contact-widget__link--viber { --channel-icon: #8654cc; }
            .store-contact-widget__link--instagram { --channel-icon: #d3288f; }
            .store-contact-widget__link--whatsapp { --channel-icon: #12864f; }
            .store-contact-widget__link .store-contact-widget__icon { color: var(--channel-icon); }
        </style>
    @endonce

    <section class="store-contact-widget" aria-label="Написать в магазин · Минск">
        <p class="store-contact-widget__title">
            <svg class="store-contact-widget__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 4h16l2 5v2a2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1-5 0V9l2-5Zm0 10v6h16v-6m-10 6v-5h4v5M2 9h20"/>
            </svg>
            Написать в магазин · Минск
        </p>
        <ul class="store-contact-widget__links">
            @foreach (\App\Models\LeadChannel::LABELS as $provider => $label)
                @if ($channels->has($provider))
                    <li>
                        <a class="store-contact-widget__link store-contact-widget__link--{{ $provider }}" href="{{ $channels[$provider] }}" target="_blank" rel="noopener noreferrer">
                            <svg class="store-contact-widget__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @switch($provider)
                                    @case('telegram')
                                        <path fill="currentColor" stroke="none" d="m21.4 3.5-3.2 16c-.2 1.1-.9 1.4-1.8.9l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.2-8.3c.4-.4-.1-.6-.6-.3L5.7 13.2.8 11.7c-1.1-.3-1.1-1.1.2-1.6L20 2.8c.9-.3 1.6.2 1.4.7Z"/>
                                        @break
                                    @case('viber')
                                        <path d="M7 3h10a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4h-6l-5 3v-3a4 4 0 0 1-3-4V7a4 4 0 0 1 4-4Z"/>
                                        <path d="m8 7 2 3-1.5 1.5a10 10 0 0 0 4 4L14 14l3 2c-1 4-5 1-7-1S5 8 8 7Zm5-1a5 5 0 0 1 5 5m-5-2a2 2 0 0 1 2 2"/>
                                        @break
                                    @case('instagram')
                                        <rect x="3" y="3" width="18" height="18" rx="5"/>
                                        <circle cx="12" cy="12" r="4"/>
                                        <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
                                        @break
                                    @case('whatsapp')
                                        <path d="M20 12a8 8 0 0 1-12 7l-5 2 1.5-5A8 8 0 1 1 20 12Z"/>
                                        <path d="m8 7 2 3-1.5 1.5a10 10 0 0 0 4 4L14 14l3 2c-1 4-5 1-7-1S5 8 8 7Z"/>
                                        @break
                                @endswitch
                            </svg>
                            {{ $label }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </section>
@endif
