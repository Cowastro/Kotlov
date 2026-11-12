@extends('layouts.amerce')

@section('title', 'Отзывы о магазине KOTLOV — обслуживание и заказы')
@section('description', 'Отзывы покупателей об обслуживании в KOTLOV: консультации, оформление заказов, доставка и работа менеджеров. Отзывы об оборудовании размещены в карточках товаров.')

@section('content')
<main id="wrapper">
    <section class="flat-spacing">
        <div class="container" style="max-width:1000px;">
            <div class="sect-heading type-2 text-center" style="margin-bottom:32px;">
                <h1 class="s-title">Отзывы о магазине KOTLOV</h1>
                <p class="s-desc text-body-1 cl-text-2">О консультации, оформлении заказа, доставке и работе менеджеров.</p>
                <p style="margin-top:12px;color:#6d7680;">Отзывы об оборудовании смотрите в <a href="/catalog" class="link" style="text-decoration:underline;">карточках товаров</a>.</p>
            </div>
            <div style="display:grid;gap:16px;">
            @forelse($reviews as $review)
                <article style="padding:22px;border:1px solid #e3e6e9;border-radius:16px;">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                        <strong>{{ $review->author_name }}</strong>
                        <span aria-label="{{ $review->rating }} из 5" style="color:#b57916;letter-spacing:2px;">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', 5 - (int) $review->rating) }}</span>
                    </div>
                    <div style="font-size:13px;color:#6d7680;margin:6px 0 14px;">
                        {{ $review->created_at->format('d.m.Y') }} · Обслуживание и заказ
                        @if($review->order_id)<span style="color:#28764b;"> · Отзыв по заказу</span>@endif
                    </div>
                    <p style="white-space:pre-line;overflow-wrap:anywhere;">{{ $review->text }}</p>
                    @if($review->reply)<div style="border-left:3px solid #de4147;padding-left:16px;margin-top:18px;"><strong>Ответ KOTLOV</strong><p style="white-space:pre-line;overflow-wrap:anywhere;">{{ $review->reply }}</p></div>@endif
                </article>
            @empty
                <p class="text-center">Пока нет опубликованных отзывов о магазине. Если вы уже сделали заказ, попросите менеджера прислать ссылку для отзыва.</p>
            @endforelse
            </div>
            <div style="margin-top:24px;">{{ $reviews->links() }}</div>
        </div>
    </section>
</main>
@endsection
