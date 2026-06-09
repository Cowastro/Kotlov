<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Rules\NoHtmlOrLinks;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'author_name'  => ['required', 'string', 'max:100', new NoHtmlOrLinks()],
            'author_phone' => ['nullable', 'string', 'max:50', new NoHtmlOrLinks()],
            'author_email' => ['nullable', 'email', 'max:100'],
            'rating'       => ['required', 'integer', 'min:1', 'max:5'],
            'text'         => ['required', 'string', 'min:10', 'max:2000', new NoHtmlOrLinks()],
        ], [
            'author_name.required' => 'Укажите ваше имя',
            'author_name.max'      => 'Имя слишком длинное',
            'author_email.email'   => 'Некорректный email',
            'rating.required'      => 'Выберите оценку',
            'rating.min'           => 'Оценка от 1 до 5',
            'rating.max'           => 'Оценка от 1 до 5',
            'text.required'        => 'Напишите текст отзыва',
            'text.min'             => 'Отзыв слишком короткий (минимум 10 символов)',
            'text.max'             => 'Отзыв слишком длинный (максимум 2000 символов)',
        ]);

        $product->reviews()->create([
            'author_name'  => $validated['author_name'],
            'author_phone' => $validated['author_phone'] ?? null,
            'author_email' => $validated['author_email'] ?? null,
            'rating'       => $validated['rating'],
            'text'         => $validated['text'],
            'is_approved'  => false,
        ]);

        return back()->with('review_sent', true);
    }
}
