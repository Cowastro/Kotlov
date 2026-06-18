<x-filament-panels::page>
    @php
        $metrics = $this->metrics();
        $workstreams = $this->workstreams();
        $issueGroups = $this->issueGroups();
        $recentDecisions = $this->recentDecisions();
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

        .ai-assistant-section {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
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

        .ai-assistant-list {
            display: grid;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .ai-assistant-list li {
            position: relative;
            padding-left: 14px;
            color: rgb(203, 213, 225);
            font-size: 13px;
            line-height: 1.45;
        }

        .ai-assistant-list li::before {
            content: "";
            position: absolute;
            top: .65em;
            left: 0;
            width: 5px;
            height: 5px;
            border-radius: 999px;
            background: rgb(251, 191, 36);
        }

        .ai-assistant-link {
            width: max-content;
            color: rgb(251, 191, 36);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .ai-assistant-side {
            display: grid;
            gap: 18px;
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

        .ai-assistant-table-wrap {
            overflow: auto;
            max-width: 100%;
        }

        .ai-assistant-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .ai-assistant-table th {
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

        @media (max-width: 1100px) {
            .ai-assistant-grid,
            .ai-assistant-workstreams {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ai-assistant-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 680px) {
            .ai-assistant-grid,
            .ai-assistant-workstreams,
            .ai-assistant-sync {
                grid-template-columns: 1fr;
            }

            .ai-assistant-card-header,
            .ai-assistant-workstream-top {
                display: grid;
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

        <div class="ai-assistant-section">
            <div class="ai-assistant-card">
                <div class="ai-assistant-card-header">
                    <div>
                        <div class="ai-assistant-title">Рабочие потоки</div>
                        <div class="ai-assistant-muted">Границы задач для Codex и Claude, чтобы не пересекаться в поставщиках.</div>
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

                            <ul class="ai-assistant-list">
                                @foreach ($stream['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>

                            <a class="ai-assistant-link" href="{{ $stream['url'] }}">Открыть раздел</a>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ai-assistant-side">
                <div class="ai-assistant-card">
                    <div class="ai-assistant-card-header">
                        <div>
                            <div class="ai-assistant-title">Очередь качества</div>
                            <div class="ai-assistant-muted">Первые группы для AI-аудита карточек.</div>
                        </div>
                    </div>

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
        </div>

        <div class="ai-assistant-card">
            <div class="ai-assistant-card-header">
                <div>
                    <div class="ai-assistant-title">Последние pending-решения</div>
                    <div class="ai-assistant-muted">ИИ-помощник будет расширять этот список понятными действиями применения и отклонения.</div>
                </div>
                <a class="ai-assistant-link" href="{{ url('/admin/import-reports') }}">Отчёты импорта</a>
            </div>

            <div class="ai-assistant-table-wrap">
                <table class="ai-assistant-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Тип</th>
                            <th>Поставщик</th>
                            <th>Товар</th>
                            <th>Причина</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentDecisions as $decision)
                            <tr>
                                <td>{{ $decision['id'] }}</td>
                                <td>{{ $decision['decision'] }}</td>
                                <td>{{ $decision['supplier'] }}</td>
                                <td>{{ $decision['title'] }}</td>
                                <td>{{ $decision['reason'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <span class="ai-assistant-muted">Pending-решений нет. После AI-разбора каталога они появятся здесь.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
