@extends('layouts.amerce')

@section('content')
<main id="wrapper">
    <section class="flat-spacing">
        <div class="container" style="max-width:1000px;">
            <div class="sect-heading type-2 text-center" style="margin-bottom:32px;">
                <h1 class="s-title">Отзывы покупателей KOTLOV</h1>
                <p class="s-desc text-body-1 cl-text-2">О покупке оборудования, обслуживании и заказах. Отзывы публикуются после модерации.</p>
            </div>
            <div style="display:grid;gap:16px;">
            @forelse($reviews as $review)
                <article style="padding:22px;border:1px solid #e3e6e9;border-radius:16px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <strong>{{ $review->author_name }}</strong>
                        <span aria-label="{{ $review->rating }} из 5" style="color:#b57916;letter-spacing:2px;">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                    </div>
                    <div style="font-size:13px;color:#6d7680;margin:6px 0 14px;">
                        {{ $review->created_at->format('d.m.Y') }} · {{ $review->reviewable_type === (new \App\Models\Order)->getMorphClass() ? 'Обслуживание и заказ' : ($review->reviewable?->name ?: 'Товар') }}
                        @if($review->order_id)<span style="color:#28764b;"> · Отзыв по заказу</span>@endif
                    </div>
                    <p style="white-space:pre-line;overflow-wrap:anywhere;">{{ $review->text }}</p>
                    @if($review->reply)<div style="border-left:3px solid #de4147;padding-left:16px;margin-top:18px;"><strong>Ответ KOTLOV</strong><p style="white-space:pre-line;overflow-wrap:anywhere;">{{ $review->reply }}</p></div>@endif
                </article>
            @empty
                <p class="text-center">Пока нет опубликованных отзывов. Если вы уже сделали заказ, попросите менеджера прислать ссылку для отзыва.</p>
            @endforelse
            </div>
            <div style="margin-top:24px;">{{ $reviews->links() }}</div>
        </div>
    </section>
</main>
@endsection
