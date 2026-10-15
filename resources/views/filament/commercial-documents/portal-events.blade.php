@php
    $labels = [
        'link_created' => 'Создана онлайн-ссылка',
        'first_device_activated' => 'Подтверждено первое устройство',
        'verification_code_sent' => 'Отправлен код подтверждения',
        'device_verified' => 'Подтверждено новое устройство',
        'device_revoked' => 'Устройство отключено',
        'proposal_viewed' => 'КП просмотрено',
        'pdf_downloaded' => 'Открыт PDF',
        'proposal_accepted' => 'КП принято клиентом',
        'revision_requested' => 'Запрошена корректировка',
        'proposal_rejected' => 'КП отклонено клиентом',
        'snapshot_refreshed' => 'Данные товаров обновлены',
        'link_revoked' => 'Онлайн-доступ отозван',
    ];
@endphp

<div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <div class="mb-3 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Журнал онлайн-КП</h3>
        <span class="text-xs text-gray-500">Последние {{ $events->count() }} событий</span>
    </div>

    <div class="max-h-96 overflow-y-auto">
        @forelse($events as $event)
            <div class="grid gap-1 border-t border-gray-100 py-3 first:border-t-0 sm:grid-cols-[140px_minmax(0,1fr)] dark:border-white/5">
                <time class="text-xs text-gray-500">{{ $event->occurred_at?->format('d.m.Y H:i:s') }}</time>
                <div>
                    <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $labels[$event->type] ?? $event->type }}</div>
                    @if($event->session?->device_label)
                        <div class="text-xs text-gray-500">{{ $event->session->device_label }}</div>
                    @endif
                    @if(($event->metadata['reason'] ?? null) === 'superseded_revision')
                        <div class="text-xs text-gray-500">Заменено редакцией {{ $event->metadata['replaced_by_number'] ?? '—' }}</div>
                    @elseif(filled($event->metadata['note'] ?? null))
                        <div class="text-xs text-gray-500">{{ $event->metadata['note'] }}</div>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Событий пока нет.</p>
        @endforelse
    </div>
</div>
