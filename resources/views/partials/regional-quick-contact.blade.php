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
@if($quickContactBranch && ! $quickContactBranch->isOnlineOnly() && ($quickContactBranch->telegram_username || $quickContactBranch->viber_phone || $quickContactBranch->whatsapp_phone || $quickContactBranch->instagram_username))
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
