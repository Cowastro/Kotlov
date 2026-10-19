<div class="space-y-2">
    @foreach($items as $item)
        <a
            href="{{ $item->publicProductUrl() }}"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3 text-sm transition hover:border-primary-500 hover:bg-primary-50 dark:border-white/10 dark:hover:bg-white/5"
        >
            <span>
                <span class="block font-semibold text-gray-950 dark:text-white">{{ $item->product_name }}</span>
                <span class="text-xs text-gray-500">{{ $item->product_sku ?: 'без артикула' }} · {{ $item->quantity }} шт.</span>
            </span>
            <x-heroicon-o-arrow-top-right-on-square class="h-5 w-5 shrink-0 text-primary-500"/>
        </a>
    @endforeach
</div>
