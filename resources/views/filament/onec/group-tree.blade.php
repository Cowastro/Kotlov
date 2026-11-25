<x-filament::section collapsible>
    <x-slot name="heading">Дерево групп 1С</x-slot>
    <x-slot name="description">Выберите папку, чтобы увидеть её товары или создать внутри новую группу.</x-slot>
    <div @if ($this->groupTasks->contains(fn ($task) => $task->isActive())) wire:poll.30s.visible @endif>
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <div class="w-full sm:max-w-sm">
                <x-filament::input.wrapper>
                    <x-filament::input wire:model.live.debounce.300ms="groupSearch" placeholder="Найти группу в дереве" aria-label="Найти группу в дереве" />
                </x-filament::input.wrapper>
            </div>
            <x-filament::button size="sm" color="gray" wire:click="selectGroup(null)">Все группы</x-filament::button>
        </div>
        <div style="max-height: 320px; overflow: auto; overflow-wrap: anywhere;" aria-label="Группы номенклатуры 1С">
            @forelse ($this->groupTree as $node)
                @include('filament.onec.group-tree-node', ['node' => $node, 'depth' => 0])
            @empty
                <p class="text-sm text-gray-500">Группы не найдены. Обновите номенклатуру из 1С или измените поиск.</p>
            @endforelse
        </div>
        @if ($this->groupTasks->isNotEmpty())
            <details class="mt-3" open>
                <summary class="text-sm font-medium cursor-pointer">Создание групп: последние запросы</summary>
                <div class="mt-2 space-y-2 text-sm">
                    @foreach ($this->groupTasks as $task)
                        <div wire:key="group-task-{{ $task->id }}" class="flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="match ($task->status) { 'done' => 'success', 'failed' => 'danger', 'cancelled' => 'gray', default => 'warning' }">{{ $task->statusLabel() }}</x-filament::badge>
                            <span>{{ data_get($task->payload, 'create_group.path') }} / {{ data_get($task->payload, 'create_group.name') }}</span>
                            <span class="text-gray-500">{{ $task->error ?: $task->summary }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</x-filament::section>
