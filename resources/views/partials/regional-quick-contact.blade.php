@php
    $quickContactCity = $currentCity ?? $selectedCity ?? null;
    // Direct contact with approved pilot stores; never use another city's contacts.
    $quickContactBranch = null;
    if (in_array(($quickContactCity ?? null)?->slug, ['lida', 'minsk'], true)
        && ($quickContactCity->slug === 'minsk' || $quickContactCity->storefront_enabled)
        && ! request()->is('b2b*', 'account*', 'cart*', 'checkout*', 'login', 'register', 'email*')) {
        $quickContactBranch = \App\Models\PartnerBranch::query()->visibleIn($quickContactCity)
            ->when($quickContactCity->slug === 'minsk', fn ($query) => $query->whereHas('client', fn ($client) => $client->where('unp', '193265863')))->first();
    }
@endphp
@if($quickContactBranch && ! $quickContactBranch->isOnlineOnly() && $quickContactCity->slug === 'minsk')
    {{-- Desktop: the contact card in the corner. Phone: a small round chat button; the card opens on tap. --}}
    <style>
        .regional-store-contact .store-contact-widget{margin:0;border:1px solid #e2e6e9;border-radius:14px;box-shadow:0 6px 24px #18232c20;padding:10px 12px 4px}
        .regional-store-contact .store-contact-widget__link{min-height:40px}
        .regional-store-contact__fab,.regional-store-contact__close{display:none}
        body.modal-open .regional-store-contact,body.mobile-catalog-menu-is-open .regional-store-contact{visibility:hidden}
        .ai-selector__launcher,.ai-selector__teaser{display:none!important}

        @media(max-width:767px){
            body:has(.regional-store-contact) #goTop{bottom:calc(150px + env(safe-area-inset-bottom,0px))}
            .regional-store-contact__fab{position:relative;display:flex;align-items:center;justify-content:center;width:52px;height:52px;padding:0;border:0;border-radius:50%;background:#263342;color:#fff;box-shadow:0 8px 22px rgba(24,35,44,.32);cursor:pointer}
            .regional-store-contact__fab svg{width:24px;height:24px}
            .regional-store-contact__fab:after{position:absolute;top:3px;right:3px;width:11px;height:11px;border:2px solid #fff;border-radius:50%;background:#22c55e;content:""}
            .regional-store-contact__panel{position:absolute;right:0;bottom:62px;width:min(340px,calc(100vw - 24px));opacity:0;transform:translateY(10px) scale(.97);transform-origin:right bottom;pointer-events:none;transition:opacity .18s ease,transform .18s ease}
            .regional-store-contact.is-open .regional-store-contact__panel{opacity:1;transform:none;pointer-events:auto}
            .regional-store-contact.is-open .regional-store-contact__fab{background:#fff;color:#263342}
            .regional-store-contact.is-open .regional-store-contact__fab:after{display:none}
            .regional-store-contact .store-contact-widget{position:relative;padding:14px 14px 6px}
            .regional-store-contact__close{position:absolute;top:6px;right:6px;z-index:1;display:flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border:0;border-radius:50%;background:transparent;color:#6b7280;font-size:22px;line-height:1}
        }
    </style>
    <aside class="regional-store-contact" aria-label="Быстрая связь с магазином" data-store-contact>
        <div class="regional-store-contact__panel" id="regionalStoreContactPanel">
            <button type="button" class="regional-store-contact__close" data-store-contact-close aria-label="Закрыть">×</button>
            @include('partials.write-to-store')
        </div>
        <button type="button" class="regional-store-contact__fab" data-store-contact-toggle aria-controls="regionalStoreContactPanel" aria-expanded="false" aria-label="Написать в магазин">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a7 7 0 0 1-7 7H9l-5 3v-6a7 7 0 0 1 5-11h4a7 7 0 0 1 7 7Z"/><path d="M8.5 10h7M8.5 13.5h4.5"/></svg>
        </button>
    </aside>
    <script>
        (function () {
            var box = document.querySelector('[data-store-contact]');
            if (!box) return;
            var toggle = box.querySelector('[data-store-contact-toggle]');
            var set = function (open) { box.classList.toggle('is-open', open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); };
            toggle.addEventListener('click', function (event) { event.stopPropagation(); set(!box.classList.contains('is-open')); });
            box.querySelector('[data-store-contact-close]').addEventListener('click', function () { set(false); });
            box.querySelectorAll('[data-support-chat-open],[data-consultant-open],.store-contact-widget__link').forEach(function (el) { el.addEventListener('click', function () { set(false); }); });
            document.addEventListener('click', function (event) { if (!box.contains(event.target)) set(false); });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') set(false); });
        })();
    </script>
@elseif($quickContactBranch && ! $quickContactBranch->isOnlineOnly() && ($quickContactBranch->telegram_username || $quickContactBranch->viber_phone || $quickContactBranch->whatsapp_phone || $quickContactBranch->instagram_username))
@include('partials.branch-social-styles')
<style>
.regional-quick-contact{position:fixed;z-index:201;left:20px;right:auto;bottom:24px;width:max-content;max-width:calc(100vw - 40px);padding:12px 14px;border:1px solid #e2e6e9;border-radius:16px;background:#fff;box-shadow:0 6px 28px #18232c24}.regional-quick-contact__heading{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;line-height:1.3;font-weight:600;color:#20262b}.regional-quick-contact__heading svg{width:17px;height:17px;flex:none;color:#e04747}.regional-quick-contact .branch-social{margin:0}.regional-quick-contact .branch-social__title,.regional-quick-contact small{display:none}.regional-quick-contact .branch-social__links{display:flex;flex-wrap:nowrap;gap:7px}.regional-quick-contact a.branch-social__link{min-height:42px;padding:6px 9px;gap:6px;width:auto;flex:none;white-space:nowrap}.regional-quick-contact .branch-social__icon{width:25px;height:25px;flex-basis:25px;border-radius:7px}.regional-quick-contact .branch-social__icon svg{width:18px;height:18px}.regional-quick-contact strong{font-size:12px}.ai-selector__launcher,.ai-selector__teaser{display:none!important}body.modal-open .regional-quick-contact,body.mobile-catalog-menu-is-open .regional-quick-contact{display:none}
.regional-quick-contact__short{display:none}
@media(max-width:767px){.regional-quick-contact{display:flex;align-items:center;gap:10px;left:12px;right:auto;bottom:76px;max-width:calc(100% - 24px);padding:4px 8px 4px 12px;border-radius:14px}.regional-quick-contact__heading{display:block;flex:none;font-size:11px;line-height:1.3;margin:0}.regional-quick-contact__heading svg,.regional-quick-contact__long{display:none}.regional-quick-contact__short{display:block}.regional-quick-contact .branch-social__links{display:flex;gap:3px}.regional-quick-contact a.branch-social__link{justify-content:center;min-height:44px;width:44px;padding:0;border:0;background:transparent}.regional-quick-contact .branch-social__link>span:last-child{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}.regional-quick-contact .branch-social__icon{width:32px;height:32px;flex-basis:32px;border-radius:9px}.regional-quick-contact .branch-social__icon svg{width:21px;height:21px}.product-detail-page .regional-quick-contact{bottom:148px}body{padding-bottom:140px}}
</style>
<aside class="regional-quick-contact" aria-label="Быстрая связь с магазином {{ $quickContactCity->name_in }}">
    <p class="regional-quick-contact__heading"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10V5h18v5M5 10v11h14V10M2 10h20M9 21v-7h6v7"/></svg><span class="regional-quick-contact__long">Написать в магазин · {{ $quickContactCity->name }}</span><span class="regional-quick-contact__short">Магазин<br>{{ $quickContactCity->name_in }}</span></p>
    @include('partials.branch-social-contacts', ['contactBranch' => $quickContactBranch])
</aside>
@endif
