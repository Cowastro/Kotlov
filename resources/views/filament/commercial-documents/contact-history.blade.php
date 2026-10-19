@php($entries = collect($record->contact_history ?? [])->reverse())

<div class="space-y-3">
    @forelse ($entries as $entry)
        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <strong>{{ \App\Models\CommercialDocument::CONTACT_RESULTS[$entry['result'] ?? ''] ?? ($entry['result'] ?? 'Контакт') }}</strong>
                <span class="text-gray-500">
                    {{ filled($entry['contacted_at'] ?? null) ? \Illuminate\Support\Carbon::parse($entry['contacted_at'])->timezone('Europe/Minsk')->format('d.m.Y H:i') : '' }}
                    @if (filled($entry['user_name'] ?? null)) · {{ $entry['user_name'] }} @endif
                </span>
            </div>
            <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $entry['note'] ?? '' }}</div>
            @if (filled($entry['next_follow_up_at'] ?? null))
                <div class="mt-2 text-sm font-medium text-primary-600 dark:text-primary-400">
                    Следующий контакт: {{ \Illuminate\Support\Carbon::parse($entry['next_follow_up_at'])->timezone('Europe/Minsk')->format('d.m.Y H:i') }}
                </div>
            @endif
        </div>
    @empty
        <div class="text-sm text-gray-500">Контакты с клиентом ещё не зафиксированы.</div>
    @endforelse
</div>
