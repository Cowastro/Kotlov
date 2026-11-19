<aside class="b2b-workspace-summary" aria-label="Текущий подбор">
    <header class="b2b-selection-heading">
        <x-heroicon-o-clipboard-document-list class="b2b-desk-icon" aria-hidden="true"/>
        <h2>В подборе</h2>
        <span data-workspace-count>{{ $workspaceLines->count() }} поз. · {{ $workspaceLines->sum('quantity') }} шт.</span>
    </header>
    <ul class="b2b-selection-preview" data-workspace-preview-lines aria-label="Товары в подборе"></ul>
    <p class="b2b-selection-empty" data-workspace-empty @if($workspaceLines->isNotEmpty())hidden @endif>Добавьте товары из каталога.</p>
    <div class="b2b-selection-total" aria-live="polite">
        <span>Сумма подбора</span>
        <strong data-workspace-total>{{ number_format($workspaceState['total'], 2, ',', ' ') }} BYN</strong>
    </div>
    @if($showProcurement)
        <small data-workspace-procurement hidden></small>
        <small data-workspace-suppliers hidden></small>
    @endif
    <div class="b2b-selection-actions">
        <a class="b2b-selection-primary" data-workspace-desk @if($workspaceState['desk_url'])href="{{ $workspaceState['desk_url'] }}"@else hidden @endif>
            <x-heroicon-o-table-cells class="b2b-desk-icon" aria-hidden="true"/> Таблица заявки
        </a>
        <button type="button" data-workspace-open>
            <x-heroicon-o-pencil-square class="b2b-desk-icon" aria-hidden="true"/> Открыть подбор
        </button>
        <a data-workspace-print @if($workspaceState['print_url'])href="{{ $workspaceState['print_url'] }}"@else hidden @endif target="_blank" rel="noopener">
            <x-heroicon-o-printer class="b2b-desk-icon" aria-hidden="true"/> PDF
        </a>
    </div>
</aside>
