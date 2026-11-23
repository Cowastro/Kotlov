<x-filament-panels::page>
    <style>
        .reserve-desk { --rd-border:#e2e8f0; --rd-muted:#64748b; --rd-bg:#fff; display:grid; gap:16px; }
        .dark .reserve-desk { --rd-border:#33343a; --rd-muted:#a1a1aa; --rd-bg:#18181b; }
        .reserve-desk .rd-filters,.reserve-desk .rd-card { background:var(--rd-bg); border:1px solid var(--rd-border); border-radius:12px; padding:16px; min-width:0; }
        .rd-tabs { display:flex; flex-wrap:wrap; gap:8px; }
        .rd-filters { display:grid; grid-template-columns:minmax(200px,2fr) minmax(160px,1fr) minmax(160px,1fr) auto; gap:12px; align-items:end; }
        .rd-filters label { display:grid; gap:5px; font-size:12px; color:var(--rd-muted); min-width:0; }
        .rd-filters input,.rd-filters select { width:100%; min-width:0; height:38px; border:1px solid var(--rd-border); border-radius:8px; background:var(--rd-bg); color:inherit; font-size:14px; padding:6px 10px; }
        .rd-card-head,.rd-card-foot { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
        .rd-card-head h2 { font-size:15px; font-weight:650; overflow-wrap:anywhere; }
        .rd-muted { color:var(--rd-muted); font-size:12px; }
        .rd-main { display:grid; grid-template-columns:minmax(0,2fr) repeat(3,minmax(110px,1fr)); gap:16px; margin:14px 0; }
        .rd-main dt { font-size:11px; color:var(--rd-muted); margin-bottom:4px; }
        .rd-main dd { font-size:14px; overflow-wrap:anywhere; }
        .rd-next { padding:10px 12px; border-left:3px solid #f59e0b; background:rgba(245,158,11,.07); border-radius:6px; font-size:13px; }
        .rd-actions { display:flex; flex-wrap:wrap; gap:8px; }
        .rd-items { margin:12px 0; }
        .rd-items summary { cursor:pointer; font-size:13px; font-weight:600; }
        .rd-item { display:grid; grid-template-columns:minmax(0,3fr) minmax(80px,1fr) minmax(90px,1fr); gap:10px; padding:10px 0; border-bottom:1px solid var(--rd-border); font-size:13px; }
        .rd-empty { padding:40px 16px; text-align:center; border:1px dashed var(--rd-border); border-radius:12px; }
        @media(max-width:767px) { .rd-filters { grid-template-columns:1fr; } .rd-main { grid-template-columns:1fr 1fr; gap:12px; } .rd-main>div:first-child { grid-column:1/-1; } .rd-item { grid-template-columns:minmax(0,1fr) auto; } .rd-item>div:last-child { grid-column:1/-1; } .reserve-desk .rd-card { padding:12px; } }
    </style>
    <div class="reserve-desk">
        <p class="rd-muted">Запросы поставщикам и подтверждённые резервы. Письма бухгалтерии сюда не входят. Закрытая переписка не означает подтверждение резерва.</p>
        <nav class="rd-tabs" aria-label="Статусы резервов">
            @foreach (['active'=>'В работе','waiting'=>'Ждём подтверждения','confirmed'=>'Подтверждены','draft'=>'Черновики','rejected'=>'Отказы','archive'=>'Архив','all'=>'Все'] as $key=>$label)
                @php($count = match($key) { 'active'=>$counts->only(['draft','waiting','confirmed','rejected'])->sum(), 'archive'=>$counts->only(['fulfilled','cancelled','closed'])->sum(), 'all'=>$counts->sum(), default=>$counts[$key]??0 })
                <x-filament::button :color="$state === $key ? 'primary' : 'gray'" :outlined="$state !== $key" wire:click="$set('state', '{{ $key }}')" size="sm">{{ $label }} · {{ $count }}</x-filament::button>
            @endforeach
        </nav>
        <div class="rd-filters">
            <label>Товар, артикул, номер заказа или поставщик<input type="search" wire:model.live.debounce.500ms="search" placeholder="Найти резерв" maxlength="150"></label>
            <label>Поставщик<select wire:model.live="supplier"><option value="">Все поставщики</option>@foreach($suppliers as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            <label>Менеджер заявки<select wire:model.live="manager"><option value="">Все менеджеры</option>@foreach($managers as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            <x-filament::button color="gray" wire:click="resetFilters" size="sm">Сбросить</x-filament::button>
        </div>
        <div class="rd-muted" role="status">Найдено: {{ $pagination->total() }} · по 20 на странице</div>
        @forelse($rows as $row)
            <article class="rd-card" wire:key="{{ $row['key'] }}">
                <div class="rd-card-head">
                    <div><h2>{{ $row['supplier'] }}</h2><div class="rd-muted">{{ $row['number'] }} · {{ $row['updated']?->format('d.m.Y H:i') }}</div></div>
                    <div class="rd-actions"><x-filament::badge :color="$row['color']">{{ $row['label'] }}</x-filament::badge>@if($row['unread'])<x-filament::badge color="info">Новых ответов: {{ $row['unread'] }}</x-filament::badge>@endif</div>
                </div>
                <dl class="rd-main">
                    <div><dt>Товары · {{ count($row['items']) }} поз.</dt><dd>{{ $row['items'][0]['name'] ?? 'Состав не указан' }}@if(count($row['items'])>1)<span class="rd-muted"> и ещё {{ count($row['items'])-1 }}</span>@endif</dd></div>
                    <div><dt>Количество / закупка</dt><dd>{{ $row['quantity'] !== null ? number_format($row['quantity'], 0, ',', ' ') . ' шт.' : 'Количество не указано' }}<br><strong>{{ $row['total'] !== null ? number_format($row['total'], 2, ',', ' ') . ' BYN' : 'Цена не указана' }}</strong></dd></div>
                    <div><dt>Заказ / менеджер заявки</dt><dd>{{ $row['order_number'] ?? 'Без заказа' }}<br><span class="rd-muted">{{ $row['manager'] }}</span></dd></div>
                    <div><dt>Следующий контроль</dt><dd>@if($row['control_at']){{ $row['control_at']->format('d.m.Y H:i') }}@if($row['overdue'])<x-filament::badge color="danger">Просрочен</x-filament::badge>@endif @else Не назначен @endif<br><span class="rd-muted">Срок резерва не указан</span></dd></div>
                </dl>
                <p class="rd-next">{{ $row['next'] }}</p>
                <details class="rd-items"><summary>Состав и условия</summary>
                    @foreach($row['items'] as $item)<div class="rd-item"><div>{{ $item['name'] }}<div class="rd-muted">{{ $item['sku'] ?? 'Артикул не указан' }}</div></div><div>{{ $item['quantity'] ?? '—' }} шт.</div><div>{{ $item['price'] !== null ? number_format($item['price'],2,',',' ').' BYN / шт.' : 'Цена не указана' }}</div></div>@endforeach
                    @if($row['note'])<p style="margin-top:10px;font-size:13px;white-space:pre-line">{{ $row['note'] }}</p>@endif
                </details>
                <div class="rd-card-foot"><div class="rd-actions">
                    @if($row['can_respond'])<x-filament::button wire:click="mountAction('recordResponse', { request: {{ $row['request_id'] }} })" size="sm">Ответ поставщика</x-filament::button>@endif
                    @if($row['order_url'])<x-filament::button tag="a" :href="$row['order_url']" color="gray" size="sm">Открыть заказ</x-filament::button>@endif
                    @if($row['mail_url'])<x-filament::button tag="a" :href="$row['mail_url']" color="gray" size="sm">{{ $row['state']==='draft' ? 'Открыть запрос' : 'Переписка и ответ' }}</x-filament::button>@endif
                </div><span class="rd-muted">Подтверждение — по ответу поставщика</span></div>
            </article>
        @empty
            <div class="rd-empty"><strong>Резервов в этой группе нет</strong><p class="rd-muted" style="margin-top:8px">Выберите другой статус или сбросьте фильтры. Новый запрос создаётся из заказа или карточки товара.</p></div>
        @endforelse
        {{ $pagination->links() }}
    </div>
</x-filament-panels::page>
