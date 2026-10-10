<x-filament-panels::page>
    <style>
        .onec-setup { display: grid; gap: 18px; }
        .onec-setup-card { border: 1px solid rgba(148, 163, 184, .2); border-radius: 14px; background: rgba(255,255,255,.025); overflow: hidden; }
        .onec-setup-head { display: flex; justify-content: space-between; gap: 18px; padding: 18px; border-bottom: 1px solid rgba(148, 163, 184, .16); }
        .onec-setup-title { font-size: 18px; font-weight: 700; }
        .onec-setup-muted { color: rgb(148, 163, 184); font-size: 13px; }
        .onec-setup-badge { align-self: flex-start; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .onec-setup-badge.ready { background: rgba(34,197,94,.16); color: rgb(74,222,128); }
        .onec-setup-badge.pending { background: rgba(245,158,11,.16); color: rgb(251,191,36); }
        .onec-setup-grid { display: grid; grid-template-columns: minmax(280px,.8fr) minmax(440px,1.2fr); gap: 18px; padding: 18px; }
        .onec-setup-section { display: grid; gap: 12px; align-content: start; }
        .onec-setup-label { color: rgb(203,213,225); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .onec-setup-endpoint { display: flex; align-items: center; gap: 10px; padding: 11px 12px; border-radius: 9px; background: rgba(15,23,42,.6); overflow-wrap: anywhere; font-family: ui-monospace, monospace; font-size: 13px; }
        .onec-setup-steps { margin: 0; padding-left: 20px; color: rgb(203,213,225); line-height: 1.6; }
        .onec-setup-checks { display: grid; gap: 8px; }
        .onec-setup-check { display: grid; grid-template-columns: 24px minmax(180px,.7fr) minmax(260px,1.3fr); gap: 10px; align-items: start; padding: 11px 12px; border-radius: 9px; background: rgba(255,255,255,.03); }
        .onec-setup-check-icon { font-weight: 900; color: rgb(251,191,36); }
        .onec-setup-check.success .onec-setup-check-icon { color: rgb(74,222,128); }
        .onec-setup-check.warning .onec-setup-check-icon { color: rgb(251,191,36); }
        .onec-setup-check.failed .onec-setup-check-icon { color: rgb(248,113,113); }
        .onec-setup-check.running .onec-setup-check-icon { color: rgb(96,165,250); }
        .onec-setup-check.disabled .onec-setup-check-icon { color: rgb(148,163,184); }
        .onec-setup-next { color: rgb(148,163,184); font-size: 13px; }
        .onec-setup-actions { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 18px 18px; }
        @media (max-width: 900px) {
            .onec-setup-grid { grid-template-columns: 1fr; }
            .onec-setup-check { grid-template-columns: 24px 1fr; }
            .onec-setup-next { grid-column: 2; }
        }
    </style>

    <div class="onec-setup">
        <x-filament::section>
            <x-slot name="heading">Как должен работать постоянный обмен</x-slot>
            <x-slot name="description">Обмен запускает регламентное задание в 1С. Сайт принимает каталог, цены, остатки и статусы, а 1С забирает новые заказы.</x-slot>
            <div class="onec-setup-muted">
                Безопасный режим уже действует: исторические заказы до даты включения интеграции не выдаются. Рекомендуемый цикл — заказы каждые 5 минут, цены и остатки каждые 10 минут, полный каталог ночью.
            </div>
        </x-filament::section>

        @forelse ($sources as $item)
            @php($source = $item['source'])
            <section class="onec-setup-card">
                <div class="onec-setup-head">
                    <div>
                        <div class="onec-setup-title">{{ $source->name }}</div>
                        <div class="onec-setup-muted">{{ $source->scheduleLabel() }} · {{ $source->pricingRuleLabel() }}</div>
                    </div>
                    <span class="onec-setup-badge {{ $item['ready'] ? 'ready' : 'pending' }}">
                        {{ $item['ready'] ? 'Обмен готов' : $item['completed'].' из '.$item['total'].' проверок' }}
                    </span>
                </div>

                <div class="onec-setup-grid">
                    <div class="onec-setup-section">
                        <div class="onec-setup-label">Адрес узла для 1С</div>
                        <div class="onec-setup-endpoint">{{ $item['endpoint'] }}</div>

                        <div class="onec-setup-label">Настройка в 1С</div>
                        <ol class="onec-setup-steps">
                            <li>Откройте существующий узел обмена с сайтом.</li>
                            <li>Укажите этот адрес, отдельного пользователя обмена и его пароль.</li>
                            <li>Включите выгрузку товаров, цен и остатков только склада «{{ data_get($source->settings, 'warehouse_label', 'Основной') }}».</li>
                            <li>Включите обмен заказами и регламентное выполнение каждые {{ $source->orderIntervalMinutes() }} минут.</li>
                            <li>Выполните один ручной цикл и проверьте результат справа.</li>
                        </ol>
                    </div>

                    <div class="onec-setup-section">
                        <div class="onec-setup-label">Контроль готовности</div>
                        <div class="onec-setup-checks">
                            @foreach ($item['checks'] as $check)
                                <div class="onec-setup-check {{ $check['status'] }}">
                                    <span class="onec-setup-check-icon">{{ $check['icon'] }}</span>
                                    <strong>{{ $check['label'] }}</strong>
                                    <span class="onec-setup-next">{{ $check['next_step'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if ($item['staged_products_count'] > 0)
                            <div class="onec-setup-muted">
                                Позиций в промежуточном каталоге: {{ number_format($item['staged_products_count'], 0, ',', ' ') }}
                                @if ($item['latest_staged_at'])
                                    · данные обновлялись {{ $item['latest_staged_at']->timezone('Europe/Minsk')->format('d.m.Y H:i:s') }}
                                @endif
                            </div>
                        @endif
                        @if ($item['latest_run'])
                            <div class="onec-setup-muted">
                                Последняя попытка: {{ $item['latest_run']->started_at?->timezone('Europe/Minsk')->format('d.m.Y H:i:s') }} ·
                                {{ $item['latest_run']->status === 'success' ? 'успешно' : ($item['latest_run']->status === 'failed' ? 'ошибка' : 'выполняется') }}
                            </div>
                        @else
                            <div class="onec-setup-muted">
                                @if ($item['staged_products_count'] > 0)
                                    Данные были получены до включения журнала или вне текущего узла. Выполните новый цикл из 1С — он появится здесь как контролируемый обмен.
                                @else
                                    Сайт ещё не зафиксировал ни одного цикла этого подключения.
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="onec-setup-actions">
                    <x-filament::button tag="a" :href="\App\Filament\Resources\IntegrationSources\IntegrationSourceResource::getUrl('edit', ['record' => $source])" color="gray">
                        Настройки источника
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$journalUrl" color="gray">
                        Открыть журнал обмена
                    </x-filament::button>
                </div>
            </section>
        @empty
            <x-filament::section>
                <x-slot name="heading">Источник CommerceML ещё не создан</x-slot>
                <a href="{{ $sourceListUrl }}" class="text-primary-500 font-semibold">Открыть источники интеграции</a>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
