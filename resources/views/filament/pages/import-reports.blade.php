<x-filament-panels::page>
    @php
        $reports = $this->reports();
        $selectedReport = $this->selectedReport();
        $rows = $this->selectedRows();
        $headers = $this->selectedHeaders();
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <label class="space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Поставщик</span>
                <select
                    wire:model.live="supplier"
                    class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                    <option value="">Все</option>
                    @foreach ($this->supplierOptions() as $supplier)
                        <option value="{{ $supplier }}">{{ strtoupper($supplier) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Тип отчёта</span>
                <select
                    wire:model.live="type"
                    class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                    <option value="">Все</option>
                    @foreach ($this->typeOptions() as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </label>

            <label class="space-y-1 md:col-span-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Поиск по файлам</span>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="supplier, тип, имя файла"
                    class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                />
            </label>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(320px,420px)_1fr]">
            <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">Файлы отчётов</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Найдено: {{ count($reports) }}</div>
                </div>

                <div class="max-h-[720px] divide-y divide-gray-100 overflow-auto dark:divide-white/10">
                    @forelse ($reports as $report)
                        <button
                            type="button"
                            wire:click="$set('selectedFile', @js($report['relative_path']))"
                            class="w-full px-4 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-white/5 {{ $selectedReport && $selectedReport['relative_path'] === $report['relative_path'] ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $report['file_name'] }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ strtoupper($report['supplier']) }}
                                        </span>
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ $report['type'] }}
                                        </span>
                                        @if ($report['attention_count'] > 0)
                                            <span class="rounded-md bg-danger-100 px-2 py-0.5 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300">
                                                внимание: {{ $report['attention_count'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                                    <div>{{ date('d.m H:i', $report['modified_at']) }}</div>
                                    <div>{{ number_format($report['size'] / 1024, 1, ',', ' ') }} KB</div>
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="px-4 py-8 text-sm text-gray-500 dark:text-gray-400">
                            Отчёты пока не найдены.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="min-w-0 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $selectedReport['file_name'] ?? 'Выберите отчёт' }}
                        </div>
                        @if ($selectedReport)
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $selectedReport['relative_path'] }}
                            </div>
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
                    <div class="overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        Действия
                                    </th>
                                    @foreach ($headers as $header)
                                        <th class="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {{ $header }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
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
                                    <tr class="{{ $isAttention ? 'bg-danger-50/50 dark:bg-danger-500/5' : '' }}">
                                        <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 dark:bg-gray-900">
                                            <div class="flex gap-2">
                                                @if ($productUrl)
                                                    <a
                                                        href="{{ $productUrl }}"
                                                        target="_blank"
                                                        class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                    >
                                                        Товар
                                                    </a>
                                                @endif
                                                @if ($sourceUrl)
                                                    <a
                                                        href="{{ $sourceUrl }}"
                                                        target="_blank"
                                                        class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                                    >
                                                        Источник
                                                    </a>
                                                @endif
                                                @if (! $productUrl && ! $sourceUrl)
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </div>
                                        </td>
                                        @foreach ($headers as $header)
                                            <td class="max-w-[340px] whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">
                                                <div class="truncate" title="{{ (string) ($row[$header] ?? '') }}">
                                                    {{ (string) ($row[$header] ?? '') }}
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-200 px-4 py-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Показано строк: {{ count($rows) }}. Для больших файлов открываются первые {{ $perPage }} строк.
                    </div>
                @else
                    <div class="px-4 py-10 text-sm text-gray-500 dark:text-gray-400">
                        Нет строк для просмотра.
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
