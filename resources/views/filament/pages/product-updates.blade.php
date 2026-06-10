<x-filament-panels::page>
    @php
        $stats = $this->getEliconStats();
    @endphp

    <div class="max-w-6xl space-y-6">
        <nav class="flex gap-2 rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm"
            >
                <x-filament::icon icon="heroicon-o-building-storefront" class="h-4 w-4" />
                Эликон
            </button>
        </nav>

        <section class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-white/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="text-sm font-medium text-primary-600 dark:text-primary-400">Поставщик</div>
                        <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Эликон: счетчики газа</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                            Обновляет цены, наличие, описания, характеристики и фотографии по каталогу бытовых счетчиков газа.
                        </p>
                    </div>

                    <a
                        href="https://elicon.by/product-category/bitovie_schetchiki_gaza/"
                        target="_blank"
                        class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-500"
                    >
                        Открыть источник
                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <div class="grid gap-0 divide-y divide-gray-200 dark:divide-white/10 md:grid-cols-4 md:divide-x md:divide-y-0">
                <div class="p-5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Товары</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $stats['products'] }}</div>
                </div>
                <div class="p-5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Связки</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $stats['mappings'] }}</div>
                </div>
                <div class="p-5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Атрибуты</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $stats['attributes'] }}</div>
                </div>
                <div class="p-5">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Фото</div>
                    <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $stats['images'] }}</div>
                </div>
            </div>

            <div class="grid gap-4 border-t border-gray-200 px-6 py-5 dark:border-white/10 lg:grid-cols-[1fr_2fr]">
                <div>
                    <div class="text-sm font-medium text-gray-950 dark:text-white">Команда</div>
                    <code class="mt-2 block rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                        php artisan supplier:sync-elicon-gas-meters
                    </code>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">Пробный запуск</div>
                        <p class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-400">Показывает результат без записи в базу.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">Обновление</div>
                        <p class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-400">Применяет цены, карточки, фото и атрибуты.</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">Лимит</div>
                        <p class="mt-1 text-sm leading-5 text-gray-600 dark:text-gray-400">Можно проверить несколько товаров перед полным запуском.</p>
                    </div>
                </div>
            </div>
        </section>

        @if ($lastOutput)
            <section class="rounded-xl bg-gray-950 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center justify-between border-b border-white/10 px-5 py-3">
                    <h2 class="text-sm font-semibold text-white">Последний вывод команды</h2>
                    <span class="rounded-md px-2 py-1 text-xs font-medium {{ $lastExitCode === 0 ? 'bg-success-500/20 text-success-300' : 'bg-danger-500/20 text-danger-300' }}">
                        exit {{ $lastExitCode }}
                    </span>
                </div>
                <pre class="max-h-[520px] overflow-auto whitespace-pre-wrap px-5 py-4 text-xs leading-5 text-gray-100">{{ $lastOutput }}</pre>
            </section>
        @endif
    </div>
</x-filament-panels::page>
