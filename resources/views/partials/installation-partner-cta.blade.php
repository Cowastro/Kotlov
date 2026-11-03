@once
    @push('styles')
        <style>
            .installation-partner-card { padding: clamp(24px, 5vw, 64px); border-radius: 20px; background: #151619; color: #fff; }
            .installation-partner-card h2 { color: inherit; font-size: clamp(26px, 4vw, 44px); line-height: 1.12; }
            .installation-partner-card p { max-width: 58ch; color: #d2d3d6; }
            .installation-partner-kicker { display: flex; align-items: center; gap: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
            .installation-partner-kicker::before { content: ''; width: 24px; height: 2px; background: #ef4b4b; }
            .installation-partner-card .tf-btn { justify-content: center; white-space: normal; text-align: center; height: auto; padding-block: 12px; }
            .installation-partner-card .installation-partner-secondary { color: #fff; border: 1px solid #a6a7ab; background: transparent; }
            .installation-partner-card .installation-partner-secondary:hover,
            .installation-partner-card .installation-partner-secondary:focus-visible { color: #151619; background: #fff; }
        </style>
    @endpush
@endonce
<section class="flat-spacing-2">
    <div class="container">
        <div class="installation-partner-card">
            <span class="installation-partner-kicker text-caption-01 mb-12">Для монтажников</span>
            <h2 class="mb-12">Вы монтажная организация?</h2>
            <p class="mb-16">Подключитесь к KOTLOV, чтобы комплектовать объекты и развивать профиль монтажника.</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="tf-btn btn-white animate-btn animate-dark" href="/become-installer#apply">Стать партнёром-монтажником</a>
                <a class="tf-btn installation-partner-secondary" href="/partners">Для бизнеса</a>
            </div>
        </div>
    </div>
</section>
