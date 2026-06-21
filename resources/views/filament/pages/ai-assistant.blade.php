<x-filament-panels::page>
    @php
        $metrics = $this->metrics();
        $workstreams = $this->workstreams();
        $issueGroups = $this->issueGroups();
        $qualityOptions = $this->qualityIssueOptions();
        $qualitySamples = $this->qualitySamples();
        $pendingDecisions = $this->pendingDecisions();
        $decisionTypes = $this->decisionTypeOptions();
        $decisionChanges = $this->decisionChangeOptions();
        $supplierOptions = $this->supplierFilterOptions();
        $supplierSync = $this->supplierSyncSummary();
    @endphp

    <style>
        .ai-assistant-page {
            display: grid;
            gap: 18px;
        }

        .ai-assistant-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .ai-assistant-card {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 8px;
            background: rgba(255, 255, 255, .025);
            overflow: hidden;
        }

        .ai-assistant-metric {
            padding: 16px;
        }

        .ai-assistant-metric-label {
            color: rgb(148, 163, 184);
            font-size: 12px;
            line-height: 1.35;
        }

        .ai-assistant-metric-value {
            margin-top: 8px;
            font-size: 28px;
            font-weight: 750;
            line-height: 1;
        }

        .ai-assistant-metric[data-tone="warning"] .ai-assistant-metric-value {
            color: rgb(251, 191, 36);
        }

        .ai-assistant-metric[data-tone="danger"] .ai-assistant-metric-value {
            color: rgb(248, 113, 113);
        }

        .ai-assistant-metric[data-tone="info"] .ai-assistant-metric-value {
            color: rgb(96, 165, 250);
        }

        .ai-assistant-overview {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, .34fr);
            gap: 18px;
            align-items: start;
        }

        .ai-assistant-card-header {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .ai-assistant-title {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.25;
        }

        .ai-assistant-muted {
            color: rgb(148, 163, 184);
            font-size: 12px;
            line-height: 1.45;
        }

        .ai-assistant-workstreams {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding: 14px;
        }

        .ai-assistant-workstream {
            display: grid;
            gap: 11px;
            padding: 14px;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 8px;
            background: rgba(15, 23, 42, .16);
        }

        .ai-assistant-workstream-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
        }

        .ai-assistant-badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(251, 191, 36, .12);
            color: rgb(251, 191, 36);
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .ai-assistant-link {
            width: max-content;
            color: rgb(251, 191, 36);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .ai-assistant-quality-layout {
            display: grid;
            grid-template-columns: minmax(260px, .32fr) minmax(0, 1fr);
            align-items: stretch;
        }

        .ai-assistant-rows {
            display: grid;
            gap: 0;
        }

        .ai-assistant-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-row:last-child {
            border-bottom: 0;
        }

        .ai-assistant-row-label {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }

        .ai-assistant-row-hint {
            margin-top: 3px;
            color: rgb(148, 163, 184);
            font-size: 12px;
            line-height: 1.4;
        }

        .ai-assistant-row-count {
            font-size: 16px;
            font-weight: 750;
            white-space: nowrap;
        }

        .ai-assistant-quality-tools {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center;
            padding: 12px 16px;
            border-top: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-quality-main {
            border-left: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-quality-list {
            display: grid;
            border-top: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-quality-item {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr) auto;
            gap: 10px;
            padding: 10px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-quality-item:last-child {
            border-bottom: 0;
        }

        .ai-assistant-quality-title {
            font-size: 13px;
            font-weight: 750;
            line-height: 1.35;
        }

        .ai-assistant-quality-meta,
        .ai-assistant-quality-problems {
            margin-top: 4px;
            color: rgb(148, 163, 184);
            font-size: 12px;
            line-height: 1.35;
        }

        .ai-assistant-quality-problems {
            color: rgb(251, 191, 36);
            font-weight: 700;
        }

        .ai-assistant-table-wrap {
            overflow: auto;
            max-width: 100%;
            max-height: min(58vh, 720px);
            border-top: 1px solid rgba(148, 163, 184, .12);
            border-bottom: 1px solid rgba(148, 163, 184, .12);
        }

        .ai-assistant-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .ai-assistant-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: rgba(15, 23, 42, .26);
            color: rgb(203, 213, 225);
            padding: 9px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .02em;
            border-bottom: 1px solid rgba(148, 163, 184, .22);
            white-space: nowrap;
        }

        .ai-assistant-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            vertical-align: top;
        }

        .ai-assistant-table tr:last-child td {
            border-bottom: 0;
        }

        .ai-assistant-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
            min-width: 210px;
        }

        .ai-assistant-change {
            display: grid;
            gap: 3px;
            min-width: 170px;
            line-height: 1.35;
        }

        .ai-assistant-change-label {
            color: rgb(148, 163, 184);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .ai-assistant-change-current {
            color: rgb(148, 163, 184);
        }

        .ai-assistant-change-suggested {
            color: rgb(226, 232, 240);
            font-weight: 750;
        }

        .ai-assistant-change-suggested[data-empty="true"] {
            color: rgb(148, 163, 184);
            font-weight: 650;
        }

        .ai-assistant-reason {
            min-width: 360px;
            max-width: 720px;
            color: rgb(203, 213, 225);
            line-height: 1.45;
        }

        .ai-assistant-duplicate {
            min-width: 130px;
            max-width: 220px;
            color: rgb(148, 163, 184);
            line-height: 1.35;
        }

        .ai-assistant-duplicate-title {
            color: rgb(226, 232, 240);
            font-weight: 700;
        }

        .ai-assistant-duplicate-link {
            display: inline-flex;
            margin-top: 5px;
            color: rgb(251, 191, 36);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .ai-assistant-duplicate-note {
            margin-top: 5px;
            color: rgb(251, 191, 36);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }

        .ai-assistant-duplicate-hint {
            margin-top: 5px;
            font-size: 12px;
            line-height: 1.35;
        }

        .ai-assistant-bulk-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            padding: 10px 16px;
            border-top: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-checkbox {
            width: 16px;
            height: 16px;
            accent-color: rgb(251, 191, 36);
        }

        .ai-assistant-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 5px 9px;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: 6px;
            background: rgba(15, 23, 42, .28);
            color: rgb(226, 232, 240);
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            text-decoration: none;
            cursor: pointer;
        }

        .ai-assistant-action:hover {
            border-color: rgba(251, 191, 36, .7);
            color: rgb(251, 191, 36);
        }

        .ai-assistant-action:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .ai-assistant-action:disabled:hover {
            border-color: rgba(148, 163, 184, .25);
            color: inherit;
        }

        .ai-assistant-action[data-tone="success"] {
            border-color: rgba(34, 197, 94, .32);
            color: rgb(134, 239, 172);
        }

        .ai-assistant-action[data-tone="danger"] {
            border-color: rgba(248, 113, 113, .34);
            color: rgb(252, 165, 165);
        }

        .ai-assistant-table-tools {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            padding: 10px 16px;
            border-top: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-filters {
            display: grid;
            grid-template-columns: minmax(220px, 1.2fr) repeat(4, minmax(150px, .7fr)) auto;
            gap: 10px;
            align-items: end;
            padding: 12px 16px;
            border-top: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-filter {
            display: grid;
            gap: 5px;
        }

        .ai-assistant-filter-label {
            color: rgb(148, 163, 184);
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .ai-assistant-per-page {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgb(148, 163, 184);
            font-size: 12px;
            white-space: nowrap;
        }

        .ai-assistant-input,
        .ai-assistant-select {
            color-scheme: dark;
            min-height: 30px;
            padding: 4px 28px 4px 9px;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: 6px;
            background-color: rgb(15, 23, 42);
            color: rgb(226, 232, 240);
            font-size: 12px;
            font-weight: 700;
        }

        .ai-assistant-input {
            padding-right: 9px;
        }

        .ai-assistant-input:focus,
        .ai-assistant-select:focus {
            outline: 2px solid rgba(251, 191, 36, .28);
            outline-offset: 2px;
            border-color: rgba(251, 191, 36, .68);
        }

        .ai-assistant-select option {
            background-color: rgb(15, 23, 42);
            color: rgb(226, 232, 240);
        }

        .ai-assistant-select option:checked {
            background-color: rgb(30, 41, 59);
            color: rgb(251, 191, 36);
        }

        .ai-assistant-pagination {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 12px 16px 14px;
            border-top: 1px solid rgba(148, 163, 184, .14);
        }

        .ai-assistant-pagination-actions {
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .ai-assistant-sync {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            padding: 14px;
        }

        .ai-assistant-sync-item {
            padding: 12px;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 8px;
            background: rgba(15, 23, 42, .16);
        }

        .ai-assistant-page {
            --aa-card-bg: rgb(255, 255, 255);
            --aa-panel-bg: rgb(249, 250, 251);
            --aa-table-head-bg: rgb(243, 244, 246);
            --aa-border: rgba(15, 23, 42, .1);
            --aa-border-strong: rgba(15, 23, 42, .16);
            --aa-text: rgb(15, 23, 42);
            --aa-muted: rgb(100, 116, 139);
            --aa-soft-muted: rgb(71, 85, 105);
            --aa-action-bg: rgb(255, 255, 255);
            --aa-select-bg: rgb(255, 255, 255);
            --aa-select-option-bg: rgb(255, 255, 255);
            --aa-select-option-selected-bg: rgb(254, 243, 199);
            --aa-accent: rgb(217, 119, 6);
        }

        .dark .ai-assistant-page {
            --aa-card-bg: rgba(255, 255, 255, .025);
            --aa-panel-bg: rgba(15, 23, 42, .16);
            --aa-table-head-bg: rgba(15, 23, 42, .78);
            --aa-border: rgba(148, 163, 184, .14);
            --aa-border-strong: rgba(148, 163, 184, .22);
            --aa-text: rgb(226, 232, 240);
            --aa-muted: rgb(148, 163, 184);
            --aa-soft-muted: rgb(203, 213, 225);
            --aa-action-bg: rgba(15, 23, 42, .28);
            --aa-select-bg: rgb(15, 23, 42);
            --aa-select-option-bg: rgb(15, 23, 42);
            --aa-select-option-selected-bg: rgb(30, 41, 59);
            --aa-accent: rgb(251, 191, 36);
        }

        .ai-assistant-card {
            border-color: var(--aa-border-strong);
            background: var(--aa-card-bg);
        }

        .ai-assistant-card-header,
        .ai-assistant-row,
        .ai-assistant-quality-tools,
        .ai-assistant-quality-main,
        .ai-assistant-quality-list,
        .ai-assistant-quality-item,
        .ai-assistant-table td,
        .ai-assistant-table-wrap,
        .ai-assistant-bulk-actions,
        .ai-assistant-table-tools,
        .ai-assistant-pagination {
            border-color: var(--aa-border);
        }

        .ai-assistant-title,
        .ai-assistant-row-label,
        .ai-assistant-row-count,
        .ai-assistant-quality-title,
        .ai-assistant-table td,
        .ai-assistant-change-suggested {
            color: var(--aa-text);
        }

        .ai-assistant-muted,
        .ai-assistant-metric-label,
        .ai-assistant-row-hint,
        .ai-assistant-quality-meta,
        .ai-assistant-per-page,
        .ai-assistant-change-label,
        .ai-assistant-change-current,
        .ai-assistant-change-suggested[data-empty="true"],
        .ai-assistant-duplicate {
            color: var(--aa-muted);
        }

        .ai-assistant-reason {
            color: var(--aa-soft-muted);
        }

        .ai-assistant-duplicate-title {
            color: var(--aa-text);
        }

        .ai-assistant-duplicate-hint {
            color: var(--aa-soft-muted);
        }

        .ai-assistant-workstream,
        .ai-assistant-sync-item {
            border-color: var(--aa-border);
            background: var(--aa-panel-bg);
        }

        .ai-assistant-link,
        .ai-assistant-badge {
            color: var(--aa-accent);
        }

        .ai-assistant-table th {
            background: var(--aa-table-head-bg);
            color: var(--aa-muted);
            border-color: var(--aa-border-strong);
        }

        .ai-assistant-action {
            background: var(--aa-action-bg);
            color: var(--aa-text);
        }

        .ai-assistant-action[data-tone="success"] {
            background: rgba(34, 197, 94, .08);
            color: rgb(22, 101, 52);
        }

        .dark .ai-assistant-action[data-tone="success"] {
            color: rgb(134, 239, 172);
        }

        .ai-assistant-action[data-tone="danger"] {
            background: rgba(248, 113, 113, .1);
            color: rgb(153, 27, 27);
        }

        .dark .ai-assistant-action[data-tone="danger"] {
            color: rgb(252, 165, 165);
        }

        .ai-assistant-input,
        .ai-assistant-select {
            color-scheme: light;
            background-color: var(--aa-select-bg);
            color: var(--aa-text);
        }

        .dark .ai-assistant-input,
        .dark .ai-assistant-select {
            color-scheme: dark;
        }

        .ai-assistant-select option {
            background-color: var(--aa-select-option-bg);
            color: var(--aa-text);
        }

        .ai-assistant-select option:checked {
            background-color: var(--aa-select-option-selected-bg);
            color: var(--aa-accent);
        }

        @media (max-width: 1100px) {
            .ai-assistant-grid,
            .ai-assistant-workstreams {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ai-assistant-overview,
            .ai-assistant-quality-layout {
                grid-template-columns: 1fr;
            }

            .ai-assistant-quality-main {
                border-left: 0;
                border-top: 1px solid rgba(148, 163, 184, .14);
            }
        }

        @media (max-width: 680px) {
            .ai-assistant-grid,
            .ai-assistant-workstreams,
            .ai-assistant-sync {
                grid-template-columns: 1fr;
            }

            .ai-assistant-card-header,
            .ai-assistant-workstream-top,
            .ai-assistant-table-tools {
                display: grid;
            }

            .ai-assistant-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ai-assistant-page">
        <div class="ai-assistant-grid">
            @foreach ($metrics as $metric)
                <div class="ai-assistant-card ai-assistant-metric" data-tone="{{ $metric['tone'] }}">
                    <div class="ai-assistant-metric-label">{{ $metric['label'] }}</div>
                    <div class="ai-assistant-metric-value">{{ $metric['value'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="ai-assistant-card">
            <div class="ai-assistant-card-header">
                <div>
                    <div class="ai-assistant-title">Очередь качества</div>
                    <div class="ai-assistant-muted">Проблемные карточки для ручной проверки, AI-аудита и следующих массовых действий.</div>
                </div>
                <span class="ai-assistant-muted">Первые 8 карточек</span>
            </div>

            <div class="ai-assistant-quality-layout">
                <div>
                    <div class="ai-assistant-rows">
                        @foreach ($issueGroups as $issue)
                            <div class="ai-assistant-row">
                                <div>
                                    <div class="ai-assistant-row-label">{{ $issue['label'] }}</div>
                                    <div class="ai-assistant-row-hint">{{ $issue['hint'] }}</div>
                                </div>
                                <div class="ai-assistant-row-count">{{ number_format($issue['count'], 0, '.', ' ') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="ai-assistant-quality-main">
                    <div class="ai-assistant-quality-tools">
                        <label class="ai-assistant-filter">
                            <span class="ai-assistant-filter-label">Проблема</span>
                            <select class="ai-assistant-select" wire:model.live="qualityIssue">
                                @foreach ($qualityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="ai-assistant-quality-list">
                        @forelse ($qualitySamples as $product)
                            <div class="ai-assistant-quality-item">
                                <div class="ai-assistant-row-count">{{ $product['id'] }}</div>
                                <div>
                                    <div class="ai-assistant-quality-title">{{ $product['title'] }}</div>
                                    <div class="ai-assistant-quality-problems">{{ implode(' / ', $product['problems']) }}</div>
                                    <div class="ai-assistant-quality-meta">
                                        SKU {{ $product['sku'] }} · {{ $product['category'] }} · {{ $product['brand'] }} · {{ $product['source'] }}
                                    </div>
                                    @if ($product['supplier_hint'])
                                        <div class="ai-assistant-quality-meta">{{ $product['supplier_hint'] }}</div>
                                    @endif
                                </div>
                                <a class="ai-assistant-action" href="{{ $product['url'] }}">Открыть</a>
                            </div>
                        @empty
                            <div class="ai-assistant-row">
                                <div class="ai-assistant-muted">По этому фильтру проблемных карточек нет.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="ai-assistant-overview">
            <div class="ai-assistant-card">
                <div class="ai-assistant-card-header">
                    <div>
                        <div class="ai-assistant-title">Рабочие потоки</div>
                        <div class="ai-assistant-muted">Короткая карта зон ответственности и следующих задач.</div>
                    </div>
                </div>

                <div class="ai-assistant-workstreams">
                    @foreach ($workstreams as $stream)
                        <div class="ai-assistant-workstream">
                            <div class="ai-assistant-workstream-top">
                                <div>
                                    <div class="ai-assistant-title">{{ $stream['title'] }}</div>
                                    <div class="ai-assistant-muted">{{ $stream['summary'] }}</div>
                                </div>
                                <span class="ai-assistant-badge">{{ $stream['status'] }}</span>
                            </div>
                            <a class="ai-assistant-link" href="{{ $stream['url'] }}">Открыть раздел</a>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ai-assistant-card">
                <div class="ai-assistant-card-header">
                    <div>
                        <div class="ai-assistant-title">Синхронизации</div>
                        <div class="ai-assistant-muted">Состояние supplier_syncs как входа для дальнейшего AI-разбора.</div>
                    </div>
                </div>

                <div class="ai-assistant-sync">
                    <div class="ai-assistant-sync-item">
                        <div class="ai-assistant-muted">Активные</div>
                        <div class="ai-assistant-metric-value">{{ $supplierSync['active'] }}</div>
                    </div>
                    <div class="ai-assistant-sync-item">
                        <div class="ai-assistant-muted">С ошибкой</div>
                        <div class="ai-assistant-metric-value">{{ $supplierSync['failed'] }}</div>
                    </div>
                    <div class="ai-assistant-sync-item">
                        <div class="ai-assistant-muted">Без запуска</div>
                        <div class="ai-assistant-metric-value">{{ $supplierSync['never'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ai-assistant-card">
            <div class="ai-assistant-card-header">
                <div>
                    <div class="ai-assistant-title">Последние pending-решения</div>
                    <div class="ai-assistant-muted">ИИ-помощник будет расширять этот список понятными действиями применения и отклонения.</div>
                </div>
                <a class="ai-assistant-link" href="{{ url('/admin/import-reports') }}">Отчёты импорта</a>
            </div>

            <div class="ai-assistant-filters">
                <label class="ai-assistant-filter">
                    <span class="ai-assistant-filter-label">Поиск</span>
                    <input
                        class="ai-assistant-input"
                        type="search"
                        placeholder="Товар, причина, артикул, ID"
                        wire:model.live.debounce.400ms="decisionSearch"
                    >
                </label>

                <label class="ai-assistant-filter">
                    <span class="ai-assistant-filter-label">Статус</span>
                    <select class="ai-assistant-select" wire:model.live="decisionStatus">
                        <option value="">Все</option>
                        <option value="pending">Pending</option>
                        <option value="applied">Применено</option>
                        <option value="failed">Ошибка</option>
                    </select>
                </label>

                <label class="ai-assistant-filter">
                    <span class="ai-assistant-filter-label">Тип</span>
                    <select class="ai-assistant-select" wire:model.live="decisionType">
                        <option value="">Все типы</option>
                        @foreach ($decisionTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ai-assistant-filter">
                    <span class="ai-assistant-filter-label">Изменение</span>
                    <select class="ai-assistant-select" wire:model.live="decisionChange">
                        <option value="">Все изменения</option>
                        @foreach ($decisionChanges as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ai-assistant-filter">
                    <span class="ai-assistant-filter-label">Поставщик</span>
                    <select class="ai-assistant-select" wire:model.live="decisionSupplier">
                        <option value="">Все</option>
                        @foreach ($supplierOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button
                    type="button"
                    class="ai-assistant-action"
                    wire:click="resetDecisionFilters"
                >
                    Сбросить
                </button>
            </div>

            <div class="ai-assistant-bulk-actions">
                <span class="ai-assistant-muted">Выбрано: {{ count($this->selectedDecisionIds) }}</span>

                <button
                    type="button"
                    class="ai-assistant-action"
                    wire:click="selectVisiblePendingDecisions"
                >
                    Выбрать видимые pending
                </button>

                <button
                    type="button"
                    class="ai-assistant-action"
                    data-tone="success"
                    wire:click="applySelectedDecisions"
                    wire:confirm="Применить выбранные pending-решения?"
                >
                    Применить выбранные
                </button>

                <button
                    type="button"
                    class="ai-assistant-action"
                    wire:click="recalculateSelectedDecisions"
                    wire:confirm="Пересчитать выбранные pending-решения бренд/категория?"
                >
                    Пересчитать выбранные
                </button>

                <button
                    type="button"
                    class="ai-assistant-action"
                    data-tone="danger"
                    wire:click="deleteSelectedDecisions"
                    wire:confirm="Удалить выбранные pending-решения без применения?"
                >
                    Удалить выбранные
                </button>

                <button
                    type="button"
                    class="ai-assistant-action"
                    wire:click="clearSelectedDecisions"
                >
                    Очистить выбор
                </button>
            </div>

            <div class="ai-assistant-table-tools">
                <div class="ai-assistant-muted">
                    @if ($pendingDecisions->total() > 0)
                        Показано {{ $pendingDecisions->firstItem() }}-{{ $pendingDecisions->lastItem() }} из {{ $pendingDecisions->total() }}
                    @else
                        Pending-решений нет
                    @endif
                </div>

                <label class="ai-assistant-per-page">
                    Строк
                    <select class="ai-assistant-select" wire:model.live="decisionsPerPage">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>

            <div class="ai-assistant-table-wrap">
                <table class="ai-assistant-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>ID</th>
                            <th>Статус</th>
                            <th>Товар ID</th>
                            <th>Товар</th>
                            <th>Категория</th>
                            <th>Бренд</th>
                            <th>Дубль</th>
                            <th>Причина</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingDecisions as $decision)
                            <tr>
                                <td>
                                    @if ($decision['status_code'] === 'pending')
                                        <input
                                            class="ai-assistant-checkbox"
                                            type="checkbox"
                                            value="{{ $decision['id'] }}"
                                            wire:model.live="selectedDecisionIds"
                                        >
                                    @endif
                                </td>
                                <td>{{ $decision['id'] }}</td>
                                <td>{{ $decision['status'] }}</td>
                                <td>{{ $decision['product_id'] ?: '-' }}</td>
                                <td>
                                    <div>{{ $decision['title'] }}</div>
                                    <div class="ai-assistant-muted">{{ $decision['supplier'] }}</div>
                                </td>
                                <td>
                                    <div class="ai-assistant-change">
                                        <span class="ai-assistant-change-label">Сейчас</span>
                                        <span class="ai-assistant-change-current">{{ $decision['category_change']['current'] }}</span>
                                        <span class="ai-assistant-change-label">{{ $decision['category_change']['suggested_label'] }}</span>
                                        <span
                                            class="ai-assistant-change-suggested"
                                            data-empty="{{ $decision['category_change']['empty'] ? 'true' : 'false' }}"
                                        >
                                            {{ $decision['category_change']['suggested'] }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="ai-assistant-change">
                                        <span class="ai-assistant-change-label">Сейчас</span>
                                        <span class="ai-assistant-change-current">{{ $decision['brand_change']['current'] }}</span>
                                        <span class="ai-assistant-change-label">{{ $decision['brand_change']['suggested_label'] }}</span>
                                        <span
                                            class="ai-assistant-change-suggested"
                                            data-empty="{{ $decision['brand_change']['empty'] ? 'true' : 'false' }}"
                                        >
                                            {{ $decision['brand_change']['suggested'] }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="ai-assistant-duplicate">
                                        @if ($decision['duplicate']['exists'])
                                            <div class="ai-assistant-change-label">Пара дублей {{ $decision['duplicate']['pair_label'] }}</div>

                                            @if ($decision['duplicate']['is_reciprocal'])
                                                <div class="ai-assistant-duplicate-note">Встречная строка уже есть ниже/выше</div>
                                            @endif

                                            <div class="ai-assistant-change-label">Этот товар</div>
                                            <div class="ai-assistant-duplicate-title">
                                                ID {{ $decision['duplicate']['current_id'] }} - {{ $decision['duplicate']['current_title'] }}
                                            </div>

                                            <div class="ai-assistant-change-label">Похожий товар</div>
                                            <div class="ai-assistant-duplicate-title">
                                                ID {{ $decision['duplicate']['id'] }} - {{ $decision['duplicate']['title'] }}
                                            </div>

                                            <div>{{ $decision['duplicate']['reason'] }}</div>
                                            <div class="ai-assistant-duplicate-hint">{{ $decision['duplicate']['action_hint'] }}</div>
                                            <a
                                                class="ai-assistant-duplicate-link"
                                                href="{{ $decision['duplicate']['url'] }}"
                                            >
                                                Открыть дубль
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                                <td><div class="ai-assistant-reason">{{ $decision['reason'] }}</div></td>
                                <td>
                                    <div class="ai-assistant-actions">
                                        @if ($decision['product_url'])
                                            <a
                                                class="ai-assistant-action"
                                                href="{{ $decision['product_url'] }}"
                                            >
                                                Открыть
                                            </a>
                                        @endif

                                        @if ($decision['status_code'] === 'pending')
                                            <button
                                                type="button"
                                                class="ai-assistant-action"
                                                data-tone="success"
                                                wire:click="applyPendingDecision({{ $decision['id'] }})"
                                                wire:confirm="Применить pending-решение ID {{ $decision['id'] }}?"
                                            >
                                                Применить
                                            </button>

                                            <button
                                                type="button"
                                                class="ai-assistant-action"
                                                data-tone="danger"
                                                wire:click="deletePendingDecision({{ $decision['id'] }})"
                                                wire:confirm="Удалить pending-решение ID {{ $decision['id'] }} без применения?"
                                            >
                                                Удалить
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <span class="ai-assistant-muted">Pending-решений нет. После AI-разбора каталога они появятся здесь.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ai-assistant-pagination">
                @if ($pendingDecisions->hasPages())
                    <div class="ai-assistant-muted">
                        Страница {{ $pendingDecisions->currentPage() }} из {{ $pendingDecisions->lastPage() }}
                    </div>

                    <div class="ai-assistant-pagination-actions">
                        <button
                            type="button"
                            class="ai-assistant-action"
                            wire:click="previousPage('decisionsPage')"
                            @disabled($pendingDecisions->onFirstPage())
                        >
                            Назад
                        </button>

                        <button
                            type="button"
                            class="ai-assistant-action"
                            wire:click="nextPage('decisionsPage')"
                            @disabled(! $pendingDecisions->hasMorePages())
                        >
                            Вперед
                        </button>
                    </div>
                @else
                    <span class="ai-assistant-muted">Все найденные решения уже на этой странице.</span>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
