<div wire:key="onec-folder-{{ $node['key'] }}" style="margin-inline-start: {{ $depth ? 18 : 0 }}px">
    @if ($node['children'])
        <details @if ($node['open'] || $depth === 0) open @endif>
            <summary class="cursor-pointer py-1">
                <button type="button" wire:click.stop="selectGroup('{{ $node['key'] }}')" @class(['text-primary-600 dark:text-primary-400 font-semibold' => $node['selected']]) aria-pressed="{{ $node['selected'] ? 'true' : 'false' }}">{{ $node['name'] }}</button>
                <span class="text-xs text-gray-500">({{ count($node['children']) }})</span>
            </summary>
            @foreach ($node['children'] as $child)
                @include('filament.onec.group-tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </details>
    @else
        <button type="button" wire:click="selectGroup('{{ $node['key'] }}')" @class(['block py-1 text-left', 'text-primary-600 dark:text-primary-400 font-semibold' => $node['selected']]) aria-pressed="{{ $node['selected'] ? 'true' : 'false' }}">{{ $node['name'] }}</button>
    @endif
</div>
