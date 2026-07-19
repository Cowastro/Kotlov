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
        $canonical = 'https://' . request()->getHost() . '/blog/' . $post->slug;
        $ogImage = $post->cover_image_url;
        $ogImageSecure = $ogImage;
        $ogImageWidth = 1600;
        $ogImageHeight = 900;
        $ogImageType = 'image/jpeg';
        $ogType = 'article';
        $ogUrl = $canonical;
        $ogTitle = $title;
        $ogDescription = $description;
        $twitterTitle = $title;
        $twitterDescription = $description;
        $twitterImage = $ogImage;
        $articlePublishedTime = optional($post->published_at)->toAtomString();
        $articleModifiedTime = optional($post->updated_at)->toAtomString();
        $articleSection = optional($post->category)->name;
        $articleTags = $post->tags ?? [];

        $schemaJson = json_encode($this->articleSchema($post, $canonical), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $breadcrumbJson = json_encode($this->breadcrumbSchema($post, $canonical), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return view('pages.blog-single', compact(
            'post',
            'related',
            'title',
            'description',
            'canonical',
            'ogType',
            'ogUrl',
            'ogTitle',
            'ogDescription',
            'ogImage',
            'ogImageSecure',
            'ogImageWidth',
            'ogImageHeight',
            'ogImageType',
            'twitterTitle',
            'twitterDescription',
            'twitterImage',
            'articlePublishedTime',
            'articleModifiedTime',
            'articleSection',
            'articleTags',
            'schemaJson',
            'breadcrumbJson'
        ));
    }

    private function articleSchema(BlogPost $post, string $canonical): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ],
            'headline' => $post->title,
            'description' => $post->meta_description ?: strip_tags($post->excerpt ?? ''),
            'image' => [$post->cover_image_url],
            'datePublished' => optional($post->published_at)->toAtomString(),
            'dateModified' => optional($post->updated_at)->toAtomString(),
            'author' => [
                '@type' => 'Organization',
                'name' => 'KOTLOV',
                'url' => 'https://' . request()->getHost(),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'KOTLOV',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('img/logo.png'),
                ],
            ],
        ];

        if ($post->slug === 'kak-vybrat-teplovoy-nasos') {
            $schema['mainEntity'] = [
                [
                    '@type' => 'Question',
                    'name' => 'Какой тепловой насос выбрать для частного дома?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Для большинства новых домов чаще выбирают воздушный тепловой насос воздух-вода. Он проще по монтажу, дешевле геотермального решения и подходит для низкотемпературного отопления.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Что важнее при выборе: мощность или COP?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Важны оба параметра, но сначала считают теплопотери дома и требуемую мощность при низкой температуре. COP помогает сравнить экономичность, но без правильной мощности насос будет работать хуже.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Можно ли ставить тепловой насос со старыми радиаторами?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Можно, но систему нужно проверить. Тепловые насосы лучше работают с тёплым полом или увеличенными радиаторами, где нужна более низкая температура подачи.',
                    ],
                ],
            ];
        }

        return $schema;
    }

    private function breadcrumbSchema(BlogPost $post, string $canonical): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Главная',
                    'item' => 'https://' . request()->getHost(),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Статьи',
                    'item' => 'https://' . request()->getHost() . '/blog',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $post->title,
                    'item' => $canonical,
                ],
            ],
        ];
    }
}
