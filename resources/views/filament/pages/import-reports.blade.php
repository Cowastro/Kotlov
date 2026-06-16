<x-filament-panels::page>
    @php
        $reports = $this->reports();
        $selectedReport = $this->selectedReport();
        $rows = $this->selectedRows();
        $headers = $this->selectedHeaders();
    @endphp

    <style>
        .import-reports-page {
            display: grid;
            gap: 18px;
        }

        .import-reports-filters {
            display: grid;
            grid-template-columns: minmax(180px, 240px) minmax(180px, 240px) minmax(260px, 1fr);
            gap: 14px;
            align-items: end;
        }

        .import-reports-label {
            display: grid;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .import-reports-control {
            width: 100%;
            min-height: 40px;
            border: 1px solid rgba(148, 163, 184, .35);
            border-radius: 10px;
            background: rgba(255, 255, 255, .04);
            color: inherit;
            padding: 8px 11px;
            font-size: 14px;
        }

        .import-reports-control option {
            background: rgb(31, 31, 35);
            color: rgb(248, 250, 252);
        }

        .import-reports-card {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 12px;
            background: rgba(255, 255, 255, .025);
            overflow: hidden;
        }

        .import-reports-card-header {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .import-reports-title {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.25;
        }

        .import-reports-muted {
            color: rgb(148, 163, 184);
            font-size: 12px;
            line-height: 1.35;
        }

        .import-reports-table-wrap {
            overflow: auto;
            max-width: 100%;
        }

        .import-reports-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .import-reports-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: rgb(31, 31, 35);
            color: rgb(203, 213, 225);
            padding: 10px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .02em;
            border-bottom: 1px solid rgba(148, 163, 184, .22);
            white-space: nowrap;
        }

        .import-reports-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            vertical-align: top;
            white-space: nowrap;
        }

        .import-reports-table tr:hover td {
            background: rgba(255, 255, 255, .035);
        }

        .import-reports-report-button {
            width: 100%;
            border: 0;
            background: transparent;
            color: inherit;
            text-align: left;
            cursor: pointer;
            font: inherit;
        }

        .import-reports-report-button.is-active {
            color: rgb(251, 191, 36);
        }

        .import-reports-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 5px;
        }

        .import-reports-badge {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            border-radius: 7px;
            padding: 2px 7px;
            background: rgba(148, 163, 184, .16);
            color: rgb(226, 232, 240);
            font-size: 12px;
            font-weight: 600;
        }

        .import-reports-badge-attention {
            background: rgba(239, 68, 68, .18);
            color: rgb(252, 165, 165);
        }

        .import-reports-actions {
            display: flex;
            gap: 10px;
        }

        .import-reports-link {
            color: rgb(251, 191, 36);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .import-reports-link:hover {
            text-decoration: underline;
        }

        .import-reports-cell {
            max-width: 420px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .import-reports-attention-row td {
            background: rgba(239, 68, 68, .06);
        }

        @media (max-width: 900px) {
            .import-reports-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="import-reports-page">
        <div class="import-reports-card">
            <div class="import-reports-card-header">
                <div>
                    <div class="import-reports-title">Фильтры</div>
                    <div class="import-reports-muted">Отчёты читаются из storage/app/reports</div>
                </div>
            </div>

            <div style="padding: 16px;">
                <div class="import-reports-filters">
                    <label class="import-reports-label">
                        <span>Поставщик</span>
                        <select wire:model.live="supplier" class="import-reports-control">
                            <option value="">Все</option>
                            @foreach ($this->supplierOptions() as $supplier)
                                <option value="{{ $supplier }}">{{ strtoupper($supplier) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="import-reports-label">
                        <span>Тип отчёта</span>
                        <select wire:model.live="type" class="import-reports-control">
                            <option value="">Все</option>
                            @foreach ($this->typeOptions() as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="import-reports-label">
                        <span>Поиск по файлам</span>
                        <input
                            wire:model.live.debounce.300ms="search"
                            type="search"
                            placeholder="Поставщик, тип, имя файла"
                            class="import-reports-control"
                        />
                    </label>
                </div>
            </div>
        </div>

        <div class="import-reports-card">
            <div class="import-reports-card-header">
                <div>
                    <div class="import-reports-title">Файлы отчётов</div>
                    <div class="import-reports-muted">Найдено: {{ count($reports) }}</div>
                </div>
            </div>

            <div class="import-reports-table-wrap" style="max-height: 360px;">
                <table class="import-reports-table">
                    <thead>
                        <tr>
                            <th>Файл</th>
                            <th>Поставщик</th>
                            <th>Тип</th>
                            <th>Внимание</th>
                            <th>Дата</th>
                            <th>Размер</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            @php
                                $isActive = $selectedReport && $selectedReport['relative_path'] === $report['relative_path'];
                            @endphp
                            <tr>
                                <td>
                                    <button
                                        type="button"
                                        wire:click="$set('selectedFile', @js($report['relative_path']))"
                                        class="import-reports-report-button {{ $isActive ? 'is-active' : '' }}"
                                    >
                                        <strong>{{ $report['file_name'] }}</strong>
                                        <div class="import-reports-muted">{{ $report['relative_path'] }}</div>
                                    </button>
                                </td>
                                <td>{{ strtoupper($report['supplier']) }}</td>
                                <td>{{ $report['type'] }}</td>
                                <td>
                                    @if ($report['attention_count'] > 0)
                                        <span class="import-reports-badge import-reports-badge-attention">
                                            {{ $report['attention_count'] }}
                                        </span>
                                    @else
                                        <span class="import-reports-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ date('d.m.Y H:i', $report['modified_at']) }}</td>
                                <td>{{ number_format($report['size'] / 1024, 1, ',', ' ') }} KB</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="import-reports-muted">Отчёты пока не найдены.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="import-reports-card">
            <div class="import-reports-card-header">
                <div style="min-width: 0;">
                    <div class="import-reports-title">
                        {{ $selectedReport['file_name'] ?? 'Выберите отчёт' }}
                    </div>
                    @if ($selectedReport)
                        <div class="import-reports-muted">{{ $selectedReport['relative_path'] }}</div>
                    @endif
                </div>

                @if ($selectedReport)
                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-arrow-down-tray"
                        wire:click="downloadSelected"
                    >
                        Скачать CSV
                    </x-filament::button>
                @endif
            </div>

            @if ($selectedReport && $headers !== [])
                <div class="import-reports-table-wrap">
                    <table class="import-reports-table">
                        <thead>
                            <tr>
                                <th>Действия</th>
                                @foreach ($headers as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $productUrl = $this->productAdminUrl($row);
                                    $sourceUrl = $this->sourceUrl($row);
                                    $action = strtolower((string) ($row['action'] ?? $row['recommended_action'] ?? ''));
                                    $isAttention = str_contains($action, 'manual')
                                        || str_contains($action, 'error')
                                        || str_contains($action, 'cost_above_retail')
                                        || str_contains($action, 'keep_manual_review');
                                @endphp
                                <tr class="{{ $isAttention ? 'import-reports-attention-row' : '' }}">
                                    <td>
                                        <div class="import-reports-actions">
                                            @if ($productUrl)
                                                <a class="import-reports-link" href="{{ $productUrl }}" target="_blank">Товар</a>
                                            @endif
                                            @if ($sourceUrl)
                                                <a class="import-reports-link" href="{{ $sourceUrl }}" target="_blank">Источник</a>
                                            @endif
                                            @if (! $productUrl && ! $sourceUrl)
                                                <span class="import-reports-muted">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    @foreach ($headers as $header)
                                        <td>
                                            <div class="import-reports-cell" title="{{ (string) ($row[$header] ?? '') }}">
                                                {{ (string) ($row[$header] ?? '') }}
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="import-reports-muted" style="padding: 12px 16px; border-top: 1px solid rgba(148, 163, 184, .18);">
                    Показано строк: {{ count($rows) }}. Для больших файлов открываются первые {{ $perPage }} строк.
                </div>
            @else
                <div class="import-reports-muted" style="padding: 26px 16px;">
                    Нет строк для просмотра.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
