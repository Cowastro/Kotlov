@php
    $cleanEntries = [];

    foreach ($record->contact_history ?? [] as $entry) {
        if (! is_array($entry)) {
            continue;
        }

        $previous = end($cleanEntries);
        $note = mb_strtolower(trim((string) ($entry['note'] ?? ''), " \t\n\r\0\x0B,.;:–—-"));
        $previousNote = is_array($previous)
            ? mb_strtolower(trim((string) ($previous['note'] ?? ''), " \t\n\r\0\x0B,.;:–—-"))
            : null;
        $isRepeatedTerminalResult = in_array($entry['result'] ?? null, ['accepted', 'rejected'], true)
            && is_array($previous)
            && ($previous['stage'] ?? null) === ($entry['stage'] ?? null)
            && ($previous['result'] ?? null) === ($entry['result'] ?? null)
            && $previousNote === $note;

        if (! $isRepeatedTerminalResult) {
            $cleanEntries[] = $entry;
        }
    }

    $entries = collect($cleanEntries)->reverse();
@endphp

<div style="display:grid;gap:10px">
    @forelse ($entries as $entry)
        <div style="border:1px solid rgba(156,163,175,.25);border-radius:10px;padding:12px 14px">
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;font-size:13px">
                <strong>{{ \App\Models\CommercialDocument::CONTACT_RESULTS[$entry['result'] ?? ''] ?? ($entry['result'] ?? 'Контакт') }}</strong>
                <span style="color:#9ca3af;font-size:12px">
                    {{ filled($entry['contacted_at'] ?? null) ? \Illuminate\Support\Carbon::parse($entry['contacted_at'])->timezone('Europe/Minsk')->format('d.m.Y H:i') : '' }}
                    @if (filled($entry['user_name'] ?? null)) · {{ $entry['user_name'] }} @endif
                </span>
            </div>
            @if (filled(trim((string) ($entry['note'] ?? ''), " \t\n\r\0\x0B,.;:–—-")))
                <div style="margin-top:5px;font-size:13px;line-height:1.45">{{ trim((string) $entry['note'], " \t\n\r\0\x0B,.;:–—-") }}</div>
            @endif
            @if (filled($entry['next_follow_up_at'] ?? null))
                <div style="margin-top:7px;font-size:12px;font-weight:600;color:#f59e0b">
                    Следующий контакт: {{ \Illuminate\Support\Carbon::parse($entry['next_follow_up_at'])->timezone('Europe/Minsk')->format('d.m.Y H:i') }}
                </div>
            @endif
        </div>
    @empty
        <div style="font-size:13px;color:#9ca3af">Контакты с клиентом ещё не зафиксированы.</div>
    @endforelse
</div>
