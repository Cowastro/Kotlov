<section class="b2b-workspace-toolbar" id="staff-workspace"
    data-state='@json($workspaceState)'
    data-create-url="{{ route('b2b.workspace.store') }}"
    data-update-url="{{ route('b2b.workspace.update', ['selection' => '__selection__', 'product' => '__product__']) }}">
    <label>Рабочий подбор<select data-workspace-switch aria-label="Выбрать сохранённый подбор">
        @if(!$workspaceState['id'])<option value="">Новый подбор</option>@endif
        @foreach($workspaceSelections as $saved)<option value="{{ $saved->id }}" @selected($workspaceState['id'] === $saved->id)>{{ $saved->name }}</option>@endforeach
    </select></label>
    <button type="button" data-workspace-new><x-heroicon-o-plus class="b2b-desk-icon" aria-hidden="true"/> Новый подбор</button>
    <div class="b2b-client-pick" data-client-pick data-clients-url="{{ route('b2b.workspace.clients') }}"><label for="workspace-client-search">Клиент</label><span class="b2b-client-chosen" data-client-chosen hidden><b data-client-label></b><button type="button" data-client-history>История</button><button type="button" data-client-clear aria-label="Убрать клиента">×</button></span><input id="workspace-client-search" type="search" autocomplete="off" placeholder="Название или УНП" data-client-search aria-describedby="workspace-client-hint"><span id="workspace-client-hint" hidden>Показывает, что и по какой цене клиент брал раньше</span><ul class="b2b-client-results" data-client-results role="listbox" hidden></ul></div>
    <button type="button" data-workspace-paste-open data-paste-url="{{ route('b2b.workspace.paste') }}"><x-heroicon-o-clipboard-document-list class="b2b-desk-icon" aria-hidden="true"/> Вставить список</button>
    @if($showProcurement)<label class="b2b-margin-threshold">Мин. маржа, %<input type="number" min="0" max="90" step="1" inputmode="numeric" placeholder="не задана" data-workspace-margin-threshold aria-describedby="margin-threshold-hint"></label><span id="margin-threshold-hint" hidden>Строки с маржой ниже порога подсвечиваются. Порог хранится в этом браузере.</span>@endif
    <span class="b2b-workspace-status" data-workspace-status role="status" aria-live="polite">{{ $workspaceState['saved_at'] ? 'Сохранено '.$workspaceState['saved_at'] : 'Добавьте товар — подбор сохранится в аккаунте' }}</span>
</section>
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
    <footer><strong data-workspace-total></strong>@if($showProcurement)<small data-workspace-procurement></small><small data-workspace-suppliers></small>@endif<a data-workspace-edit @if($workspaceState['edit_url'])href="{{ $workspaceState['edit_url'] }}"@else hidden @endif>Название, комментарий и редактирование →</a><a data-workspace-print @if($workspaceState['print_url'])href="{{ $workspaceState['print_url'] }}"@else hidden @endif target="_blank" rel="noopener">Печать / PDF</a></footer>
</dialog>
<dialog class="b2b-workspace-dialog is-paste" id="workspace-paste-dialog" aria-labelledby="workspace-paste-title">
    <header><div><small>Из Excel или письма клиента</small><h2 id="workspace-paste-title">Вставить список</h2></div><button type="button" data-dialog-close aria-label="Закрыть">×</button></header>
    <form data-workspace-paste-form>
        <label for="workspace-paste-text">По строке на позицию: артикул или название и количество</label>
        <textarea id="workspace-paste-text" name="text" rows="7" maxlength="30000" required placeholder="TS.KMP.FRK.0260.79671	4&#10;Отвод Моно ОМ-Р 45° 430-0,8 Ø 150 — 6 шт&#10;Труба ТМ-Р L1000 Ø 150 × 3"></textarea>
        <p class="b2b-workspace-price-note">Количество берётся из отдельного столбца, «5 шт» или «× 5». Число в конце названия (например, диаметр) количеством не считается — тогда ставим 1.</p>
        <button type="submit" class="b2b-workspace-primary">Распознать</button>
    </form>
    <p data-workspace-paste-status role="status" aria-live="polite"></p>
    <div data-workspace-paste-rows></div>
    <footer data-workspace-paste-footer hidden><span data-workspace-paste-summary></span><button type="button" class="b2b-workspace-primary" data-workspace-paste-add>Добавить в подбор</button></footer>
</dialog>
<dialog class="b2b-workspace-dialog is-analogs" id="workspace-analogs-dialog" aria-labelledby="workspace-analogs-title">
    <header><div><small>Тот же тип и размер — другая серия, бренд или поставщик</small><h2 id="workspace-analogs-title">Аналоги</h2></div><button type="button" data-dialog-close aria-label="Закрыть">×</button></header>
    <p data-analogs-source class="b2b-workspace-price-note"></p>
    <p data-analogs-status role="status" aria-live="polite"></p>
    <div data-analogs-list></div>
</dialog>
<dialog class="b2b-workspace-dialog is-history" id="workspace-history-dialog" aria-labelledby="workspace-history-title">
    <header><div><small>Заказы с сайта и B2B‑заявки, без отменённых</small><h2 id="workspace-history-title">История клиента</h2></div><button type="button" data-dialog-close aria-label="Закрыть">×</button></header>
    <p data-history-status role="status" aria-live="polite"></p>
    <div data-history-orders></div>
</dialog>
