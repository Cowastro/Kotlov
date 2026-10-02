<section class="installer-recruitment-note" aria-label="Предложение для монтажников">
    <div class="container">
        <div class="installer-recruitment-note__inner">
            <div>
                <span class="installer-recruitment-note__eyebrow">Для специалистов {{ $category }}</span>
                <p class="installer-recruitment-note__title">Занимаетесь {{ $service }}?</p>
                <p class="installer-recruitment-note__text">Получайте заявки в своём регионе и партнёрские цены на оборудование. Размещение профиля бесплатное, комиссии за заказ нет.</p>
            </div>
            <div class="installer-recruitment-note__action">
                <span>Первым 30 специалистам — приоритет на 90 дней</span>
                <a href="{{ route('become-installer', ['ref' => 'category']) }}#apply" class="tf-btn animate-btn">Получать заявки</a>
            </div>
        </div>
    </div>
</section>

@once
    @push('styles')
        <style>
            .installer-recruitment-note{padding:22px 0 0}.installer-recruitment-note__inner{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:28px;padding:22px 26px;border:1px solid #eadfce;border-radius:16px;background:linear-gradient(110deg,#fffaf2,#fff)}
            .installer-recruitment-note__eyebrow{display:block;margin-bottom:5px;color:#a45b00;font-size:11px;font-weight:750;letter-spacing:.07em;text-transform:uppercase}.installer-recruitment-note__title{margin:0 0 5px;font-size:20px;font-weight:750;color:#171717}.installer-recruitment-note__text{max-width:760px;margin:0;color:#626262;font-size:13px;line-height:1.55}
            .installer-recruitment-note__action{display:flex;align-items:center;gap:18px}.installer-recruitment-note__action span{max-width:180px;color:#69420c;font-size:12px;font-weight:650;line-height:1.4}.installer-recruitment-note__action .tf-btn{white-space:nowrap}
            @media(max-width:767px){.installer-recruitment-note__inner{grid-template-columns:1fr;padding:20px}.installer-recruitment-note__action{align-items:flex-start;flex-direction:column;gap:12px}.installer-recruitment-note__action span{max-width:none}.installer-recruitment-note__action .tf-btn{width:100%}}
        </style>
    @endpush
@endonce
