<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-tabs flex gap-x-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <button
                type="button"
                class="fi-tabs-item flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium text-primary-600 bg-primary-50 dark:bg-primary-500/10"
            >
                Эликон
            </button>
        </div>

        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Поставщик</div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Эликон</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Категория</div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Счетчики газа</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Источник</div>
                    <a
                        href="https://elicon.by/product-category/bitovie_schetchiki_gaza/"
                        target="_blank"
                        class="mt-1 block text-lg font-semibold text-primary-600"
                    >
                        elicon.by
                    </a>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Команда</div>
                    <div class="mt-1 text-sm font-mono text-gray-700 dark:text-gray-200">supplier:sync-elicon-gas-meters</div>
                </div>
            </div>
        </section>

        @if ($lastOutput)
            <section class="rounded-xl bg-gray-950 p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white">Последний вывод</h2>
                    <span class="rounded-md px-2 py-1 text-xs font-medium {{ $lastExitCode === 0 ? 'bg-success-500/20 text-success-300' : 'bg-danger-500/20 text-danger-300' }}">
                        exit {{ $lastExitCode }}
                    </span>
                </div>
                <pre class="max-h-[520px] overflow-auto whitespace-pre-wrap text-xs leading-5 text-gray-100">{{ $lastOutput }}</pre>
            </section>
        @endif
    </div>
</x-filament-panels::page>
