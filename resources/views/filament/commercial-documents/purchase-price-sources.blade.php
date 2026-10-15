@if ($sources->isEmpty())
    <p class="text-sm text-gray-500">Подтверждённых счетов поставщиков пока нет.</p>
@else
    <div class="space-y-3">
        @foreach ($sources as $source)
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $source->product?->name }}</div>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ $source->documentLabel() }} · {{ $source->supplierDisplayName() ?: 'Поставщик не указан' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold text-gray-950 dark:text-white">{{ number_format((float) $source->price_byn, 2, ',', ' ') }} BYN с НДС</div>
                        <div class="mt-1 text-xs {{ $source->isUsable() ? 'text-success-600' : 'text-danger-600' }}">
                            {{ $source->isUsable() ? 'Источник действует' : 'Источник просрочен или отключён' }}
                            @if ($source->valid_until)
                                · до {{ $source->valid_until->format('d.m.Y') }}
                            @endif
                        </div>
                    </div>
                </div>
                @if ($source->attachment_path)
                    <a
                        class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline"
                        href="{{ route('admin.purchase-price-sources.attachment', $source) }}"
                        target="_blank"
                    >
                        Открыть входящий счёт
                    </a>
                @endif
            </div>
        @endforeach
    </div>
@endif
