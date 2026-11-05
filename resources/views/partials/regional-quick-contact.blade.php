@php
    // Pilot: direct contact with the approved Lida store on its retail storefront only.
    $quickContactBranch = null;
    if (($currentCity ?? null)?->slug === 'lida' && $currentCity->storefront_enabled
        && ! request()->is('b2b*', 'account*', 'cart*', 'checkout*', 'login', 'register', 'email*')) {
        $quickContactBranch = \App\Models\PartnerBranch::query()->visibleIn($currentCity)->first();
    }
@endphp
@if($quickContactBranch && ($quickContactBranch->telegram_username || $quickContactBranch->viber_phone || $quickContactBranch->instagram_username))
@include('partials.branch-social-styles')
@push('styles')
<style>
.regional-quick-contact{position:fixed;z-index:201;right:20px;bottom:24px;max-width:350px;padding:12px 14px;border:1px solid #e2e6e9;border-radius:16px;background:#fff;box-shadow:0 6px 28px #18232c24}.regional-quick-contact__heading{display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:13px;line-height:1.3;font-weight:600;color:#20262b}.regional-quick-contact__heading svg{width:17px;height:17px;flex:none;color:#e04747}.regional-quick-contact .branch-social{margin:0}.regional-quick-contact .branch-social__title,.regional-quick-contact small{display:none}.regional-quick-contact .branch-social__links{display:flex;gap:7px}.regional-quick-contact a.branch-social__link{min-height:42px;padding:6px 9px;gap:6px;width:auto}.regional-quick-contact .branch-social__icon{width:25px;height:25px;flex-basis:25px;border-radius:7px}.regional-quick-contact .branch-social__icon svg{width:18px;height:18px}.regional-quick-contact strong{font-size:12px}.ai-selector__launcher,.ai-selector__teaser{display:none!important}body.modal-open .regional-quick-contact,body.mobile-catalog-menu-is-open .regional-quick-contact{display:none}
@media(max-width:767px){.regional-quick-contact{left:12px;right:12px;bottom:76px;max-width:none;padding:9px 12px;border-radius:14px}.regional-quick-contact__heading{font-size:12px;margin-bottom:6px}.regional-quick-contact .branch-social__links{display:flex;gap:6px}.regional-quick-contact a.branch-social__link{flex:1;justify-content:center;min-height:40px;padding:5px 6px}.product-detail-page .regional-quick-contact{bottom:148px}body{padding-bottom:170px}}
</style>
@endpush
<aside class="regional-quick-contact" aria-label="Быстрая связь с магазином в Лиде">
    <p class="regional-quick-contact__heading"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10V5h18v5M5 10v11h14V10M2 10h20M9 21v-7h6v7"/></svg>Написать в магазин · Лида</p>
    @include('partials.branch-social-contacts', ['contactBranch' => $quickContactBranch])
</aside>
@endif
