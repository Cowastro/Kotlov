@php
    $active = $sessions->filter(fn ($session) => ! $session->revoked_at && $session->expires_at?->isFuture());
@endphp

<div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Подтверждённые устройства</h3>
        <span class="text-xs text-gray-500">Активных: {{ $active->count() }}</span>
    </div>

    @forelse($sessions as $session)
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 py-3 first:border-t-0 dark:border-white/5">
            <div>
                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $session->device_label ?: 'Неизвестное устройство' }}</div>
                <div class="text-xs text-gray-500">
                    Подтверждено {{ $session->authorized_at?->format('d.m.Y H:i') }}
                    · последнее посещение {{ $session->last_seen_at?->format('d.m.Y H:i') ?: 'не было' }}
                </div>
            </div>
            @if($session->revoked_at)
                <span class="rounded-full bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">Отключено</span>
            @elseif($session->expires_at?->isPast())
                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400">Срок истёк</span>
            @else
                <span class="rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">Активно</span>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">Клиент ещё не подтвердил ни одного устройства.</p>
    @endforelse
</div>
