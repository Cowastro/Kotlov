@foreach ($nodes as $node)
    @if ($node['children']->isNotEmpty())
        <details class="b2b-category-branch {{ $node['selected'] ? 'selected' : '' }}" data-category-node data-label="{{ mb_strtolower($node['name']) }}" @if($node['expanded']) open @endif>
            <summary>
                <span>{{ $node['name'] }}</span>
                <span class="b2b-category-count">{{ $node['count'] }}</span>
            </summary>
            <div class="b2b-category-tree__nested">
                <a href="{{ request()->fullUrlWithQuery(['category' => $node['id'], 'q' => null, 'page' => null]) }}" class="b2b-category-section-link">
                    Все товары раздела
                </a>
                @include('partials.b2b-category-tree', ['nodes' => $node['children'], 'level' => $level + 1])
            </div>
        </details>
    @else
        <a href="{{ request()->fullUrlWithQuery(['category' => $node['id'], 'q' => null, 'page' => null]) }}" class="b2b-category-leaf{{ $level === 0 ? ' b2b-category-leaf--root' : '' }}{{ $node['selected'] ? ' active' : '' }}" data-category-node data-label="{{ mb_strtolower($node['name']) }}">
            <span>{{ $node['name'] }}</span>
            <span class="b2b-category-count">{{ $node['count'] }}</span>
        </a>
    @endif
@endforeach
