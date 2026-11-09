<nav class="order-queue-nav" aria-label="Очереди заказов">
    @foreach ($workTabs as $key)
        @php($tab = $tabs[$key])
        <x-filament::button
            size="sm"
            :color="$this->activeTab === $key ? 'primary' : 'gray'"
            :outlined="$this->activeTab !== $key"
            :icon="$tab->getIcon()"
            :aria-current="$this->activeTab === $key ? 'page' : 'false'"
            wire:click="selectOrderTab('{{ $key }}')"
        >
            {{ $tab->getLabel() }}
            @if ($tab->getBadge())
                <x-filament::badge :color="$tab->getBadgeColor() ?? 'gray'">{{ $tab->getBadge() }}</x-filament::badge>
            @endif
        </x-filament::button>
    @endforeach

    <div class="order-queue-nav__more">
        <x-filament::input.wrapper>
            <x-filament::input.select aria-label="Другие очереди и статусы" wire:key="order-queue-{{ $this->activeTab }}" wire:change="selectOrderTab($event.target.value)">
                <option value="" disabled @selected(in_array($this->activeTab, $workTabs, true))>Другие очереди и статусы</option>
                <optgroup label="Контроль и архив">
                    @foreach (['missing_price', 'missing_supplier', 'margin_risk', 'archived'] as $key)
                        <option value="{{ $key }}" @selected($this->activeTab === $key)>{{ $tabs[$key]->getLabel() }}{{ $tabs[$key]->getBadge() ? ' ('.$tabs[$key]->getBadge().')' : '' }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Статус заказа">
                    @foreach (array_keys(\App\Models\Order::STATUSES) as $key)
                        <option value="{{ $key }}" @selected($this->activeTab === $key)>{{ $tabs[$key]->getLabel() }}{{ $tabs[$key]->getBadge() ? ' ('.$tabs[$key]->getBadge().')' : '' }}</option>
                    @endforeach
                </optgroup>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
</nav>
