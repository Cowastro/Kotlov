<div class="crm-mail-queue">
    <nav class="crm-mail-queue__nav" aria-label="Разделы почты">
        @foreach ($workTabs as $key)
            @php($tab = $tabs[$key])
            <x-filament::button
                size="sm"
                :color="$this->activeTab === $key ? 'primary' : 'gray'"
                :outlined="$this->activeTab !== $key"
                :icon="$tab->getIcon()"
                :aria-current="$this->activeTab === $key ? 'page' : 'false'"
                wire:click="selectMailTab('{{ $key }}')"
            >
                {{ $tab->getLabel() }}
                @if ($key === 'unread')
                    <x-filament::badge :color="$tab->getBadgeColor()">{{ $tab->getBadge() ?? 0 }}</x-filament::badge>
                @elseif (in_array($key, ['needs_response', 'my_work'], true) && $tab->getBadge())
                    <x-filament::badge :color="$tab->getBadgeColor()">{{ $tab->getBadge() }}</x-filament::badge>
                @endif
            </x-filament::button>
        @endforeach

        <div class="crm-mail-queue__folders">
            <x-filament::input.wrapper>
                <x-filament::input.select aria-label="Папки и подборки" wire:key="mail-folder-{{ $this->activeTab }}" wire:change="selectMailTab($event.target.value)">
                    <option value="" disabled @selected(in_array($this->activeTab, $workTabs, true))>Папки и подборки</option>
                    @foreach (array_diff_key($tabs, array_flip($workTabs)) as $key => $tab)
                        <option value="{{ $key }}" @selected($this->activeTab === $key)>{{ $tab->getLabel() }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </nav>

    @if ($this->activeTab === 'needs_response')
        <p class="crm-mail-queue__hint">Новые переписки, письма без ответа и напоминания, срок которых наступил. Прочитанное письмо остаётся в очереди, пока его не обработают.</p>
    @endif

    <details class="crm-mail-queue__health">
        <summary>Состояние синхронизации</summary>
        <div>
            @livewire(\App\Filament\Widgets\CrmMailHealthOverview::class, key('mail-queue-health'))
        </div>
    </details>
</div>
