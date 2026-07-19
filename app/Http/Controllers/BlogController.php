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

        $activeCategory = null;
        if ($request->filled('category')) {
            $activeCategory = BlogCategory::where('slug', $request->category)
                ->where('is_active', true)
                ->first();

            if ($activeCategory) {
                $query->where('category_id', $activeCategory->id);
            }
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%");
            });
        }

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        $posts = $query->paginate(6)->withQueryString();
        $totalCount = BlogPost::published()->count();

        $categories = BlogCategory::where('is_active', true)
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->having('posts_count', '>', 0)
            ->orderBy('sort_order')
            ->get();

        $recentPosts = BlogPost::published()
            ->orderByDesc('published_at')
            ->limit(4)
            ->get();

        $tags = BlogPost::published()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $title = $activeCategory
            ? $activeCategory->name . ' — статьи | KOTLOV'
            : 'Статьи об отоплении — советы и обзоры | KOTLOV';

        $description = $activeCategory
            ? 'Статьи о ' . mb_strtolower($activeCategory->name) . '. Советы по выбору и эксплуатации отопительного оборудования.'
            : 'Полезные статьи об отоплении: выбор котла, печи, камина. Советы по монтажу и эксплуатации.';

        $canonicalBase = 'https://' . request()->getHost();
        $canonical = $canonicalBase . '/blog' . ($activeCategory ? '?category=' . $activeCategory->slug : '');

        return view('pages.blog', compact(
            'posts',
            'categories',
            'recentPosts',
            'tags',
            'activeCategory',
            'totalCount',
            'title',
            'description',
            'canonical'
        ));
    }

    public function show(string $slug)
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->with(['category', 'author'])
            ->firstOrFail();

        $post->increment('views_count');

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category_id', $post->category_id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $title = $post->meta_title ?: ($post->title . ' | KOTLOV');
        $description = $post->meta_description ?: ($post->excerpt ?: mb_substr(strip_tags($post->content ?? ''), 0, 160));
        $canonicalBase = 'https://' . request()->getHost();
        $canonical = $canonicalBase . '/blog/' . $post->slug;
        $ogImage = $post->cover_image_url;

        return view('pages.blog-single', compact('post', 'related', 'title', 'description', 'canonical', 'ogImage'));
    }
}
