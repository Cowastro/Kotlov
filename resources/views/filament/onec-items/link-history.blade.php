@forelse ($changes as $change)
    <p style="font-size:12px;overflow-wrap:anywhere;margin-bottom:6px">
        {{ \Carbon\Carbon::parse($change->created_at)->timezone('Europe/Minsk')->format('d.m.Y H:i') }} · {{ $change->user_name ?: 'Обмен / система' }}:
        #{{ $change->old_product_id ?: '—' }} → #{{ $change->new_product_id ?: '—' }}
        @if (str_contains($change->external_id, '#')) · характеристика {{ explode('#', $change->external_id)[1] }} @endif
    </p>
@empty
    <span>Изменений после включения истории пока нет.</span>
@endforelse
