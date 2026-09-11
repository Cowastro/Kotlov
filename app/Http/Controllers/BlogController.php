<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    private const CANONICAL_BASE = 'https://kotlov.by';

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
            ->map(fn ($tag) => trim((string) $tag))
            ->filter(fn ($tag) => $tag !== '' && mb_strlen($tag) <= 28)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(12)
            ->values();

        $title = $activeCategory
            ? $activeCategory->name . ' — статьи | KOTLOV'
            : 'Статьи об отоплении — советы и обзоры | KOTLOV';

        $description = $activeCategory
            ? 'Статьи о ' . mb_strtolower($activeCategory->name) . '. Советы по выбору и эксплуатации отопительного оборудования.'
            : 'Полезные статьи об отоплении: выбор котла, печи, камина. Советы по монтажу и эксплуатации.';

        // Blog content is identical on city subdomains. Keep one canonical
        // version so ranking signals are consolidated on the primary domain.
        $canonicalBase = self::CANONICAL_BASE;
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

        $isHeatPumpContent = preg_match('/\bтеплов\p{L}*\s+насос\p{L}*/u', mb_strtolower($post->title)) === 1
            || collect($post->tags ?? [])->contains(
                fn ($tag) => preg_match('/\bтеплов\p{L}*\s+насос\p{L}*/u', mb_strtolower((string) $tag)) === 1
            );

        $isFireplaceContent = preg_match('/\b(камин|печь|топк|дымоход)\p{L}*/u', mb_strtolower($post->title)) === 1
            || collect($post->tags ?? [])->contains(
                fn ($tag) => preg_match('/\b(камин|печь|топк|дымоход)\p{L}*/u', mb_strtolower((string) $tag)) === 1
            );

        $isPelletBurnerContent = preg_match('/(пеллетн\p{L}*\s+горел|ceramic\s+pro|kotlov\s+xo)/u', mb_strtolower($post->title)) === 1
            || collect($post->tags ?? [])->contains(
                fn ($tag) => preg_match('/(пеллетн\p{L}*\s+горел|ceramic\s+pro|kotlov\s+xo)/u', mb_strtolower((string) $tag)) === 1
            );

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->when(
                $isHeatPumpContent,
                fn ($query) => $query->where(fn ($relatedQuery) => $relatedQuery
                    ->where('category_id', $post->category_id)
                    ->orWhereJsonContains('tags', 'тепловые насосы')),
                fn ($query) => $query->where('category_id', $post->category_id)
            )
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $heatPumpLinks = $isHeatPumpContent ? $this->heatPumpLinks($post) : collect();
        $fireplaceLinks = $isFireplaceContent ? $this->fireplaceLinks($post) : collect();
        $pelletBurnerLinks = $isPelletBurnerContent ? $this->pelletBurnerLinks($post) : collect();

        $title = $post->meta_title ?: ($post->title . ' | KOTLOV');
        $description = $post->meta_description ?: ($post->excerpt ?: mb_substr(strip_tags($post->content ?? ''), 0, 160));
        $canonical = self::CANONICAL_BASE . '/blog/' . $post->slug;
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
            'isHeatPumpContent',
            'heatPumpLinks',
            'isFireplaceContent',
            'fireplaceLinks',
            'isPelletBurnerContent',
            'pelletBurnerLinks',
            'schemaJson',
            'breadcrumbJson'
        ));
    }

    private function heatPumpLinks(BlogPost $post)
    {
        $links = collect([
            [
                'title' => 'Каталог тепловых насосов',
                'text' => 'Модели KOTLOV GE на R32 и R290, цены и характеристики.',
                'url' => '/teplovyie-nasosyi',
            ],
        ]);

        if ($post->slug !== 'kak-vybrat-teplovoy-nasos') {
            $links->push([
                'title' => 'Как выбрать тепловой насос',
                'text' => 'Мощность, COP, температура подачи, резерв и монтаж.',
                'url' => '/blog/kak-vybrat-teplovoy-nasos',
            ]);
        }

        if ($post->slug !== 'teplovye-nasosy-ge-r290-vysokotemperaturnye') {
            $links->push([
                'title' => 'R32 или R290',
                'text' => 'Когда важны тёплый пол, радиаторы и высокая температура воды.',
                'url' => '/blog/teplovye-nasosy-ge-r290-vysokotemperaturnye',
            ]);
        } else {
            $links->push([
                'title' => 'R290 на радиаторах: реальный объект',
                'text' => 'Монтаж высокотемпературного насоса в Острошицком Городке.',
                'url' => '/blog/teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok',
            ]);
        }

        $links->push([
            'title' => 'Монтаж теплового насоса под ключ',
            'text' => 'Расчёт, гидравлическая схема, установка и пусконаладка.',
            'url' => '/montazh-teplovyh-nasosov',
        ]);

        return $links->take(4)->values();
    }

    private function fireplaceLinks(BlogPost $post)
    {
        return collect([
            [
                'title' => 'Печи-камины для дома и дачи',
                'text' => 'Модели разной мощности, с плитой и длительным горением.',
                'url' => '/pechki',
            ],
            [
                'title' => 'Камины и каминные топки',
                'text' => 'Подбор топки под интерьер, площадь и режим эксплуатации.',
                'url' => '/kaminy',
            ],
            [
                'title' => 'Дымоходы и комплектующие',
                'text' => 'Диаметр, высота, проходы перекрытий и кровельные узлы.',
                'url' => '/dymohody',
            ],
            [
                'title' => 'Монтаж камина под ключ',
                'text' => 'Расчёт, комплектация, противопожарные узлы и запуск.',
                'url' => '/montazh-kaminov',
            ],
        ]);
    }

    private function pelletBurnerLinks(BlogPost $post)
    {
        return collect([
            [
                'title' => 'Акция −10% на Ceramic PRO 100 кВт',
                'text' => 'Цена, условия предложения и заявка на инженерный расчёт.',
                'url' => '/akcii/kotlov-xo-ceramic-pro',
            ],
            [
                'title' => 'KOTLOV XO Ceramic PRO 100 кВт',
                'text' => 'Фотографии, характеристики, комплектация и актуальная цена.',
                'url' => '/pelletnye-gorelki/pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt',
            ],
            [
                'title' => 'Каталог пеллетных горелок',
                'text' => 'Модели KOTLOV XO для частных и промышленных объектов.',
                'url' => '/pelletnye-gorelki',
            ],
            [
                'title' => 'Инженерный подбор и монтаж',
                'text' => 'Проверим котёл, дымоход, автоматику, шнек и бункер.',
                'url' => '/akcii/kotlov-xo-ceramic-pro#xo-request',
            ],
        ]);
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
                'url' => self::CANONICAL_BASE,
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
                    'item' => self::CANONICAL_BASE,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Статьи',
                    'item' => self::CANONICAL_BASE . '/blog',
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
