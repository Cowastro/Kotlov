<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Поставщик</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Код / slug</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Описание</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">Товары</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">Связки</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">Атрибуты</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">Фото</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Команда</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Последний запуск</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-950 dark:text-white">Статус</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($this->suppliers() as $supplier)
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-gray-950 dark:text-white">{{ $supplier['title'] }}</div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $supplier['name'] }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <code class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $supplier['key'] }}</code>
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $supplier['code'] }}</div>
                                </td>
                                <td class="max-w-xs px-4 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $supplier['description'] }}
                                </td>
                                <td class="px-4 py-4 text-right font-medium text-gray-950 dark:text-white">{{ $supplier['products_count'] }}</td>
                                <td class="px-4 py-4 text-right font-medium text-gray-950 dark:text-white">{{ $supplier['mappings_count'] }}</td>
                                <td class="px-4 py-4 text-right font-medium text-gray-950 dark:text-white">{{ $supplier['attributes_count'] }}</td>
                                <td class="px-4 py-4 text-right font-medium text-gray-950 dark:text-white">{{ $supplier['photos_count'] }}</td>
                                <td class="px-4 py-4">
                                    <code class="block max-w-[260px] truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        {{ $supplier['command'] }}
                                    </code>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ $supplier['last_run'] }}</td>
                                <td class="px-4 py-4">
                                    @php
                                        $statusColor = match ($supplier['status']) {
                                            'updated' => 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-500/10 dark:text-success-300',
                                            'dry-run' => 'bg-info-50 text-info-700 ring-info-600/20 dark:bg-info-500/10 dark:text-info-300',
                                            'failed' => 'bg-danger-50 text-danger-700 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300',
                                            default => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-white/10 dark:text-gray-300',
                                        };
                                        $statusLabel = match ($supplier['status']) {
                                            'updated' => 'обновлено',
                                            'dry-run' => 'проверено',
                                            'failed' => 'ошибка',
                                            default => 'нет запусков',
                                        };
                                    @endphp
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="runSupplier('{{ $supplier['key'] }}', 'dry_run')"
                                            class="rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                                        >
                                            Пробный запуск
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="runSupplier('{{ $supplier['key'] }}', 'update')"
                                            wire:confirm="Запустить полное обновление поставщика {{ $supplier['name'] }}?"
                                            class="rounded-md bg-warning-500 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-warning-600"
                                        >
                                            Обновить
                                        </button>
                                        @if ($supplier['source_url'])
                                            <a
                                                href="{{ $supplier['source_url'] }}"
                                                target="_blank"
                                                class="rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                                            >
                                                Источник
                                            </a>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="showLog('{{ $supplier['key'] }}')"
                                            class="rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                                        >
                                            Лог
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="showSettings('{{ $supplier['key'] }}')"
                                            class="rounded-md bg-gray-100 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20"
                                        >
                                            Настройки
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    Поставщики обновления не настроены.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($selectedLogOutput)
            <section class="rounded-xl bg-gray-950 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-3">
                    <h2 class="text-sm font-semibold text-white">{{ $selectedLogTitle }}</h2>
                    <button
                        type="button"
                        wire:click="$set('selectedLogOutput', null)"
                        class="text-xs font-medium text-gray-300 hover:text-white"
                    >
                        Закрыть
                    </button>
                </div>
                <pre class="max-h-[520px] overflow-auto whitespace-pre-wrap px-5 py-4 text-xs leading-5 text-gray-100">{{ $selectedLogOutput }}</pre>
            </section>
        @endif
    </div>
</x-filament-panels::page>
