<x-filament-widgets::widget>
    <div wire:poll.30s class="crm-today">
        <style>
            .crm-today{display:grid;gap:16px}.crm-today *{box-sizing:border-box}.crm-today__hero{padding:22px;border:1px solid rgb(229 231 235);border-radius:18px;background:linear-gradient(135deg,#18181b 0%,#27272a 70%,#3f2b13 100%);color:#fff}.dark .crm-today__hero{border-color:rgb(63 63 70)}.crm-today__head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px}.crm-today__title{margin:0;font-size:22px;font-weight:750;line-height:1.2}.crm-today__subtitle{margin:6px 0 0;color:rgba(255,255,255,.68);font-size:13px}.crm-today__links{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:7px}.crm-today__link{padding:7px 10px;border:1px solid rgba(255,255,255,.16);border-radius:999px;color:#fff;font-size:12px;text-decoration:none}.crm-today__link:hover{background:rgba(255,255,255,.1)}.crm-today__stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:20px}.crm-today__stat{padding:14px;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(255,255,255,.06)}.crm-today__stat strong{display:block;font-size:26px;line-height:1}.crm-today__stat span{display:block;margin-top:7px;color:rgba(255,255,255,.68);font-size:12px}.crm-today__stat--danger strong{color:#fda4af}.crm-today__stat--warning strong{color:#fcd34d}.crm-today__channels{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}.crm-today__channel{padding:5px 9px;border-radius:999px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.76);font-size:11px}.crm-today__grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:16px}.crm-today__panel{overflow:hidden;border:1px solid rgb(229 231 235);border-radius:18px;background:#fff}.dark .crm-today__panel{border-color:rgb(63 63 70);background:rgb(24 24 27)}.crm-today__panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid rgb(229 231 235)}.dark .crm-today__panel-head{border-color:rgb(63 63 70)}.crm-today__panel-title{margin:0;font-size:16px;font-weight:700;color:rgb(24 24 27)}.dark .crm-today__panel-title{color:#fff}.crm-today__count{display:inline-grid;place-items:center;min-width:27px;height:27px;padding:0 8px;border-radius:999px;background:rgb(244 244 245);color:rgb(82 82 91);font-size:12px;font-weight:700}.dark .crm-today__count{background:rgb(39 39 42);color:rgb(212 212 216)}.crm-today__list{display:grid}.crm-today__item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;padding:13px 18px;border-bottom:1px solid rgb(244 244 245);color:inherit;text-decoration:none;transition:background .15s}.dark .crm-today__item{border-color:rgb(39 39 42)}.crm-today__item:last-child{border-bottom:0}.crm-today__item:hover{background:rgb(250 250 250)}.dark .crm-today__item:hover{background:rgb(39 39 42)}.crm-today__kind{display:inline-flex;width:max-content;margin-bottom:5px;padding:3px 7px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:10px;font-weight:700}.crm-today__kind--danger{background:#fff1f2;color:#be123c}.crm-today__kind--warning{background:#fffbeb;color:#a16207}.crm-today__kind--success{background:#ecfdf5;color:#047857}.crm-today__name{overflow:hidden;color:rgb(24 24 27);font-size:13px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.dark .crm-today__name{color:#fff}.crm-today__meta{overflow:hidden;margin-top:3px;color:rgb(113 113 122);font-size:11px;text-overflow:ellipsis;white-space:nowrap}.crm-today__side{text-align:right}.crm-today__time{display:block;color:rgb(82 82 91);font-size:12px;font-weight:700}.dark .crm-today__time{color:rgb(212 212 216)}.crm-today__responsible{display:block;margin-top:6px;color:rgb(113 113 122);font-size:10px}.crm-today__empty{padding:34px 20px;text-align:center}.crm-today__empty strong{display:block;color:#047857;font-size:14px}.crm-today__empty span{display:block;margin-top:5px;color:rgb(113 113 122);font-size:12px}@media(max-width:1023px){.crm-today__grid{grid-template-columns:1fr}}@media(max-width:767px){.crm-today__hero{padding:17px}.crm-today__head{display:grid}.crm-today__links{justify-content:flex-start}.crm-today__stats{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-today__stat strong{font-size:22px}.crm-today__item{padding:12px 14px}.crm-today__panel-head{padding:15px}.crm-today__subtitle{line-height:1.45}}
        </style>

        <section class="crm-today__hero">
            <div class="crm-today__head">
                <div>
                    <h2 class="crm-today__title">CRM сегодня</h2>
                    <p class="crm-today__subtitle">{{ $dateLabel }} · только обращения и действия менеджеров · обновлено {{ $updatedAt }}</p>
                </div>
                <nav class="crm-today__links" aria-label="Разделы CRM">
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

        <div class="crm-today__grid">
            <section class="crm-today__panel">
                <header class="crm-today__panel-head">
                    <h3 class="crm-today__panel-title">Не пропустить</h3>
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
                    <h3 class="crm-today__panel-title">Последние поступления</h3>
                    <span class="crm-today__count">{{ $summary['today'] }}</span>
                </header>
                <div class="crm-today__list">
                    @forelse ($todayItems as $item)
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
                            </div>
                        </a>
                    @empty
                        <div class="crm-today__empty">
                            <strong>Сегодня новых обращений ещё нет</strong>
                            <span>Здесь появятся звонки, заказы, заявки, письма и чаты.</span>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-widgets::widget>
