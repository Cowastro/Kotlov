@php
    $reviews = $order->customerReviews;
    $received = $reviews->first();
    $published = $reviews->firstWhere('is_approved', true);
    $canRequest = $order->canRequestCustomerReview();
    $canSendSms = $canRequest && \App\Filament\Resources\Orders\OrderResource::canEdit($order);
    $canCopyLink = $canRequest && \App\Filament\Resources\Orders\OrderResource::canView($order);
    $reviewActionContext = ($isTable ?? false) ? ['table' => true, 'recordKey' => (string) $order->getKey()] : [];
    $date = fn ($value) => \Illuminate\Support\Carbon::parse($value)->timezone('Europe/Minsk')->format('d.m.Y H:i').' (Минск)';
    $reviewUrl = fn ($review) => $review && \App\Filament\Resources\Reviews\ReviewResource::canView($review)
        ? \App\Filament\Resources\Reviews\ReviewResource::getUrl('view', ['record' => $review->id]) : null;
    $statuses = [
        [
            'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
            'active' => (bool) $order->review_sms_sent_at,
            'label' => $order->review_sms_sent_at ? 'СМС со ссылкой отправлена' : 'СМС со ссылкой не отправлена',
            'hint' => $order->review_sms_sent_at
                ? 'СМС со ссылкой принята SMS.BY: '.$date($order->review_sms_sent_at)
                : ($order->review_sms_attempted_at ? 'Была попытка отправки: '.$date($order->review_sms_attempted_at).'. Успешная отправка не подтверждена.' : 'СМС со ссылкой на отзыв ещё не отправляли'),
            'url' => null,
            'action' => $canSendSms ? 'customer_review_sms' : null,
        ],
        [
            'icon' => 'heroicon-o-pencil-square',
            'active' => (bool) $received,
            'label' => $received ? 'Отзыв получен — открыть' : 'Отзыв не получен',
            'hint' => $received ? 'Отзыв получен: '.$date($received->created_at).'. Всего: '.$reviews->count().'. Открыть отзыв' : 'Клиент ещё не оставил отзыв',
            'url' => $reviewUrl($received),
            'action' => ! $received && $canCopyLink ? 'customer_review_link' : null,
        ],
        [
            'icon' => 'heroicon-o-check-badge',
            'active' => (bool) $published,
            'label' => $published ? 'Отзыв опубликован — открыть' : 'Отзыв не опубликован',
            'hint' => $published ? 'Опубликовано отзывов: '.$reviews->where('is_approved', true)->count().' из '.$reviews->count().'. Открыть опубликованный отзыв'
                : ($received ? 'Отзыв получен, но не опубликован. Открыть отзыв' : 'Опубликованного отзыва пока нет'),
            'url' => $reviewUrl($published ?: $received),
            'action' => null,
        ],
    ];
@endphp
<div class="order-review-status" aria-label="СМС и отзыв клиента" style="display:inline-flex;width:max-content;align-items:center;justify-content:flex-start;gap:5px;margin-top:2px;">
    @foreach($statuses as $status)
        @php
            $style = 'display:inline-flex;align-items:center;justify-content:center;width:28px;min-width:28px;max-width:28px;height:28px;border-radius:6px;flex:0 0 28px;'
                .($status['active'] ? 'color:#22c55e;background:rgba(34,197,94,.1);' : 'color:#9ca3af;background:rgba(156,163,175,.1);');
        @endphp
        @if($status['action'])
            <button type="button" title="{{ $status['label'] }} · {{ $status['hint'] }} · {{ $status['action'] === 'customer_review_sms' ? 'Отправить СМС' : 'Скопировать ссылку' }}"
                aria-label="{{ $status['action'] === 'customer_review_sms' ? 'Отправить ссылку на отзыв по СМС' : 'Скопировать ссылку на отзыв' }}"
                style="{{ $style }}cursor:pointer;" wire:click.stop="mountAction(@js($status['action']), {}, @js($reviewActionContext))">
                <x-filament::icon :icon="$status['icon']" style="width:18px;height:18px;" />
            </button>
        @elseif($status['url'])
            <a href="{{ $status['url'] }}" title="{{ $status['hint'] }}" aria-label="{{ $status['label'] }}" style="{{ $style }}" x-on:click.stop>
                <x-filament::icon :icon="$status['icon']" style="width:18px;height:18px;" />
            </a>
        @else
            <span title="{{ $status['hint'] }}{{ ! $canRequest ? ' · Запрос отзыва доступен после завершения сделки' : '' }}" aria-label="{{ $status['label'] }}" role="img" style="{{ $style }}">
                <x-filament::icon :icon="$status['icon']" style="width:18px;height:18px;" />
            </span>
        @endif
    @endforeach
</div>
