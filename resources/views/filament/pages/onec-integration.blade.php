<x-filament-panels::page>
    @php
        $labels = ['healthy' => 'Работает', 'failed' => 'Ошибка загрузки', 'running' => 'Выполняется', 'unknown' => 'Ещё не поступал', 'stale' => 'Данные устарели', 'disabled' => 'Отключён'];
        $when = fn ($date) => $date ? \Carbon\Carbon::parse($date)->timezone('Europe/Minsk')->format('d.m.Y H:i') : 'ещё не было';
    @endphp
    <style>
        .onec-center{display:grid;gap:20px;--oc-line:rgba(148,163,184,.25)}
        .onec-center a{color:inherit}.onec-center__muted{opacity:.7;font-size:13px;line-height:1.6}
        .onec-center__shortcuts,.onec-center__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .onec-center__shortcut,.onec-center__card{min-width:0;border:1px solid var(--oc-line);border-radius:14px;padding:20px;overflow-wrap:anywhere}
        .onec-center__shortcut{display:flex;align-items:center;justify-content:space-between;gap:12px;background:rgba(245,158,11,.08);min-height:80px}
        .onec-center h2{font-size:18px;font-weight:650;line-height:1.3;margin-bottom:8px}.onec-center h3{font-size:14px;font-weight:650}
        .onec-center__eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.65;margin-bottom:6px}
        .onec-center__row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--oc-line)}
        .onec-center__row:last-child{border-bottom:0}.onec-center__badge{display:inline-block;border-radius:6px;padding:4px 8px;font-size:12px;line-height:1.4;flex-shrink:0;background:rgba(148,163,184,.13)}
        .onec-center__badge.healthy{background:rgba(34,197,94,.15);color:#16a34a}.dark .onec-center__badge.healthy{color:#86efac}
        .onec-center__badge.failed,.onec-center__badge.stale{background:rgba(245,158,11,.14);color:#b45309}.dark .onec-center__badge.failed,.dark .onec-center__badge.stale{color:#fcd34d}
        .onec-center__link{display:inline-flex;align-items:center;min-height:44px;margin-top:8px;text-decoration:underline;text-underline-offset:3px;font-size:13px}
        .onec-center__stats{display:flex;flex-wrap:wrap;gap:10px 24px;margin:12px 0}.onec-center__stats strong{font-size:20px;display:block}
        .onec-center details{border:1px solid var(--oc-line);border-radius:12px;padding:16px}.onec-center summary{cursor:pointer;font-weight:600}.onec-center details p{margin-top:8px}
        .onec-center a:focus-visible,.onec-center summary:focus-visible{outline:2px solid #d97706;outline-offset:4px}
        @media(max-width:767px){.onec-center__shortcuts,.onec-center__grid{grid-template-columns:minmax(0,1fr)}.onec-center__shortcut,.onec-center__card{padding:16px}.onec-center__row{flex-wrap:wrap}.onec-center h2{font-size:17px}}
    </style>
    <div class="onec-center">
        <p class="onec-center__muted">Файловый обмен получает каталог, а мост выполняет задачи на сервере 1С. Ниже — состояние каждого канала отдельно. Время указано по Минску.</p>
        <nav class="onec-center__shortcuts" aria-label="Работа с 1С">
            <a class="onec-center__shortcut" href="{{ $itemsUrl }}"><div><h2>Номенклатура и привязки</h2><p class="onec-center__muted">Товары, характеристики и карточки сайта</p></div><span aria-hidden="true">→</span></a>
            <a class="onec-center__shortcut" href="{{ $ordersUrl }}"><div><h2>Заказы 1С</h2><p class="onec-center__muted">Заказы, связанные с документами 1С</p></div><span aria-hidden="true">→</span></a>
        </nav>
        <div class="onec-center__grid">
            <section class="onec-center__card" aria-label="Каталог — файловый обмен">
                <div class="onec-center__eyebrow">1С → сайт · файловый обмен</div><h2>Каталог, цены и остатки</h2>
                @forelse ($overview['files'] as $file)
                    <div class="onec-center__row"><div><h3>{{ $file['name'] }}</h3><p class="onec-center__muted">Успешная загрузка: {{ $when($file['last_success']) }}</p></div><span class="onec-center__badge {{ $file['status'] }}">{{ $labels[$file['status']] }}</span></div>
                @empty
                    <p class="onec-center__muted">Файловые источники ещё не настроены.</p>
                @endforelse
                <p class="onec-center__muted">Этот статус относится только к загрузке каталога. Он не определяет состояние заказов и моста.</p>
            </section>
            <section class="onec-center__card" aria-label="Агент моста 1С">
                <div class="onec-center__eyebrow">Сайт ↔ агент на сервере 1С</div><h2>Мост 1С</h2>
                <span class="onec-center__badge {{ $overview['agent']['online'] ? 'healthy' : 'stale' }}">{{ $overview['agent']['online'] ? 'Агент на связи' : ($overview['agent']['seen_at'] ? 'Нет свежего соединения' : 'Агент ещё не подключался') }}</span>
                <p class="onec-center__muted">Последнее соединение: {{ $when($overview['agent']['seen_at']) }}</p>
                <div class="onec-center__stats"><div><strong>{{ $overview['agent']['queued'] }}</strong><span class="onec-center__muted">ожидают / выполняются</span></div><div><strong>{{ $overview['agent']['failed'] }}</strong><span class="onec-center__muted">задач с ошибкой</span></div></div>
                @if ($overview['agent']['outdated'])<p class="onec-center__muted">Версию агента нужно обновить. Подробности — в задачах моста.</p>@endif
                <a class="onec-center__link" href="{{ $bridgeUrl }}">Задачи и ответы 1С →</a>
            </section>
            <section class="onec-center__card" aria-label="Справочник и привязки">
                <div class="onec-center__eyebrow">Справочник 1С → карточки сайта</div><h2>Номенклатура и характеристики</h2>
                <div class="onec-center__stats"><div><strong>{{ $overview['items']['total'] }}</strong><span class="onec-center__muted">товаров 1С</span></div><div><strong>{{ $overview['items']['linked'] }}</strong><span class="onec-center__muted">с привязками</span></div><div><strong>{{ $overview['items']['conflicts'] }}</strong><span class="onec-center__muted">конфликтов</span></div></div>
                <p class="onec-center__muted">Справочник прочитан: {{ $when($overview['items']['catalog_at']) }}.<br>Связи обновляются сразу после сохранения. Каждая характеристика привязывается отдельно.</p>
                <a class="onec-center__link" href="{{ $itemsUrl }}">Проверить и привязать товары →</a>
            </section>
            <section class="onec-center__card" aria-label="Остатки основного склада">
                <div class="onec-center__eyebrow">Основной склад · данные моста</div><h2>Остатки и резервы</h2>
                <p>Последнее чтение: <strong>{{ $when($overview['items']['stock_at']) }}</strong></p>
                <p class="onec-center__muted">По расписанию — каждые 30 минут, с 07:00 до 22:00 по Минску. Свободный остаток учитывает резерв. Остаток родительского товара не считается остатком каждого цвета или размера.</p>
                <a class="onec-center__link" href="{{ $itemsUrl }}">Посмотреть складские остатки →</a>
            </section>
        </div>
        <section class="onec-center__card" aria-label="Заказы и документы 1С">
            <h2>Заказы и документы 1С</h2><p class="onec-center__muted">Выполнение задачи и состояние конкретного заказа — разные вещи. Документы и их статусы смотрите в заказе.</p>
            @forelse ($overview['tasks'] as $task)
                <div class="onec-center__row"><div><h3>{{ $task['title'] }}</h3><p class="onec-center__muted">{{ $when($task['finished_at'] ?: $task['created_at']) }}</p></div><span class="onec-center__badge">{{ \App\Models\OneCAgentTask::STATUSES[$task['status']] ?? 'Неизвестный статус' }}</span></div>
            @empty
                <p class="onec-center__muted">Задач по документам ещё нет. Откройте заказ, чтобы проверить его связь с 1С.</p>
            @endforelse
            <a class="onec-center__link" href="{{ $ordersUrl }}">Открыть заказы 1С →</a> · <a class="onec-center__link" href="{{ $bridgeUrl }}">Все задачи моста →</a>
        </section>
        @if ($fileSetupUrl)
            <details><summary>Технические настройки для администратора</summary><p class="onec-center__muted">Файловый обмен продолжает работать отдельно от агента. Проверки на его странице относятся только к этому способу подключения.</p><a class="onec-center__link" href="{{ $fileSetupUrl }}">Файловый обмен 1С →</a></details>
        @endif
    </div>
</x-filament-panels::page>
