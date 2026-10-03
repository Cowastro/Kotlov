@push('styles')
<style>
    .kotlov-hp-line-badge { display: inline-flex; align-items: center; gap: 7px; margin-bottom: 10px; padding: 7px 11px; border: 1px solid var(--primary); border-radius: 999px; background: var(--white); color: var(--primary); font-size: 12px; font-weight: 700; letter-spacing: .035em; text-transform: uppercase; }
    .kotlov-hp-line-badge::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ''; }
    .kotlov-hp-profile { padding: 34px 0 0; }
    .kotlov-hp-profile__panel { position: relative; overflow: hidden; padding: 34px; border-radius: 20px; background: var(--text); color: var(--white); }
    .kotlov-hp-profile__panel::after { position: absolute; top: -120px; right: -80px; width: 310px; height: 310px; border: 1px solid rgba(255,255,255,.12); border-radius: 50%; content: ''; }
    .kotlov-hp-profile__head { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(360px, .8fr); gap: 34px; align-items: end; }
    .kotlov-hp-profile__eyebrow { display: inline-flex; align-items: center; gap: 9px; margin-bottom: 12px; color: var(--primary); font-size: 12px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
    .kotlov-hp-profile__eyebrow::before { width: 24px; height: 2px; background: currentColor; content: ''; }
    .kotlov-hp-profile__title, .kotlov-hp-profile__lead { color: var(--white); }
    .kotlov-hp-profile__title { margin-bottom: 10px; }
    .kotlov-hp-profile__lead { max-width: 720px; opacity: .76; }
    .kotlov-hp-profile__specs { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .kotlov-hp-profile__spec { padding: 16px 18px; border: 1px solid rgba(255,255,255,.14); border-radius: 14px; background: rgba(255,255,255,.07); }
    .kotlov-hp-profile__spec span { display: block; margin-bottom: 5px; color: rgba(255,255,255,.58); font-size: 12px; }
    .kotlov-hp-profile__spec strong { display: block; color: var(--white); font-size: 16px; line-height: 1.35; }
    .kotlov-hp-profile__bottom { position: relative; z-index: 1; display: flex; align-items: flex-end; justify-content: space-between; gap: 28px; margin-top: 28px; padding-top: 26px; border-top: 1px solid rgba(255,255,255,.14); }
    .kotlov-hp-profile__uses { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 12px; }
    .kotlov-hp-profile__use { padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,.1); color: var(--white); font-size: 13px; }
    .kotlov-hp-profile__actions { display: flex; flex-shrink: 0; flex-wrap: wrap; gap: 10px; }
    .kotlov-hp-profile__actions .tf-btn { min-width: 210px; }
    .kotlov-hp-profile .kotlov-hp-profile__actions .tf-btn.kotlov-hp-profile__secondary,
    .kotlov-hp-profile .kotlov-hp-profile__actions .tf-btn.kotlov-hp-profile__secondary:visited {
        border: 1px solid rgba(255,255,255,.45);
        background: transparent;
        color: #fff;
    }
    .kotlov-hp-profile .kotlov-hp-profile__actions .tf-btn.kotlov-hp-profile__secondary:hover,
    .kotlov-hp-profile .kotlov-hp-profile__actions .tf-btn.kotlov-hp-profile__secondary:focus-visible {
        border-color: #fff;
        background: #fff;
        color: #111;
    }
    .kotlov-hp-profile__note { margin-top: 18px; color: rgba(255,255,255,.58); font-size: 12px; line-height: 1.5; }

    @media (max-width: 991px) {
        .kotlov-hp-profile__head { grid-template-columns: 1fr; }
        .kotlov-hp-profile__bottom { align-items: stretch; flex-direction: column; }
        .kotlov-hp-profile__actions { width: 100%; }
    }
    @media (max-width: 767px) {
        .kotlov-hp-profile { padding-top: 24px; }
        .kotlov-hp-profile__panel { padding: 25px 20px 22px; }
        .kotlov-hp-profile__head { gap: 24px; }
        .kotlov-hp-profile__specs { grid-template-columns: 1fr; }
        .kotlov-hp-profile__actions { flex-direction: column; }
        .kotlov-hp-profile__actions .tf-btn { width: 100%; min-width: 0; }
    }
</style>
@endpush

<section class="kotlov-hp-profile" aria-labelledby="kotlov-hp-profile-title">
    <div class="container">
        <div class="kotlov-hp-profile__panel">
            <div class="kotlov-hp-profile__head">
                <div>
                    <p class="kotlov-hp-profile__eyebrow">Наша линейка KOTLOV GE</p>
                    <h3 class="kotlov-hp-profile__title" id="kotlov-hp-profile-title">
                        {{ $profile['model'] ? 'Модель ' . $profile['model'] : 'Тепловой насос KOTLOV GE' }}
                    </h3>
                    <p class="kotlov-hp-profile__lead text-body-1 mb-0">{{ $profile['positioning'] }}</p>
                </div>

                <div class="kotlov-hp-profile__specs" aria-label="Ключевые характеристики модели">
                    @foreach ([
                        'Мощность' => $profile['power'],
                        'Хладагент' => $profile['refrigerant'],
                        'Электропитание' => $profile['power_supply'],
                        'Температура воды' => $profile['flow_temperature'],
                    ] as $label => $value)
                        @if ($value)
                            <div class="kotlov-hp-profile__spec">
                                <span>{{ $label }}</span>
                                <strong>{{ $value }}</strong>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="kotlov-hp-profile__bottom">
                <div>
                    <p class="fw-semibold text-white mb-0">Для каких задач рассматривают эту модель</p>
                    <div class="kotlov-hp-profile__uses">
                        @foreach ($profile['uses'] as $use)
                            <span class="kotlov-hp-profile__use">{{ $use }}</span>
                        @endforeach
                    </div>
                    <p class="kotlov-hp-profile__note mb-0">Окончательную мощность и схему подключения подтверждаем после расчёта теплопотерь и проверки существующей системы отопления.</p>
                </div>
                <div class="kotlov-hp-profile__actions">
                    <a href="#engineeringCalculation" data-bs-toggle="modal" class="tf-btn btn-primary"
                       data-analytics-event="kotlov_heat_pump_calculation_click">Рассчитать под мой дом</a>
                    <a href="{{ $profile['landing_url'] }}" class="tf-btn btn-white btn-stroke kotlov-hp-profile__secondary">
                        {{ $profile['landing_label'] }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
