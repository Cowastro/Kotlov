@php
    $channels = \Illuminate\Support\Facades\Schema::hasTable('lead_channels') ? \App\Models\LeadChannel::query()
        ->where('enabled', true)
        ->whereNotNull('public_url')
        ->pluck('public_url', 'provider') : collect();
    $showFormLink = $showFormLink ?? true;
    $showSiteChat = $showSiteChat ?? false;
@endphp

@if ($channels->isNotEmpty() || $showFormLink || $showSiteChat)
    <div class="d-grid gap-8 mt-16">
        <p class="fw-medium mb-0"><i class="icon icon-Headset" aria-hidden="true"></i> Написать в магазин · Минск</p>
        <ul class="d-flex flex-wrap gap-12">
            @foreach (\App\Models\LeadChannel::LABELS as $provider => $label)
                @if (filled($channels[$provider] ?? null))
                    <li><a class="link text-reset" href="{{ $channels[$provider] }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a></li>
                @endif
            @endforeach
            @if ($showSiteChat)
                <li><button class="link text-reset" type="button" data-support-chat-open>Онлайн-чат</button></li>
            @endif
            @if ($showFormLink)
                <li><a class="link text-reset" href="{{ route('contacts') }}#write-to-store">Форма на сайте</a></li>
            @endif
        </ul>
    </div>
@endif
