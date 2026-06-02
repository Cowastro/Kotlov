<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::published()
            ->with(['category'])
            ->orderByDesc('published_at');

        // Фильтр по категории
        $activeCategory = null;
        if ($request->filled('category')) {
            $activeCategory = BlogCategory::where('slug', $request->category)
                ->where('is_active', true)
                ->first();
            if ($activeCategory) {
                $query->where('category_id', $activeCategory->id);
            }
        }

        // Поиск
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                   ->orWhere('excerpt', 'like', "%{$q}%")
                   ->orWhere('content', 'like', "%{$q}%");
            });
        }

        // Фильтр по тегу
        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        $posts      = $query->paginate(6)->withQueryString();
        $totalCount = BlogPost::published()->count();

        // Сайдбар: категории с количеством статей
        $categories = BlogCategory::where('is_active', true)
            ->withCount(['posts' => fn($q) => $q->published()])
            ->having('posts_count', '>', 0)
            ->orderBy('sort_order')
            ->get();

        // Последние 4 статьи для сайдбара
        $recentPosts = BlogPost::published()
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        // Все теги из опубликованных статей
        $tags = BlogPost::published()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('pages.blog', compact(
            'posts', 'categories', 'recentPosts',
            'tags', 'activeCategory', 'totalCount'
        ));
    }

    public function show(string $slug)
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->with(['category', 'author'])
            ->firstOrFail();

        // Счётчик просмотров
        $post->increment('views_count');

        // Похожие статьи (та же категория)
        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category_id', $post->category_id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('pages.blog-single', compact('post', 'related'));
    }
}
