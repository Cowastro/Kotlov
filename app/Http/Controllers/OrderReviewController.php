<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use App\Rules\NoHtmlOrLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderReviewController extends Controller
{
    public function show(Order $order, Request $request)
    {
        $products = $order->items()->with('product')->get()
            ->filter(fn ($item): bool => $item->product !== null)->unique('product_id');
        $submitted = Review::where('order_id', $order->id)->get(['reviewable_type', 'reviewable_id']);

        return response()->view('pages.order-review', [
            'orderNumber' => $order->number, 'products' => $products,
            'submitted' => $submitted, 'orderId' => $order->id, 'formUrl' => $request->fullUrl(),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Order $order, Request $request)
    {
        $productIds = $order->items()->whereNotNull('product_id')->pluck('product_id')->unique()->map(fn ($id): string => (string) $id)->all();
        $data = $request->validate([
            'target' => ['required', Rule::in(['order', ...$productIds])],
            'author_name' => ['required', 'string', 'max:100', new NoHtmlOrLinks],
            'rating' => ['required', 'integer', 'between:1,5'],
            'text' => ['required', 'string', 'min:10', 'max:2000', new NoHtmlOrLinks],
            'consent' => ['accepted'],
        ], [
            'target.in' => 'Выберите товар из вашего заказа.',
            'author_name.required' => 'Укажите имя для публикации.',
            'rating.required' => 'Поставьте оценку от 1 до 5.',
            'text.min' => 'Напишите хотя бы 10 символов.',
            'consent.accepted' => 'Подтвердите согласие на публикацию отзыва.',
        ]);
        $created = DB::transaction(function () use ($order, $data): bool {
            Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $type = $data['target'] === 'order' ? $order->getMorphClass() : (new \App\Models\Product)->getMorphClass();
            $targetId = $data['target'] === 'order' ? $order->id : (int) $data['target'];
            if (Review::where('order_id', $order->id)->where('reviewable_type', $type)->where('reviewable_id', $targetId)->exists()) {
                return false;
            }
            Review::create([
                'order_id' => $order->id, 'reviewable_type' => $type, 'reviewable_id' => $targetId,
                'author_name' => $data['author_name'], 'rating' => $data['rating'],
                'text' => $data['text'], 'is_approved' => false,
            ]);

            return true;
        });

        return redirect($request->fullUrl())->with('review_status', $created
            ? 'Спасибо! Ваш отзыв получен и появится на сайте после проверки.'
            : 'Ваш отзыв уже получен. Спасибо!');
    }
}
