<section class="b2b-workspace-toolbar" id="staff-workspace"
    data-state='@json($workspaceState)'
    data-create-url="{{ route('b2b.workspace.store') }}"
    data-update-url="{{ route('b2b.workspace.update', ['selection' => '__selection__', 'product' => '__product__']) }}">
    <label>Рабочий подбор<select data-workspace-switch aria-label="Выбрать сохранённый подбор">
        @if(!$workspaceState['id'])<option value="">Новый подбор</option>@endif
        @foreach($workspaceSelections as $saved)<option value="{{ $saved->id }}" @selected($workspaceState['id'] === $saved->id)>{{ $saved->name }}</option>@endforeach
    </select></label>
    <button type="button" data-workspace-new>+ Новый подбор</button>
    <span class="b2b-workspace-status" data-workspace-status role="status" aria-live="polite">{{ $workspaceState['saved_at'] ? 'Сохранено '.$workspaceState['saved_at'] : 'Добавьте товар — подбор сохранится в аккаунте' }}</span>
</section>
<div class="b2b-workspace-summary" aria-label="Текущий подбор">
    <button type="button" data-workspace-open><span data-workspace-count>0 позиций</span><strong data-workspace-total>0,00 BYN</strong><span>Открыть подбор ↑</span></button>
    <a data-workspace-print @if($workspaceState['print_url'])href="{{ $workspaceState['print_url'] }}"@else hidden @endif target="_blank" rel="noopener">Печать / PDF</a>
    <a data-workspace-desk @if($workspaceState['desk_url'])href="{{ $workspaceState['desk_url'] }}"@else hidden @endif>Таблица заявки →</a>
    @if($showProcurement)<small data-workspace-procurement></small>@endif
</div>
<dialog class="b2b-workspace-dialog" id="workspace-new-dialog" aria-labelledby="workspace-new-title">
    <form data-workspace-create>
        <header><h2 id="workspace-new-title">Новый подбор</h2><button type="button" data-dialog-close aria-label="Закрыть">×</button></header>
        <label for="workspace-new-name">Клиент или объект</label>
        <input id="workspace-new-name" name="name" required maxlength="160" placeholder="Например: котельная, Алексей">
        <p>Предыдущий подбор останется в аккаунте. Каждый товар сохраняется сразу после добавления.</p>
        <p data-workspace-create-error role="alert" hidden></p>
        <button type="submit" class="b2b-workspace-primary">Создать подбор</button>
    </form>
</dialog>
<dialog class="b2b-workspace-dialog is-selection" id="workspace-selection-dialog" aria-labelledby="workspace-selection-title">
    <header><div><small>Подбор сохранён в аккаунте</small><h2 id="workspace-selection-title" data-workspace-title>Подбор</h2></div><button type="button" data-dialog-close aria-label="Закрыть">×</button></header>
    <input type="search" data-workspace-line-search aria-label="Поиск в подборе" placeholder="Найти в подборе: название или артикул">
    <p class="b2b-workspace-price-note">Цены сайта на момент добавления. Наличие и индивидуальные условия подтверждаются отдельно.</p>
    <p data-workspace-dialog-status role="status" aria-live="polite"></p>
    <div data-workspace-lines></div>
    <footer><strong data-workspace-total></strong>@if($showProcurement)<small data-workspace-procurement></small>@endif<a data-workspace-edit @if($workspaceState['edit_url'])href="{{ $workspaceState['edit_url'] }}"@else hidden @endif>Название, комментарий и редактирование →</a><a data-workspace-print @if($workspaceState['print_url'])href="{{ $workspaceState['print_url'] }}"@else hidden @endif target="_blank" rel="noopener">Печать / PDF</a></footer>
</dialog>
