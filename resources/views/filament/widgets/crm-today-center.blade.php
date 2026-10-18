<x-filament-widgets::widget>
    <div wire:poll.30s class="crm-today" x-data="{ filtersOpen: false, reminderOpen: false }">
        <style>
            .crm-today{display:grid;gap:16px}.crm-today *{box-sizing:border-box}.crm-today__hero{padding:22px;border:1px solid rgb(229 231 235);border-radius:18px;background:linear-gradient(135deg,#18181b 0%,#27272a 70%,#3f2b13 100%);color:#fff}.dark .crm-today__hero{border-color:rgb(63 63 70)}.crm-today__head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.crm-today__title{margin:0;font-size:22px;font-weight:750;line-height:1.2}.crm-today__subtitle{margin:6px 0 0;color:rgba(255,255,255,.68);font-size:13px}.crm-today__links{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:7px}.crm-today__link,.crm-today__manage{padding:7px 10px;border:1px solid rgba(255,255,255,.16);border-radius:999px;background:transparent;color:#fff;font-size:12px;text-decoration:none;cursor:pointer}.crm-today__link:hover,.crm-today__manage:hover{background:rgba(255,255,255,.1)}.crm-today__stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:20px}.crm-today__stat{padding:14px;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(255,255,255,.06)}.crm-today__stat strong{display:block;font-size:26px;line-height:1}.crm-today__stat span{display:block;margin-top:7px;color:rgba(255,255,255,.68);font-size:12px}.crm-today__stat--danger strong{color:#fda4af}.crm-today__stat--warning strong{color:#fcd34d}.crm-today__channels{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}.crm-today__channel{padding:5px 9px;border-radius:999px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.76);font-size:11px}.crm-today__filters{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:13px 14px;border:1px solid rgb(229 231 235);border-radius:14px;background:#fff}.dark .crm-today__filters{border-color:rgb(63 63 70);background:rgb(24 24 27)}.crm-today__filter label{display:block;margin:0 0 5px;color:rgb(113 113 122);font-size:10px;font-weight:700;text-transform:uppercase}.crm-today__filter select{width:100%;height:38px;padding:0 32px 0 10px;border:1px solid rgb(212 212 216);border-radius:9px;background:#fff;color:rgb(24 24 27);font-size:13px}.dark .crm-today__filter select{border-color:rgb(82 82 91);background:rgb(39 39 42);color:#fff}.crm-today__grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:16px}.crm-today__panel{overflow:hidden;border:1px solid rgb(229 231 235);border-radius:18px;background:#fff}.dark .crm-today__panel{border-color:rgb(63 63 70);background:rgb(24 24 27)}.crm-today__panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid rgb(229 231 235)}.dark .crm-today__panel-head{border-color:rgb(63 63 70)}.crm-today__panel-title{margin:0;font-size:16px;font-weight:700;color:rgb(24 24 27)}.dark .crm-today__panel-title{color:#fff}.crm-today__panel-label{display:block;margin-top:3px;color:rgb(113 113 122);font-size:11px}.crm-today__count{display:inline-grid;place-items:center;min-width:27px;height:27px;padding:0 8px;border-radius:999px;background:rgb(244 244 245);color:rgb(82 82 91);font-size:12px;font-weight:700}.dark .crm-today__count{background:rgb(39 39 42);color:rgb(212 212 216)}.crm-today__list{display:grid}.crm-today__item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;padding:13px 18px;border-bottom:1px solid rgb(244 244 245);color:inherit;text-decoration:none;transition:background .15s}.dark .crm-today__item{border-color:rgb(39 39 42)}.crm-today__item:last-child{border-bottom:0}.crm-today__item:hover{background:rgb(250 250 250)}.dark .crm-today__item:hover{background:rgb(39 39 42)}.crm-today__kind{display:inline-flex;width:max-content;margin-bottom:5px;padding:3px 7px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:10px;font-weight:700}.crm-today__kind--danger{background:#fff1f2;color:#be123c}.crm-today__kind--warning{background:#fffbeb;color:#a16207}.crm-today__kind--success{background:#ecfdf5;color:#047857}.crm-today__name{overflow:hidden;color:rgb(24 24 27);font-size:13px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.dark .crm-today__name{color:#fff}.crm-today__meta{overflow:hidden;margin-top:3px;color:rgb(113 113 122);font-size:11px;text-overflow:ellipsis;white-space:nowrap}.crm-today__side{text-align:right}.crm-today__time{display:block;color:rgb(82 82 91);font-size:12px;font-weight:700}.dark .crm-today__time{color:rgb(212 212 216)}.crm-today__status{display:inline-block;margin-top:6px;padding:2px 6px;border-radius:999px;background:#f4f4f5;color:#52525b;font-size:9px}.crm-today__status--open{background:#fff7ed;color:#c2410c}.crm-today__status--closed{background:#ecfdf5;color:#047857}.crm-today__responsible{display:block;margin-top:6px;color:rgb(113 113 122);font-size:10px}.crm-today__empty{padding:34px 20px;text-align:center}.crm-today__empty strong{display:block;color:#047857;font-size:14px}.crm-today__empty span{display:block;margin-top:5px;color:rgb(113 113 122);font-size:12px}@media(max-width:1023px){.crm-today__grid{grid-template-columns:1fr}}@media(max-width:767px){.crm-today__hero{padding:17px}.crm-today__head{display:grid}.crm-today__links{justify-content:flex-start}.crm-today__stats{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-today__stat strong{font-size:22px}.crm-today__filters{grid-template-columns:1fr}.crm-today__item{padding:12px 14px}.crm-today__panel-head{padding:15px}.crm-today__subtitle{line-height:1.45}}
            .crm-reminders{border:1px solid rgb(229 231 235);border-radius:18px;background:#fff;overflow:hidden}.dark .crm-reminders{border-color:rgb(63 63 70);background:rgb(24 24 27)}.crm-reminders__add{padding:8px 12px;border:0;border-radius:10px;background:#18181b;color:#fff;font-size:12px;font-weight:700;cursor:pointer}.dark .crm-reminders__add{background:#f4f4f5;color:#18181b}.crm-reminders__form{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1.6fr) minmax(180px,.7fr) minmax(180px,.7fr) auto;gap:9px;padding:13px 18px;border-bottom:1px solid rgb(229 231 235)}.dark .crm-reminders__form{border-color:rgb(63 63 70)}.crm-reminders__input{width:100%;min-height:40px;padding:8px 10px;border:1px solid rgb(212 212 216);border-radius:9px;background:#fff;color:#18181b;font-size:12px}.dark .crm-reminders__input{border-color:rgb(82 82 91);background:rgb(39 39 42);color:#fff}.crm-reminders__save{min-height:40px;padding:8px 14px;border:0;border-radius:9px;background:#ca8a04;color:#fff;font-size:12px;font-weight:700;cursor:pointer}.crm-reminders__list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0}.crm-reminders__item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:13px 18px;border-bottom:1px solid rgb(244 244 245)}.crm-reminders__item:nth-child(odd){border-right:1px solid rgb(244 244 245)}.dark .crm-reminders__item{border-color:rgb(39 39 42)}.crm-reminders__due{display:inline-block;margin-bottom:5px;color:#a16207;font-size:10px;font-weight:700}.crm-reminders__due--overdue{color:#be123c}.crm-reminders__done{align-self:center;padding:6px 9px;border:1px solid #bbf7d0;border-radius:8px;background:#f0fdf4;color:#047857;font-size:11px;font-weight:700;cursor:pointer}.crm-reminders__error{display:block;margin-top:4px;color:#dc2626;font-size:10px}@media(max-width:1100px){.crm-reminders__form{grid-template-columns:1fr 1fr}.crm-reminders__save{grid-column:1/-1}.crm-reminders__list{grid-template-columns:1fr}.crm-reminders__item:nth-child(odd){border-right:0}}@media(max-width:640px){.crm-reminders__form{grid-template-columns:1fr;padding:12px 14px}.crm-reminders__save{grid-column:auto}.crm-reminders__item{padding:12px 14px}}
            .crm-periods{display:flex;align-items:center;gap:7px;overflow-x:auto;padding:3px 1px 5px;scrollbar-width:thin}.crm-periods__button{flex:0 0 auto;padding:9px 14px;border:1px solid rgb(212 212 216);border-radius:999px;background:#fff;color:#52525b;font-size:12px;font-weight:700;cursor:pointer}.crm-periods__button:hover{border-color:#a1a1aa;background:#fafafa}.crm-periods__button--active{border-color:#ca8a04;background:#ca8a04;color:#fff}.dark .crm-periods__button{border-color:#3f3f46;background:#18181b;color:#d4d4d8}.dark .crm-periods__button--active{border-color:#eab308;background:#eab308;color:#18181b}.crm-today__filters{grid-template-columns:repeat(2,minmax(0,1fr))}@media(max-width:640px){.crm-periods__button{padding:8px 12px}.crm-today__filters{grid-template-columns:1fr}}
        </style>

        <section class="crm-today__hero">
            <div class="crm-today__head">
                <div>
                    <h2 class="crm-today__title">CRM сегодня</h2>
                    <p class="crm-today__subtitle">{{ $dateLabel }} · только обращения и действия менеджеров · обновлено {{ $updatedAt }}</p>
                </div>
                <nav class="crm-today__links" aria-label="Разделы CRM">
                    <button type="button" class="crm-today__manage" x-on:click="filtersOpen = ! filtersOpen" x-text="filtersOpen ? 'Скрыть' : 'Управление'"></button>
                    @foreach ($links as $link)
                        <a class="crm-today__link" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="crm-today__stats">
                <div class="crm-today__stat">
                    <strong>{{ $summary['today'] }}</strong>
                    <span>поступило сегодня</span>
                </div>
                <div class="crm-today__stat crm-today__stat--danger">
                    <strong>{{ $summary['attention'] }}</strong>
                    <span>требует реакции</span>
                </div>
                <div class="crm-today__stat crm-today__stat--warning">
                    <strong>{{ $summary['due'] }}</strong>
                    <span>контактов до конца дня</span>
                </div>
                <div class="crm-today__stat {{ $summary['overdue'] ? 'crm-today__stat--danger' : '' }}">
                    <strong>{{ $summary['overdue'] }}</strong>
                    <span>просрочено</span>
                </div>
            </div>

            <div class="crm-today__channels" aria-label="Поступления по каналам">
                <span class="crm-today__channel">Звонки {{ $todayCounts['calls'] }}</span>
                <span class="crm-today__channel">Заказы {{ $todayCounts['orders'] }}</span>
                <span class="crm-today__channel">Заявки {{ $todayCounts['requests'] }}</span>
                <span class="crm-today__channel">Монтаж {{ $todayCounts['installations'] }}</span>
                <span class="crm-today__channel">Почта {{ $todayCounts['mail'] }}</span>
                <span class="crm-today__channel">Чат {{ $todayCounts['chats'] }}</span>
            </div>
        </section>

        <nav class="crm-periods" aria-label="Период поступлений">
            @foreach ([
                'today' => 'Сегодня',
                'yesterday' => 'Вчера',
                'week' => 'Эта неделя',
                'month' => 'Этот месяц',
                'all' => 'Всё время',
            ] as $periodValue => $periodTitle)
                <button
                    type="button"
                    class="crm-periods__button {{ $period === $periodValue ? 'crm-periods__button--active' : '' }}"
                    wire:click="$set('period', '{{ $periodValue }}')"
                >{{ $periodTitle }}</button>
            @endforeach
        </nav>

        <section class="crm-today__filters" x-show="filtersOpen" x-collapse>
            <div class="crm-today__filter">
                <label for="crm-status">Состояние</label>
                <select id="crm-status" wire:model.live="status">
                    <option value="open">Только незакрытые</option>
                    <option value="closed">Только закрытые</option>
                    <option value="all">Все</option>
                </select>
            </div>
            <div class="crm-today__filter">
                <label for="crm-sort">Сортировка</label>
                <select id="crm-sort" wire:model.live="sortOrder">
                    <option value="desc">Сначала новые</option>
                    <option value="asc">Сначала старые</option>
                </select>
            </div>
        </section>

        <section class="crm-reminders">
            <header class="crm-today__panel-head">
                <div>
                    <h3 class="crm-today__panel-title">Напоминания</h3>
                    <span class="crm-today__panel-label">Быстрые заметки и задачи, которые нельзя потерять</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="crm-today__count">{{ $reminders->count() }}</span>
                    <button type="button" class="crm-reminders__add" x-on:click="reminderOpen = ! reminderOpen">+ Добавить</button>
                </div>
            </header>

            <form class="crm-reminders__form" x-show="reminderOpen" x-collapse wire:submit.prevent="saveQuickReminder">
                <div>
                    <input class="crm-reminders__input" type="text" wire:model="reminderTitle" placeholder="Что нужно сделать?">
                    @error('reminderTitle') <span class="crm-reminders__error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <input class="crm-reminders__input" type="text" wire:model="reminderNote" placeholder="Короткая заметка или комментарий">
                </div>
                <input class="crm-reminders__input" type="datetime-local" wire:model="reminderDueAt" aria-label="Срок напоминания">
                @if ($canAssignReminders)
                    <select class="crm-reminders__input" wire:model="reminderAssignedUserId" aria-label="Ответственный">
                        <option value="">Не назначен</option>
                        @foreach ($managerOptions as $managerId => $managerName)
                            <option value="{{ $managerId }}">{{ $managerName }}</option>
                        @endforeach
                    </select>
                @else
                    <div class="crm-reminders__input" style="display:flex;align-items:center;color:#71717a">Ответственный: вы</div>
                @endif
                <button class="crm-reminders__save" type="submit" wire:loading.attr="disabled" wire:target="saveQuickReminder">Сохранить</button>
            </form>

            <div class="crm-reminders__list">
                @forelse ($reminders as $reminder)
                    <article class="crm-reminders__item" wire:key="crm-reminder-{{ $reminder['id'] }}">
                        <div style="min-width:0">
                            @if ($reminder['due'])
                                <time class="crm-reminders__due {{ $reminder['overdue'] ? 'crm-reminders__due--overdue' : '' }}">
                                    {{ $reminder['overdue'] ? 'Просрочено · ' : 'До · ' }}{{ $reminder['due'] }}
                                </time>
                            @endif
                            <div class="crm-today__name">{{ $reminder['title'] }}</div>
                            @if ($reminder['note'])
                                <div class="crm-today__meta">{{ $reminder['note'] }}</div>
                            @endif
                            <div class="crm-today__meta">Ответственный: {{ $reminder['responsible'] ?: 'Не назначен' }} · создал: {{ $reminder['creator'] ?: '—' }}</div>
                        </div>
                        <button type="button" class="crm-reminders__done" wire:click="completeReminder({{ $reminder['id'] }})" wire:loading.attr="disabled">Выполнено</button>
                    </article>
                @empty
                    <div class="crm-today__empty" style="grid-column:1/-1">
                        <strong>Активных напоминаний нет</strong>
                        <span>Добавьте заметку со сроком и ответственным.</span>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="crm-today__grid">
            <section class="crm-today__panel">
                <header class="crm-today__panel-head">
                    <div>
                        <h3 class="crm-today__panel-title">Не пропустить</h3>
                        <span class="crm-today__panel-label">Все незакрытые, включая прошлые дни</span>
                    </div>
                    <span class="crm-today__count">{{ $summary['attention'] }}</span>
                </header>
                <div class="crm-today__list">
                    @forelse ($attentionItems as $item)
                        <a class="crm-today__item" href="{{ $item['url'] }}">
                            <div style="min-width:0">
                                <span class="crm-today__kind crm-today__kind--{{ $item['tone'] }}">{{ $item['kind'] }}</span>
                                <div class="crm-today__name">{{ $item['title'] }}</div>
                                @if ($item['subtitle'])
                                    <div class="crm-today__meta">{{ $item['subtitle'] }}</div>
                                @endif
                            </div>
                            <div class="crm-today__side">
                                <time class="crm-today__time">{{ $item['time'] }}</time>
                                <span class="crm-today__responsible">{{ $item['responsible'] ?: 'Не назначен' }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="crm-today__empty">
                            <strong>Срочных обращений нет</strong>
                            <span>Все звонки, заявки и сообщения разобраны.</span>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="crm-today__panel">
                <header class="crm-today__panel-head">
                    <div>
                        <h3 class="crm-today__panel-title">Поступления</h3>
                        <span class="crm-today__panel-label">{{ $rangeLabel }}</span>
                    </div>
                    <span class="crm-today__count">{{ $activityCount }}</span>
                </header>
                <div class="crm-today__list">
                    @forelse ($activityItems as $item)
                        <a class="crm-today__item" href="{{ $item['url'] }}">
                            <div style="min-width:0">
                                <span class="crm-today__kind crm-today__kind--{{ $item['tone'] }}">{{ $item['kind'] }}</span>
                                <div class="crm-today__name">{{ $item['title'] }}</div>
                                @if ($item['subtitle'])
                                    <div class="crm-today__meta">{{ $item['subtitle'] }}</div>
                                @endif
                            </div>
                            <div class="crm-today__side">
                                <time class="crm-today__time">{{ $item['time'] }}</time>
                                <span class="crm-today__status crm-today__status--{{ $item['closed'] ? 'closed' : 'open' }}">{{ $item['statusLabel'] ?: ($item['closed'] ? 'Закрыто' : 'Открыто') }}</span>
                                <span class="crm-today__responsible">{{ $item['responsible'] ?: 'Не назначен' }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="crm-today__empty">
                            <strong>По выбранным фильтрам ничего нет</strong>
                            <span>Измените период или состояние в кнопке «Управление».</span>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-widgets::widget>
